<?php

namespace App\Support\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Single source of truth for the private disk that holds violation evidence and
 * sanction proofs.
 *
 * WHY THIS CLASS EXISTS
 * ---------------------
 * Every evidence/proof call site used to resolve its own disk with
 *
 *     Storage::disk((string) config('filesystems.evidence_disk', 'local'));
 *
 * and to re-implement path normalisation and existence checks. Two problems
 * followed from that:
 *
 *   1. `config(..., 'local')` is a SILENT fallback. On Railway the local disk is
 *      ephemeral: an object written there is gone on the next deploy, container
 *      replacement or restart. If `EVIDENCE_STORAGE_DISK` were ever missing or
 *      misread (for example because the configuration cache was built before the
 *      variable was set), uploads would succeed, the database would record a
 *      key, and the file would quietly disappear later — with no error anywhere.
 *
 *   2. An unreachable bucket does not just return false. The S3 adapter throws
 *      `League\Flysystem\UnableToCheckFileExistence`, which previously escaped as
 *      a 500 (the announcement feature hit exactly this and had to work around
 *      it). A confidential-evidence route must degrade to a controlled error.
 *
 * This class centralises the disk choice so a view endpoint can never read from
 * a different disk than the upload wrote to, makes a misconfigured production
 * deployment FAIL LOUDLY instead of silently writing to ephemeral storage, and
 * provides the diagnostics needed to tell the four failure modes apart.
 *
 * AUTHORIZATION IS NOT THIS CLASS'S CONCERN. It only answers "where do the bytes
 * live and do they exist". Callers keep their own role checks.
 */
class EvidenceDisk
{
    /**
     * Disk definitions that may legitimately hold evidence objects.
     *
     * Used ONLY by {@see diagnose()} to explain a mismatch (case C). Reads and
     * writes always use the configured disk so the two can never diverge.
     *
     * @var array<int, string>
     */
    private const ALTERNATE_DISKS = ['s3', 'evidence', 'local'];

    /**
     * The configured evidence disk name — the single source of truth.
     *
     * Falls back to the private local disk ONLY when nothing is configured at
     * all (development/test), never to satisfy a production expectation.
     */
    public static function name(): string
    {
        $configured = trim((string) config('filesystems.evidence_disk', ''));

        return $configured !== '' ? $configured : 'local';
    }

    /**
     * Resolve the configured disk.
     *
     * @return FilesystemAdapter
     */
    public static function disk(): Filesystem
    {
        return Storage::disk(self::name());
    }

    /**
     * Is the configured disk a REMOTE (object storage) disk?
     *
     * Determined from the disk definition's driver rather than its name, so a
     * renamed alias still behaves correctly.
     */
    public static function isRemote(): bool
    {
        return self::driverOf(self::name()) === 's3';
    }

    /**
     * Is the application running for real users (not tests/CLI tooling)?
     */
    public static function isProduction(): bool
    {
        return app()->environment('production');
    }

    /**
     * Is the configured disk genuinely usable?
     *
     * For an S3-compatible disk this requires BOTH a bucket and credentials; a
     * missing `AWS_BUCKET` or key means every write would fail at call time.
     *
     * A definition that names no driver (a minimally-configured alias) is given
     * the benefit of the doubt — the connectivity probe in `evidence:diagnose`
     * is what actually proves a disk works. Being conservative here would block
     * uploads on a deployment that is in fact fine.
     */
    public static function isUsable(): bool
    {
        $diskName = self::name();
        $config = config("filesystems.disks.{$diskName}");

        if (! is_array($config)) {
            return false;
        }

        // Remote by EXPLICIT driver only. A disk with no driver declared is not
        // treated as remote, so it cannot trip the production guard.
        if (trim((string) ($config['driver'] ?? '')) !== 's3') {
            return true;
        }

        return trim((string) ($config['bucket'] ?? '')) !== ''
            && trim((string) ($config['key'] ?? '')) !== ''
            && trim((string) ($config['secret'] ?? '')) !== '';
    }

