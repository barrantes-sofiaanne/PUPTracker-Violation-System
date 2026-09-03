<?php

namespace App\Http\Controllers;

use App\Models\AnnouncementAttachment;
use App\Models\Announcement;
use App\Services\AnnouncementAttachmentService;
use App\Services\AuditLogService;
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
        private AnnouncementAttachmentService $attachmentService,
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
     * Stream the file from the configured private disk.
     */
    protected function stream(
        AnnouncementAttachment $attachment,
        string $actorType,
        int $actorId,
        Request $request,
        string $disposition,
    ): StreamedResponse {
        $disk = $this->attachmentService->disk();

        if (! $attachment->storage_path || ! $disk->exists($attachment->storage_path)) {
            Log::warning('Announcement attachment missing from storage', [
                'attachment_id' => $attachment->attachment_id,
                'storage_path' => $attachment->storage_path,
            ]);
            abort(404, 'Attachment file not found.');
        }

        $this->auditAccess(
            $disposition === 'attachment' ? 'ATTACHMENT_DOWNLOADED' : 'ATTACHMENT_VIEWED',
            $attachment,
            $actorType,
            $actorId,
            $request,
        );

        /** @var \Illuminate\Filesystem\FilesystemAdapter $diskAdapter */
        $diskAdapter = $disk;

        return $diskAdapter->response(
            $attachment->storage_path,
            $attachment->original_filename ?: basename((string) $attachment->storage_path),
            [],
            $disposition,
        );
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
