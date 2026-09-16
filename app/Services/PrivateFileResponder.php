<?php

namespace App\Services;

use App\Support\Storage\EvidenceDisk;
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
     * Delegates to {@see EvidenceDisk}, the single source of truth, so a view
     * endpoint can never read from a different disk than the upload wrote to —
     * including when a production deployment is misconfigured.
     *
     * The return type is the CONCRETE adapter, not the `Filesystem` contract:
     * `response()` (used to stream the file) is declared only on the adapter, so
     * narrowing here is what makes the streaming call type-safe. The cast is
     * safe because every Laravel filesystem driver this project configures
     * (local, s3) resolves to a FilesystemAdapter.
     *
     * @return FilesystemAdapter
     */
    public static function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = EvidenceDisk::disk();

        return $disk;
    }

    /**
     * Normalise a stored path/key.
     *
     * Delegates to {@see EvidenceDisk::candidateKeys()} so every caller applies
     * identical normalisation. Uploads write keys shaped
     * `violation-evidence/<id>/<file>`, but a stored value may arrive with a
     * leading slash, a duplicated separator, or a leading `storage/` segment
     * from an older format. Each of those would MISS on the disk while looking
     * correct in the database, so they are stripped here. The database row
     * itself is never rewritten.
     *
     * @return array<int, string> candidate keys, most-likely first
     */
    public static function candidateKeys(?string $storedPath): array
    {
        return EvidenceDisk::candidateKeys($storedPath);
    }

    /**
     * Locate the first candidate key that actually exists on the disk.
     *
     * @return string|null the resolved object key, or null when none exist
     */
    public static function resolveExistingKey(?string $storedPath): ?string
    {
        return EvidenceDisk::resolveExistingKey($storedPath);
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
            // Distinguish the failure modes so an operator can tell a missing
            // object from a wrong-disk configuration without seeing the key.
            $diagnosis = EvidenceDisk::diagnose($storedPath);

            Log::warning('Private file object is missing from storage.', $logContext + [
                'kind' => $kind,
                'case' => $diagnosis['case'],
                'meaning' => $diagnosis['meaning'],
                'configured_disk' => $diagnosis['configured_disk'],
                'driver' => $diagnosis['driver'],
                'disk_usable' => $diagnosis['disk_usable'],
                'found_on' => $diagnosis['found_on'],
                // Log a HASH, never the key itself - the key is an internal
                // storage detail and must not leak into logs.
                'key_hash' => $diagnosis['key_hash'],
            ]);

            throw new HttpException(404, $messages['missing']);
        }

        // 3. Stream. A disk failure becomes a controlled error, not a stack trace.
        $disk = self::disk();

        // `response()` lives on the concrete adapter, not on the `Filesystem`
        // contract, so a custom/third-party disk that does not implement it would
        // be a fatal error here. Fail with the controlled 503 instead, so the
        // user sees the friendly message and the operator gets a clear log line.
        if (! method_exists($disk, 'response')) {
            Log::error('Private file could not be streamed: the disk cannot produce a response.', $logContext + [
                'kind' => $kind,
                'disk' => EvidenceDisk::name(),
                'driver' => EvidenceDisk::driverOf(EvidenceDisk::name()),
                'key_hash' => hash('sha256', $key),
            ]);

            throw new HttpException(503, $messages['unavailable']);
        }

        try {
            return $disk->response(
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
