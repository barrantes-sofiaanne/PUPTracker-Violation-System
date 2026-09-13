<?php

namespace App\Http\Controllers;

use App\Models\AnnouncementAttachment;
use App\Models\Announcement;
use App\Services\AuditLogService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Authorized access to announcement attachments.
 *
 * This is the single controlled endpoint through which every role (student,
 * security, admin) downloads/views an announcement attachment. Files are kept
 * on a PRIVATE disk; they are never served from the public web root and there
 * are no permanent direct object-storage URLs.
 *
 * Authorization is enforced server-side on every request (never by hiding the
 * button):
 *   - the user must be authenticated on one of the app guards;
 *   - the attachment must belong to a valid announcement;
 *   - announcements are global/published — every authenticated student/security/
 *     admin may read them (there is no draft/archived/restricted status in the
 *     current announcements_tbl schema; all rows are published).
 *
 * Every access is recorded in audit_logs (ATTACHMENT_VIEWED / DOWNLOADED) with
 * the actor + IP + user agent where available. Direct URL/id tampering cannot
 * bypass the announcement lookup (the row is fetched through its announcement).
 */
class AnnouncementAttachmentController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService,
    ) {}

    /**
     * Stream an attachment (inline view). Authorized for any app user.
     *
     * @return StreamedResponse
     */
    public function show(AnnouncementAttachment $attachment, Request $request): StreamedResponse
    {
        [$actorType, $actorId] = $this->resolveActor();

        if (! $actorType) {
            $this->auditDenied($attachment, 'unauthenticated', null, $request);
            abort(403, 'Authentication required.');
        }

        // Every access path must validate through the announcement. This also
        // prevents id-tampering from reaching an attachment on an announcement
        // the user is not allowed to see.
        $announcement = $attachment->announcement;

        if (! $announcement) {
            $this->auditDenied($attachment, $actorType, $actorId, $request);
            abort(404, 'Attachment does not belong to an announcement.');
        }

        if (! $this->canAccess($actorType)) {
            $this->auditDenied($attachment, $actorType, $actorId, $request);
            abort(403, 'You are not allowed to access this attachment.');
        }

        return $this->stream($attachment, $actorType, $actorId, $request, 'inline');
    }

    /**
     * Force a download of an attachment. Authorized for any app user.
     *
     * @return StreamedResponse
     */
    public function download(AnnouncementAttachment $attachment, Request $request): StreamedResponse
    {
        [$actorType, $actorId] = $this->resolveActor();

        if (! $actorType) {
            $this->auditDenied($attachment, 'unauthenticated', null, $request);
            abort(403, 'Authentication required.');
        }

        $announcement = $attachment->announcement;

        if (! $announcement) {
            $this->auditDenied($attachment, $actorType, $actorId, $request);
            abort(404, 'Attachment does not belong to an announcement.');
        }

        if (! $this->canAccess($actorType)) {
            $this->auditDenied($attachment, $actorType, $actorId, $request);
            abort(403, 'You are not allowed to access this attachment.');
        }

        return $this->stream($attachment, $actorType, $actorId, $request, 'attachment');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Announcements are global/published: any authenticated student, security
     * account, or admin may read them. (The schema has no draft/archive flag.)
     */
    protected function canAccess(string $actorType): bool
    {
        return in_array($actorType, ['student', 'security', 'admin'], true);
    }

    /**
     * Stream the file from the private disk that actually holds it.
     *
     * ROOT CAUSE NOTE (broken inline images)
     * -------------------------------------
     * This originally used the configured announcements disk
     * (`filesystems.announcements_disk`) for BOTH the existence check and the
     * read. When that configuration is wrong — e.g. ANNOUNCEMENT_STORAGE_DISK=s3
     * with blank AWS_* credentials — the S3 adapter throws
     * `UnableToCheckFileExistence`, which is NOT caught here and surfaced to the
     * browser as an HTTP 500. An <img> receiving a 500 with no image bytes shows
     * a broken-image icon, while the attachment row (name + Download link) still
     * renders because it is built from database metadata alone.
     *
     * We now resolve the disk that genuinely holds the object: the disk recorded
     * on the attachment row when it exists and is readable, otherwise the
     * configured announcements disk, otherwise the private local disk. The row
     * is an administrative record, so we only trust it if the file really
     * resolves there — and every candidate must still pass the existing
     * authorization checks above (which are unchanged).
     */
    protected function stream(
        AnnouncementAttachment $attachment,
        string $actorType,
        int $actorId,
        Request $request,
        string $disposition,
    ): StreamedResponse {
        if (! $attachment->storage_path) {
            Log::warning('Announcement attachment has no storage path', [
                'attachment_id' => $attachment->attachment_id,
            ]);
            abort(404, 'Attachment file not found.');
        }

        $resolved = $this->resolveReadableDisk($attachment);

        if ($resolved === null) {
            Log::warning('Announcement attachment missing from every configured disk', [
                'attachment_id' => $attachment->attachment_id,
                'storage_path' => $attachment->storage_path,
                'row_disk' => $attachment->storage_disk,
                'configured_disk' => config('filesystems.announcements_disk', 'local'),
            ]);
            abort(404, 'Attachment file not found.');
        }

        [$disk, $diskName] = $resolved;

        $this->auditAccess(
            $disposition === 'attachment' ? 'ATTACHMENT_DOWNLOADED' : 'ATTACHMENT_VIEWED',
            $attachment,
            $actorType,
            $actorId,
            $request,
        );

        $filename = $attachment->original_filename ?: basename($attachment->storage_path);

        // Resolve a concrete Content-Type. Passing an empty Content-Type makes
        // browsers sniff the body, and with `X-Content-Type-Options: nosniff`
        // that combination can refuse to paint the image. We prefer the
        // authoritative live sniff of the stored bytes and fall back to the
        // MIME recorded at upload (which is itself validated against an
        // allow-list). The real type is never hard-coded.
        $contentType = $this->resolveContentType($disk, $diskName, $attachment);

        $headers = array_filter([
            'Content-Type' => $contentType,
        ]);

        /** @var \Illuminate\Filesystem\FilesystemAdapter $diskAdapter */
        $diskAdapter = $disk;

        return $diskAdapter->response(
            $attachment->storage_path,
            $filename,
            $headers,
            $disposition,
        );
    }

    /**
     * Find the first disk that actually holds this attachment's object.
     *
     * Candidates, in order:
     *   1. the disk recorded on the attachment row (when it resolves);
     *   2. the configured announcements disk;
     *   3. the private local disk (where files land when the disk is unset).
     *
     * Every candidate is probed defensively: a storage/adapter failure (for
     * example an unreachable bucket) is logged and skipped rather than allowed
     * to bubble up as a 500 on an <img> request.
     *
     * @return array{0: Filesystem, 1: string}|null  [disk, disk name]
     */
    protected function resolveReadableDisk(AnnouncementAttachment $attachment): ?array
    {
        $rowDisk = is_string($attachment->storage_disk) ? trim($attachment->storage_disk) : '';
        $configuredDisk = (string) config('filesystems.announcements_disk', 'local');

        $candidates = [];

        if ($rowDisk !== '') {
            $candidates[] = $rowDisk;
        }

        if ($configuredDisk !== '') {
            $candidates[] = $configuredDisk;
        }

        // Private local disk: the default when ANNOUNCEMENT_STORAGE_DISK is unset,
        // and where existing local attachments physically live.
        $candidates[] = 'local';
        $candidates[] = 'unknown_disk';

        foreach (array_unique($candidates) as $diskName) {
            // Only consider disks that are actually defined; `disk()` would
            // otherwise raise an InvalidArgumentException for a stale row value.
            if (! is_array(config("filesystems.disks.{$diskName}"))) {
                continue;
            }

            try {
                $disk = Storage::disk($diskName);

                if ($disk->exists($attachment->storage_path)) {
                    return [$disk, $diskName];
                }
            } catch (\Throwable $e) {
                // A misconfigured or unreachable disk must not break the request.
                Log::warning('Announcement attachment disk probe failed', [
                    'attachment_id' => $attachment->attachment_id,
                    'disk' => $diskName,
                    'storage_path' => $attachment->storage_path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * Determine the Content-Type to serve for an attachment.
     *
     * Prefers the live MIME sniff of the stored object (authoritative for the
     * bytes actually being returned) and falls back to the MIME validated at
     * upload time. Never hard-codes a single image type.
     */
    protected function resolveContentType(
        Filesystem $disk,
        string $diskName,
        AnnouncementAttachment $attachment,
    ): ?string {
        // The recorded MIME was sniffed server-side at upload and checked
        // against the allow-list, so it is a sound fallback.
        $recorded = trim((string) $attachment->mime_type);

        try {
            $detected = $disk->mimeType($attachment->storage_path);

            if (is_string($detected) && $detected !== '') {
                return $detected;
            }
        } catch (\Throwable $e) {
            Log::debug('Announcement attachment MIME detection failed; using recorded type', [
                'attachment_id' => $attachment->attachment_id,
                'disk' => $diskName,
                'error' => $e->getMessage(),
            ]);
        }

        return $recorded !== '' ? $recorded : null;
    }

    /**
     * Resolve [type, id] of the current authenticated user (any guard).
     */
    protected function resolveActor(): array
    {
        foreach (['student', 'security', 'admin'] as $guard) {
            if (auth($guard)->check()) {
                $user = auth($guard)->user();
                $id = (int) ($guard === 'student'
                    ? ($user->user_id ?? $user->getAuthIdentifier())
                    : $user->getAuthIdentifier());

                return [$guard, $id];
            }
        }

        return [null, null];
    }

    /**
     * Record a successful view/download in audit_logs.
     */
    protected function auditAccess(
        string $action,
        AnnouncementAttachment $attachment,
        string $actorType,
        int $actorId,
        Request $request,
    ): void {
        try {
            $this->auditLogService->audit([
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'action' => $action,
                'module' => 'Announcement',
                'description' => sprintf(
                    '%s announcement attachment "%s" (attachment #%d) for announcement #%d.',
                    $action === 'ATTACHMENT_DOWNLOADED' ? 'Downloaded' : 'Viewed',
                    $attachment->original_filename ?: 'untitled',
                    $attachment->attachment_id,
                    $attachment->announcement_id,
                ),
            ], $request);
        } catch (\Throwable $e) {
            Log::warning('Unable to audit announcement attachment access', [
                'action' => $action,
                'attachment_id' => $attachment->attachment_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Record an ATTACHMENT_ACCESS_DENIED audit entry.
     */
    protected function auditDenied(
        AnnouncementAttachment $attachment,
        string $actorType,
        ?int $actorId,
        Request $request,
    ): void {
        try {
            $this->auditLogService->audit([
                'actor_type' => $actorType ?: 'unknown',
                'actor_id' => $actorId,
                'action' => 'ATTACHMENT_ACCESS_DENIED',
                'module' => 'Announcement',
                'description' => sprintf(
                    'Denied access to announcement attachment "%s" (attachment #%d, announcement #%s).',
                    $attachment->original_filename ?: 'untitled',
                    $attachment->attachment_id,
                    $attachment->announcement_id ?? 'unknown',
                ),
            ], $request);
        } catch (\Throwable $e) {
            Log::warning('Unable to audit announcement attachment denial', [
                'attachment_id' => $attachment->attachment_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