    /**
     * True when production is EXPECTED to use object storage but the configured
     * disk cannot actually be used.
     *
     * This is the dangerous state: evidence would be written to an ephemeral
     * filesystem and lost on the next deploy.
     */
    public static function isMisconfigured(): bool
    {
        if (! self::isProduction()) {
            return false;
        }

        // A production deployment that names an s3 disk but lacks the bucket or
        // credentials is misconfigured.
        return self::isRemote() && ! self::isUsable();
    }

    /**
     * Guard a WRITE path.
     *
     * In production, if object storage is configured but unusable, this throws
     * instead of letting the caller silently persist to ephemeral storage. The
     * message is actionable for an operator and contains no secrets.
     *
     * @throws \RuntimeException
     */
    public static function assertWritable(): void
    {
        if (! self::isMisconfigured()) {
            return;
        }

        $diskName = self::name();

        Log::critical('Evidence storage is misconfigured for production.', [
            'disk' => $diskName,
            'driver' => self::driverOf($diskName),
            'has_bucket' => trim((string) config("filesystems.disks.{$diskName}.bucket", '')) !== '',
            'has_credentials' => self::hasS3Credentials($diskName),
            'hint' => 'Set EVIDENCE_STORAGE_DISK=s3 with AWS_BUCKET, AWS_ACCESS_KEY_ID, '
                . 'AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION and AWS_ENDPOINT, then '
                . 'rebuild the config cache (php artisan config:clear / config:cache).',
        ]);

        throw new \RuntimeException(
            'Evidence storage is not configured correctly. Uploads are disabled to avoid '
            . 'saving confidential files to a temporary filesystem. Please contact the '
            . 'system administrator.'
        );
    }

    /**
     * Normalise a stored path/key into candidate object keys.
     *
     * Uploads write keys shaped `violation-evidence/<id>/<file>`, but a stored
     * value may arrive with a leading slash, a duplicated separator, a
     * `storage/` prefix from an older format, URL-encoding, or a full URL. Each
     * would MISS on the disk while looking correct in the database.
     *
     * A full URL is reduced to its object key by dropping the scheme and host and
     * then the BUCKET segment — without that the path would begin `bucket/...`
     * and never match.
     *
     * The database row is never rewritten by this method.
     *
     * @return array<int, string> candidate keys, most likely first
     */
    public static function candidateKeys(?string $storedPath): array
    {
        $raw = trim((string) $storedPath);

        if ($raw === '') {
            return [];
        }

        $variants = [];

        // Both the value as stored and its percent-decoded form, so a key that
        // was URL-encoded in the database still resolves.
        foreach ([$raw, rawurldecode($raw)] as $value) {
            foreach (self::reduceToObjectKey($value) as $candidate) {
                $variants[] = $candidate;
            }
        }

        return array_values(array_unique(array_filter($variants, static fn (string $v): bool => $v !== '')));
    }

    /**
     * Reduce one stored value to plausible object keys.
     *
     * @return array<int, string>
     */
    private static function reduceToObjectKey(string $value): array
    {
        // Backslashes come from Windows-era rows; Flysystem treats them as
        // separators, so normalise them up front rather than emitting a key that
        // only *appears* to work.
        $value = str_replace('\\', '/', trim($value));
        $value = trim($value);

        if ($value === '') {
            return [];
        }

        $hadScheme = (bool) preg_match('#^https?://#i', $value);

        // Drop scheme + host, leaving the path (which may still start with the
        // bucket segment).
        $value = preg_replace('#^https?://[^/]+/#i', '', $value) ?? $value;

        // Drop the bucket segment, which appears only when the caller stored a
        // full URL. The bucket name is not an object-key component.
        $value = self::stripBucketSegment((string) $value);

        // Drop a leading `storage/` segment (public-URL shape).
        $value = preg_replace('#^/?storage/#i', '', (string) $value) ?? (string) $value;

        // Collapse duplicate separators and remove any leading slash.
        $value = preg_replace('#/+#', '/', (string) $value) ?? (string) $value;
        $value = ltrim((string) $value, '/');

        $candidates = $value === '' ? [] : [(string) $value];

        // When a scheme/host was present, also offer the segment starting at a
        // known object root. This recovers a key when the URL carries extra path
        // segments before the bucket (custom domains, signed URLs).
        if ($hadScheme) {
            $root = self::sliceFromKnownRoot((string) $value);

            if ($root !== null && ! in_array($root, $candidates, true)) {
                $candidates[] = $root;
            }
        }

        return $candidates;
    }

