<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Serves a PRIVATE evidence/proof file from the configured disk.
 *
 * Every evidence/proof controller used to inline the same three steps:
 * resolve the configured disk, check the stored key exists, and stream a
 * response — aborting with a bare 404 when anything was missing. That produced
 * a raw error page and no operator-visible explanation.
 *
 * This service centralises the flow so ANY caller gets identical behaviour:
 *
 *   1. a missing stored key  -> a controlled, friendly application error;
 *   2. an object missing from storage -> the same controlled error;
 *   3. a storage failure -> a controlled error;
 *   4. success -> a streamed response with the correct filename.
 *
 * Storage stays PRIVATE: the file is streamed through the application, so no
 * bucket name, object key or disk path is ever exposed to the client. Technical
 * detail is logged server-side only.
 */
class PrivateFileResponder
{
    /**
     * Controlled error messages, one per file kind.
     *
     * @var array<string, array{missing: string, unavailable: string}>
     */
    private const MESSAGES = [
        'evidence' => [
            'missing' => 'Evidence could not be found. Please contact the system administrator if the problem persists.',
            'unavailable' => 'Unable to load the evidence at this time. Please try again later.',
        ],
        'proof' => [
            'missing' => 'Proof document could not be found. Please contact the system administrator if the problem persists.',
            'unavailable' => 'Unable to load the file at this time. Please try again later.',
        ],
    ];

    /**
     * Resolve the configured private disk for evidence/proof storage.
     *
     * Single source of truth for the disk choice, so a view endpoint can never
     * read from a different disk than the upload wrote to.
     *
     * @return Filesystem
     */
    public static function disk(): Filesystem
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk((string) config('filesystems.evidence_disk', 'local'));

        return $disk;
    }

    /**
     * Normalise a stored path/key.
     *
     * Uploads write keys shaped `violation-evidence/<id>/<file>`, but a stored
     * value may arrive with a leading slash, a duplicated separator, or a
     * leading `storage/`/`/storage/` segment from an older format. Each of those
     * would MISS on the disk while looking correct in the database, so they are
     * stripped here. The database row itself is never rewritten.
     *
     * @return array<int, string> candidate keys, most-likely first
     */
    public static function candidateKeys(?string $storedPath): array
    {
        $raw = trim((string) $storedPath);

        if ($raw === '') {
            return [];
        }

        // Decode percent-encoding in case the key was stored URL-encoded.
        $decoded = rawurldecode($raw);

        // Tolerate backslashes from Windows-era rows.
        $decoded = str_replace('\\', '/', $decoded);

        $variants = [];

        foreach ([$raw, $decoded] as $value) {
            $value = trim($value);

            // Strip an accidental scheme/host, keeping only the object key.
            $value = preg_replace('#^https?://[^/]+/#i', '', $value);

            // Strip a leading `storage/` or `/storage/` prefix (public-URL shape).
            $value = preg_replace('#^/?storage/#i', '', $value);

            // Collapse duplicate slashes and drop any leading slash.
            $value = preg_replace('#/+#', '/', $value);
            $value = ltrim($value, '/');

            if ($value !== '') {
                $variants[] = $value;
            }
        }

        return array_values(array_unique($variants));
    }

    /**
     * Locate the first candidate key that actually exists on the disk.
     *
     * @return string|null the resolved object key, or null when none exist
     */
    public static function resolveExistingKey(?string $storedPath): ?string
    {
        $disk = self::disk();

        foreach (self::candidateKeys($storedPath) as $key) {
            try {
                if ($disk->exists($key)) {
                    return $key;
                }
            } catch (Throwable $e) {
                // A disk-level failure is reported by the caller, not here.
                Log::warning('Private file existence check failed.', [
                    'key_hash' => hash('sha256', $key),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * Stream a private file, translating every failure into a controlled error.
     *
     * @param  string  $kind      'evidence' | 'proof'
     * @param  string|null  $storedPath  the database path/key
     * @param  string|null  $downloadName  the name presented to the client
     * @param  array<string, mixed>  $logContext  extra safe context for logging
     *
     * @throws HttpException 404 with a friendly message when the file is absent
     * @throws HttpException 503 with a friendly message when storage fails
     */
    public static function stream(
        string $kind,
        ?string $storedPath,
        ?string $downloadName = null,
        array $logContext = []
    ): StreamedResponse {
        $messages = self::MESSAGES[$kind] ?? self::MESSAGES['evidence'];

        // 1. No stored key at all -> controlled "not found".
        if (trim((string) $storedPath) === '') {
            Log::warning('Private file has no stored path.', $logContext + ['kind' => $kind]);

            throw new HttpException(404, $messages['missing']);
        }

        // 2. Key present but the object is absent -> controlled "not found".
        $key = self::resolveExistingKey($storedPath);

        if ($key === null) {
            Log::warning('Private file object is missing from storage.', $logContext + [
                'kind' => $kind,
                // Log a HASH, never the key itself — the key is an internal
                // storage detail and must not leak into logs.
                'key_hash' => hash('sha256', (string) $storedPath),
            ]);

            throw new HttpException(404, $messages['missing']);
        }

        // 3. Stream. A disk failure becomes a controlled error, not a stack trace.
        try {
            return self::disk()->response(
                $key,
                $downloadName ?: basename($key)
            );
        } catch (Throwable $e) {
            Log::error('Private file could not be streamed.', $logContext + [
                'kind' => $kind,
                'key_hash' => hash('sha256', $key),
                'error' => $e->getMessage(),
            ]);

            throw new HttpException(503, $messages['unavailable']);
        }
    }
}
