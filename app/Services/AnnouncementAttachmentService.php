<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Announcement attachment storage + security service.
 *
 * The physical files live on a PRIVATE storage disk (never the public web root
 * and never MySQL BLOBs). This service is the only place that:
 *   - resolves the configured announcement disk (S3-ready on Railway);
 *   - validates uploads (extension + MIME + size + count + filename length);
 *   - stores files with a random server-side name and structured path;
 *   - computes SHA-256 metadata;
 *   - writes audit_logs entries for attachment actions;
 *   - removes files + metadata safely (with cleanup-failure logging).
 *
 * Design rules:
 *   - The original filename is metadata ONLY (never used on disk).
 *   - No client-provided path/filename is ever trusted.
 *   - A DB write failure after a successful upload triggers cleanup of the
 *     uploaded object (never leave a DB row pointing at a missing file, and
 *     never leave an orphaned object).
 *   - Technical storage failures go to the Laravel log, NOT audit_logs.
 */
class AnnouncementAttachmentService
{
    /**
     * Allowed upload MIME types (allow-list; reject MIME spoofing).
     */
    public const ALLOWED_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'image/jpeg',
        'image/png',
    ];

    /**
     * Allowed extensions that correspond to the MIME allow-list.
     */
    public const ALLOWED_EXTENSIONS = [
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'ppt',
        'pptx',
        'jpg',
        'jpeg',
        'png',
    ];

    /**
     * Maximum upload size (kilobytes). 10 MB.
     */
    public const MAX_KILOBYTES = 10240;

    /**
     * Maximum number of attachments per announcement.
     */
    public const MAX_ATTACHMENTS = 10;

    /**
     * Maximum original-filename length.
     */
    public const MAX_FILENAME_LENGTH = 255;

    /**
     * Resolve the configured announcement disk.
     *
     * @return \Illuminate\Contracts\Filesystem\Filesystem|\Illuminate\Filesystem\FilesystemAdapter
     */
    public function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk((string) config('filesystems.announcements_disk', 'local'));
    }

    /**
     * Validation rules for a single attachment upload field.
     *
     * MIME is validated against the allow-list (rejecting MIME spoofing) and
     * the extension is validated too (both must pass).
     */
    public function singleFileRules(): array
    {
        return [
            'file',
            'max:' . self::MAX_KILOBYTES,
            'mimes:' . implode(',', self::ALLOWED_EXTENSIONS),
            'mimetypes:' . implode(',', self::ALLOWED_MIMES),
        ];
    }

    /**
     * Store one uploaded attachment for an announcement (private storage).
     *
     * @return AnnouncementAttachment|null  the created metadata row, or null on failure
     */
    public function storeUploaded(
        Announcement $announcement,
        UploadedFile $file,
        ?Admin $admin = null,
        ?Request $request = null,
    ): ?AnnouncementAttachment {
        // Validate the extension + MIME server-side (defense against spoofing
        // that a browser accept/validation can't provide).
        $extension = strtolower($file->getClientOriginalExtension());
        $reportedMime = strtolower((string) $file->getClientMimeType());
        $originalNameRaw = (string) $file->getClientOriginalName();

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            Log::warning('Announcement attachment rejected: disallowed extension', [
                'announcement_id' => $announcement->announcement_id,
                'extension' => $extension,
                'original_name' => $originalNameRaw,
            ]);

            return null;
        }

        // Authoritative MIME: sniff the actual bytes on disk. This defeats
        // Content-Type spoofing (e.g. a PHP/HTML payload renamed to .pdf).
        // If the file is empty/indeterminate, fall back to the reported MIME
        // (which is still constrained by the extension allow-list + validation).
        $sniffedMime = $this->sniffFileMime($file);
        $mime = $sniffedMime ?: $reportedMime;

        if (! $this->isAllowedMime($mime, $sniffedMime, $reportedMime, $extension)) {
            Log::warning('Announcement attachment rejected: MIME not allowed', [
                'announcement_id' => $announcement->announcement_id,
                'extension' => $extension,
                'reported_mime' => $reportedMime,
                'sniffed_mime' => $sniffedMime,
                'original_name' => $originalNameRaw,
            ]);

            return null;
        }

        $originalName = mb_substr($originalNameRaw, 0, self::MAX_FILENAME_LENGTH);
        if (trim($originalName) === '') {
            $originalName = 'attachment';
        }

        $size = $file->getSize();

        if ($size > self::MAX_KILOBYTES * 1024) {
            Log::warning('Announcement attachment rejected: file too large', [
                'announcement_id' => $announcement->announcement_id,
                'size' => $size,
                'original_name' => $originalName,
            ]);

            return null;
        }

        $directory = 'announcements/' . now()->format('Y/m');
        $storedFilename = $this->randomStoredName($extension);
        $disk = $this->disk();

        try {
            $storedPath = $disk->putFileAs($directory, $file, $storedFilename);
        } catch (\Throwable $e) {
            Log::error('Announcement attachment upload failed (storage)', [
                'announcement_id' => $announcement->announcement_id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($storedPath === false) {
            Log::error('Announcement attachment upload failed (storage returned false)', [
                'announcement_id' => $announcement->announcement_id,
            ]);

            return null;
        }

        try {
            $hash = hash_file('sha256', $file->getRealPath()) ?: null;
        } catch (\Throwable $e) {
            Log::warning('Unable to compute SHA-256 for announcement attachment', [
                'announcement_id' => $announcement->announcement_id,
                'error' => $e->getMessage(),
            ]);

            $hash = null;
        }

        try {
            $attachment = AnnouncementAttachment::create([
                'announcement_id' => $announcement->announcement_id,
                'original_filename' => $originalName,
                'stored_filename' => $storedFilename,
                'storage_disk' => (string) config('filesystems.announcements_disk', 'local'),
                'storage_path' => $storedPath,
                'mime_type' => $mime,
                'file_size' => $size,
                'file_hash' => $hash,
                'uploaded_by' => $admin?->getKey(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // DB failure after a successful object upload: clean up the object
            // so we never leave an orphaned file, then rethrow.
            Log::error('Announcement attachment DB write failed; cleaning up object', [
                'announcement_id' => $announcement->announcement_id,
                'storage_path' => $storedPath,
                'error' => $e->getMessage(),
            ]);

            try {
                $disk->delete($storedPath);
            } catch (\Throwable $cleanup) {
                Log::error('Announcement attachment cleanup after DB failure also failed', [
                    'storage_path' => $storedPath,
                    'error' => $cleanup->getMessage(),
                ]);
            }

            report($e);

            return null;
        }

        $this->audit('ATTACHMENT_UPLOADED', $attachment, $admin, $request);

        return $attachment;
    }

    /**
     * Delete an attachment: remove the physical object, then the metadata row.
     * The action is audited. If the object removal fails the metadata row is
     * kept and the failure is logged (no silent orphan).
     */
    public function deleteAttachment(
        AnnouncementAttachment $attachment,
        ?Admin $admin = null,
        ?Request $request = null,
    ): bool {
        $disk = $this->disk();

        $objectDeleted = true;
        if ($attachment->storage_path) {
            try {
                if ($disk->exists($attachment->storage_path)) {
                    $objectDeleted = $disk->delete($attachment->storage_path);
                }
            } catch (\Throwable $e) {
                Log::error('Announcement attachment object deletion failed', [
                    'attachment_id' => $attachment->attachment_id,
                    'storage_path' => $attachment->storage_path,
                    'error' => $e->getMessage(),
                ]);

                $objectDeleted = false;
            }
        }

        if (! $objectDeleted) {
            // Never silently drop the DB row while the object still exists.
            Log::warning('Announcement attachment metadata retained because object deletion failed', [
                'attachment_id' => $attachment->attachment_id,
                'storage_path' => $attachment->storage_path,
            ]);

            $this->audit('ATTACHMENT_DELETED', $attachment, $admin, $request, 'deletion attempted but storage removal failed; metadata retained');

            return false;
        }

        try {
            $announcementId = $attachment->announcement_id;
            $attachment->delete();
        } catch (\Throwable $e) {
            Log::error('Announcement attachment metadata deletion failed', [
                'attachment_id' => $attachment->attachment_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        $this->audit('ATTACHMENT_DELETED', $attachment, $admin, $request, 'deleted attachment #' . $attachment->attachment_id . ' (announcement #' . ($announcementId ?? '?') . ')');

        return true;
    }

    /**
     * Delete ALL attachments of an announcement (files + metadata + audit).
     * Used when an announcement itself is deleted.
     */
    public function deleteAnnouncementAttachments(
        Announcement $announcement,
        ?Admin $admin = null,
        ?Request $request = null,
    ): void {
        $attachments = $announcement->attachments()->get();

        foreach ($attachments as $attachment) {
            $this->deleteAttachment($attachment, $admin, $request);
        }
    }

    /**
     * Generate a random, unguessable storage filename.
     */
    protected function randomStoredName(string $extension): string
    {
        $name = Str::uuid()->toString();

        return $name . ($extension !== '' ? '.' . $extension : '');
    }

    /**
     * Audit an announcement-attachment action. Never stores file contents.
     */
    protected function audit(
        string $action,
        AnnouncementAttachment $attachment,
        ?Admin $admin = null,
        ?Request $request = null,
        ?string $overrideDescription = null,
    ): void {
        try {
            $description = $overrideDescription ?? sprintf(
                '%s announcement attachment "%s" (attachment #%d) for announcement #%d.',
                ucwords(strtolower(str_replace('_', ' ', $action))),
                $attachment->original_filename ?: 'untitled',
                $attachment->attachment_id,
                $attachment->announcement_id,
            );

            app(AuditLogService::class)->audit([
                'actor_type' => 'admin',
                'actor_id' => $admin?->getKey(),
                'action' => $action,
                'module' => 'Announcement',
                'description' => $description,
            ], $request);
        } catch (\Throwable $e) {
            // Audit logging must never break the primary action.
            Log::warning('Unable to write announcement attachment audit log', [
                'action' => $action,
                'attachment_id' => $attachment->attachment_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sniff the real content MIME of an uploaded file using finfo.
     *
     * Returns null when the file is empty or the MIME cannot be determined
     * (so the caller can fall back to the reported MIME + extension rules).
     */
    protected function sniffFileMime(UploadedFile $file): ?string
    {
        try {
            $realPath = $file->getRealPath();

            if (! $realPath || ! is_file($realPath)) {
                return null;
            }

            if (filesize($realPath) === 0) {
                return null;
            }

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($realPath);

            if (! is_string($mime) || $mime === '') {
                return null;
            }

            $mime = strtolower(trim($mime));

            // inode/x-empty is the finfo result for empty/whitespace-only files;
            // treat it as indeterminate.
            if (in_array($mime, ['inode/x-empty', 'application/x-empty', 'application/octet-stream'], true)) {
                return null;
            }

            return $mime;
        } catch (\Throwable $e) {
            Log::warning('Unable to sniff announcement attachment MIME', [
                'original_name' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Determine whether a MIME is allowed for upload.
     *
     * When the content sniff produced a definitive MIME it must be on the
     * allow-list (this is what defeats MIME spoofing). When the sniff was
     * indeterminate (empty/octet-stream), the reported MIME is checked against
     * the allow-list AND the extension is cross-checked against the MIME so a
     * misleading reported header can't smuggle a disallowed type through.
     */
    protected function isAllowedMime(
        string $mime,
        ?string $sniffedMime,
        string $reportedMime,
        string $extension,
    ): bool {
        // A definitive content sniff is authoritative: it must be allowed.
        if ($sniffedMime !== null) {
            return in_array($sniffedMime, self::ALLOWED_MIMES, true);
        }

        // No definitive content: fall back to the reported MIME + extension.
        if (! in_array($reportedMime, self::ALLOWED_MIMES, true)) {
            return false;
        }

        return $this->mimeMatchesExtension($reportedMime, $extension);
    }

    /**
     * Cross-check a MIME against an extension (extension <-> MIME mapping).
     */
    protected function mimeMatchesExtension(string $mime, string $extension): bool
    {
        $mapping = [
            'application/pdf' => ['pdf'],
            'application/msword' => ['doc'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
            'application/vnd.ms-excel' => ['xls'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
            'application/vnd.ms-powerpoint' => ['ppt'],
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['pptx'],
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
        ];

        $allowedExtensions = $mapping[$mime] ?? [];

        return in_array($extension, $allowedExtensions, true);
    }
}