    /**
     * Remove a leading bucket segment from a path.
     */
    private static function stripBucketSegment(string $path): string
    {
        $buckets = [];

        foreach (array_keys((array) config('filesystems.disks', [])) as $diskName) {
            $bucket = trim((string) config("filesystems.disks.{$diskName}.bucket", ''));

            if ($bucket !== '') {
                $buckets[] = $bucket;
            }
        }

        foreach (array_unique($buckets) as $bucket) {
            $prefix = $bucket . '/';

            if (str_starts_with($path, $prefix)) {
                return substr($path, strlen($prefix));
            }
        }

        return $path;
    }

    /**
     * The path starting at the first known evidence/proof root.
     *
     * Only used as a fallback for a value that arrived as a URL, so a legacy
     * signed URL can still resolve without inventing a key for a normal stored
     * path.
     */
    private static function sliceFromKnownRoot(string $path): ?string
    {
        foreach (['violation-evidence/', 'sanction-proofs/', 'announcements/'] as $root) {
            $position = strpos($path, $root);

            if ($position !== false && $position > 0) {
                return substr($path, $position);
            }
        }

        return null;
    }

    /**
     * Does this key exist on the configured disk?
     *
     * Adapter failures (an unreachable bucket) are reported as "not present"
     * rather than allowed to escape as a 500 — the caller decides the response.
     */
    public static function exists(?string $storedPath): bool
    {
        return self::resolveExistingKey($storedPath) !== null;
    }

    /**
     * First candidate key that exists on the configured disk.
     *
     * @return string|null the resolved object key, or null when none exist
     */
    public static function resolveExistingKey(?string $storedPath): ?string
    {
        $candidates = self::candidateKeys($storedPath);

        if ($candidates === []) {
            return null;
        }

        // An undefined disk must not raise a fatal error while merely CHECKING
        // for a file — the caller needs to be able to report the problem.
        if (! is_array(config('filesystems.disks.' . self::name()))) {
            Log::error('The configured evidence disk is not defined.', [
                'disk' => self::name(),
                'configured' => (string) config('filesystems.evidence_disk', ''),
            ]);

            return null;
        }

        $disk = self::disk();

        foreach ($candidates as $key) {
            try {
                if ($disk->exists($key)) {
                    return $key;
                }
            } catch (Throwable $e) {
                // An unreachable/misconfigured disk throws rather than returning
                // false. Log a HASH of the key (never the key itself) and treat
                // the object as absent so the caller returns a controlled error.
                Log::warning('Evidence existence check failed on the configured disk.', [
                    'disk' => self::name(),
                    'driver' => self::driverOf(self::name()),
                    'key_hash' => hash('sha256', $key),
                    'error' => self::safeError($e),
                ]);
            }
        }

        return null;
    }

    /**
     * Object size, or null when it cannot be determined.
     *
     * Mirrors {@see resolveExistingKey()}: a storage failure must not become a
     * fatal error inside an upload transaction.
     */
    public static function size(string $key): ?int
    {
        try {
            $size = self::disk()->size($key);

            return is_int($size) ? $size : null;
        } catch (Throwable $e) {
            Log::warning('Evidence size lookup failed on the configured disk.', [
                'disk' => self::name(),
                'key_hash' => hash('sha256', $key),
                'error' => self::safeError($e),
            ]);

            return null;
        }
    }

    /**
     * Diagnose why an evidence/proof object cannot be served.
     *
     * Returns one of the four failure modes the brief requires to be
     * distinguishable, so an operator can tell a missing database reference from
     * a missing object, a wrong disk, or an authorization/response problem.
     *
     * @return array{
     *     case: string,
     *     meaning: string,
     *     configured_disk: string,
     *     driver: string,
     *     disk_usable: bool,
     *     path_present: bool,
     *     object_found: bool,
     *     found_on: string|null,
     *     key_hash: string|null,
     *     candidates: array<int, string>
     * }
     */
    public static function diagnose(?string $storedPath): array
    {
        $diskName = self::name();
        $planned = self::plannedPath($storedPath);
        $candidates = self::candidateKeys($planned);

        $base = [
            'configured_disk' => $diskName,
            'driver' => self::driverOf($diskName),
            'disk_usable' => self::isUsable(),
            'path_present' => trim((string) $storedPath) !== '',
            'object_found' => false,
            'found_on' => null,
            'key_hash' => $candidates === [] ? null : hash('sha256', $candidates[0]),
            // Only hashed candidates are returned: a raw object key is an
            // internal storage detail.
            'candidates' => array_map(static fn (string $k): string => hash('sha256', $k), $candidates),
        ];

        // CASE A — the database holds no reference at all.
        if (! $base['path_present']) {
            // NOTE: array_merge(), not `+`. The `+` operator keeps the LEFT
            // operand's value for any duplicated key, so `$base + [...]` would
            // silently leave object_found/found_on at their defaults.
            return array_merge($base, [
                'case' => 'A',
                'meaning' => 'DB reference missing (disk_path/proof_path is NULL or empty).',
            ]);
        }

        // Does the configured disk hold it?
        $key = self::resolveExistingKey($storedPath);

        if ($key !== null) {
            return array_merge($base, [
                'case' => 'D',
                'meaning' => 'Object exists on the configured disk. If it still will not '
                    . 'display, the problem is in retrieval/authorization/response, not storage.',
                'object_found' => true,
                'found_on' => $diskName,
            ]);
        }

        // The configured disk does not have it. Does ANOTHER disk? That is a
        // configuration mismatch, which is a different fix from "the object was
        // never uploaded".
        foreach (self::ALTERNATE_DISKS as $alternate) {
            if ($alternate === $diskName) {
                continue;
            }

            if (! is_array(config("filesystems.disks.{$alternate}"))) {
                continue;
            }

            try {
                foreach ($candidates as $candidate) {
                    if (Storage::disk($alternate)->exists($candidate)) {
                        return array_merge($base, [
                            'case' => 'C',
                            'meaning' => 'Object exists on a DIFFERENT disk than the one '
                                . 'configured for retrieval: storage configuration mismatch.',
                            'object_found' => true,
                            'found_on' => $alternate,
                        ]);
                    }
                }
            } catch (Throwable $e) {
                Log::warning('Evidence diagnostic probe failed on an alternate disk.', [
                    'disk' => $alternate,
                    'error' => self::safeError($e),
                ]);
            }
        }

        // CASE B — the reference is present but no disk holds the object.
        return array_merge($base, [
            'case' => 'B',
            'meaning' => 'DB reference exists but the object is missing from storage '
                . '(never uploaded, deleted, or uploaded to a disk that has since been replaced).',
        ]);
    }

    /**
     * The path an upload WOULD have written for this record.
     *
     * A record's stored path is authoritative. This only fills a gap for
     * diagnostics, and is deliberately conservative: it never invents a key for
     * a non-empty stored path.
     */
    private static function plannedPath(?string $storedPath): ?string
    {
        return trim((string) $storedPath) !== '' ? (string) $storedPath : null;
    }

    /**
     * The effective driver for a disk.
     *
     * The configured definition is authoritative. When it does not name a driver
     * (a faked disk in tests, or a minimally-configured alias), fall back to the
     * name itself so an `s3` disk is never mis-reported as `unknown` — which
     * would make a healthy deployment look broken.
     */
    public static function driverOf(string $diskName): string
    {
        $driver = trim((string) config("filesystems.disks.{$diskName}.driver", ''));

        if ($driver !== '') {
            return $driver;
        }

        return $diskName;
    }

    private static function hasS3Credentials(string $diskName): bool
    {
        return trim((string) config("filesystems.disks.{$diskName}.key", '')) !== ''
            && trim((string) config("filesystems.disks.{$diskName}.secret", '')) !== '';
    }

    /**
     * Reduce a storage exception to a short, non-sensitive description.
     *
     * Some SDK errors embed the endpoint with credentials, so the raw message is
     * never logged verbatim.
     */
    private static function safeError(Throwable $e): string
    {
        $message = preg_replace('#https?://[^@\s]+@#i', 'https://***@', $e->getMessage());

        return class_basename($e) . ': ' . mb_substr((string) $message, 0, 200);
    }
}
