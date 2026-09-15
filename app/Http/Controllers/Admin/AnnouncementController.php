<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use App\Models\AdminNotification;
use App\Services\AnnouncementAttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::with(['adminInfo', 'attachments'])
            ->latest('created_at')
            ->paginate(10);

        $admin = Auth::guard('admin')->user();
        /** @var Admin|null $admin */
        $admin = $admin instanceof Admin ? $admin : null;

        $myAnnouncements = Announcement::with(['adminInfo', 'attachments'])
            ->where('admin_id', $admin?->getKey())
            ->latest('created_at')
            ->paginate(10, ['*'], 'my_page');

        // Audience-scoped: an unscoped count here would badge the Super Admin
        // with operational notifications they are not the audience for.
        $unreadNotificationCount = AdminNotification::query()
            ->forAudience(
                $admin?->getKey() !== null ? (int) $admin->getKey() : null,
                $admin instanceof Admin && $admin->isItAdministrator()
            )
            ->where('is_read', false)
            ->count();
        $announcementStats = [
            'total' => Announcement::count(),
            'with_attachments' => Announcement::whereNotNull('attachment_path')
                ->orWhereHas('attachments')
                ->count(),
        ];

        return view(
            'admin.announcements.index',
            compact('announcements', 'myAnnouncements', 'unreadNotificationCount', 'announcementStats')
        );
    }

    public function create()
    {
        return view('admin.announcements.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'attachments' => ['nullable', 'array', 'max:' . AnnouncementAttachmentService::MAX_ATTACHMENTS],
            'attachments.*' => $this->attachmentFileRules(),
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,pdf', 'max:5120'],
            'show_on_login' => ['nullable', 'boolean'],
            'login_modal_duration' => ['nullable', 'in:1_week,1_month,custom'],
            'login_modal_expires_at' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $admin = Auth::guard('admin')->user();
        /** @var Admin|null $admin */
        $admin = $admin instanceof Admin ? $admin : null;
        $canPublishLoginModal = $admin?->isItAdministrator() ?? false;

        $showOnLogin = $canPublishLoginModal && $request->boolean('show_on_login');

        $announcement = new Announcement();
        $announcement->forceFill([
            'admin_id' => $admin?->getKey(),
            'title' => $request->input('title'),
            'content' => app(\App\Services\AnnouncementContentSanitizer::class)->sanitize($request->input('content')),
            'attachment_path' => null,
            'show_on_login' => $showOnLogin,
            'login_modal_expires_at' => $showOnLogin
                ? $this->resolveLoginModalExpiry($request)
                : null,
        ]);
        $announcement->save();

        // Store the new secure multi-attachments.
        $service = app(AnnouncementAttachmentService::class);
        $this->storeUploadedFiles($service, $announcement, $request->file('attachments', []), $admin, $request);

        // Notify students about the new announcement (target-aware).
        $this->notifyStudentsOfAnnouncement($announcement);

        return redirect()
            ->route('admin.announcements')
            ->with('success', 'Announcement published successfully.');
    }

    /**
     * Send a target-aware notification to every active student when a new
     * announcement is published.
     */
    protected function notifyStudentsOfAnnouncement($announcement): void
    {
        try {
            $students = \App\Models\User::query()
                ->whereHas('role', fn ($q) => $q->where('roles_name', 'Student'))
                ->get(['student_number']);

            foreach ($students as $student) {
                if (! $student->student_number) {
                    continue;
                }

                try {
                    app(\App\Services\StudentNotificationService::class)->create(
                        studentNumber: $student->student_number,
                        type: 'announcement',
                        title: 'New Announcement',
                        message: $announcement->title,
                        targetType: 'announcement',
                        targetId: (int) $announcement->announcement_id,
                    );
                } catch (\Throwable $e) {
                    // Continue notifying remaining students.
                }
            }
        } catch (\Throwable $e) {
            // Notification must never break announcement publishing.
        }
    }

    public function edit(Announcement $announcement)
    {
        $this->authorizeAnnouncementOwner($announcement);

        $announcement->load('attachments');

        return view('admin.announcements.edit', compact('announcement'));
    }

    public function update(Request $request, Announcement $announcement)
    {
        $this->authorizeAnnouncementOwner($announcement);

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'attachments' => ['nullable', 'array', 'max:' . AnnouncementAttachmentService::MAX_ATTACHMENTS],
            'attachments.*' => $this->attachmentFileRules(),
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,pdf', 'max:5120'],
            'remove_attachment' => ['nullable', 'boolean'],
            'show_on_login' => ['nullable', 'boolean'],
            'login_modal_duration' => ['nullable', 'in:1_week,1_month,custom'],
            'login_modal_expires_at' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $admin = Auth::guard('admin')->user();
        /** @var Admin|null $admin */
        $admin = $admin instanceof Admin ? $admin : null;
        $canPublishLoginModal = $admin?->isItAdministrator() ?? false;

        $showOnLogin = $canPublishLoginModal && $request->boolean('show_on_login');

        // Legacy single-attachment handling (backward compatible with the old
        // public/uploads/announcements flow used by pre-existing rows).
        $attachmentPath = $announcement->getAttribute('attachment_path');

        if ($request->has('remove_attachment') && $request->boolean('remove_attachment')) {
            $this->deleteLegacyAttachment($attachmentPath);
            $attachmentPath = null;
        }

        if ($request->hasFile('attachment')) {
            $this->deleteLegacyAttachment($attachmentPath);
            $attachmentPath = $this->saveLegacyAttachment($request);
        }

        $announcement->forceFill([
            'title' => $request->input('title'),
            'content' => app(\App\Services\AnnouncementContentSanitizer::class)->sanitize($request->input('content')),
            'attachment_path' => $attachmentPath,
            'show_on_login' => $showOnLogin,
            'login_modal_expires_at' => $showOnLogin
                ? $this->resolveLoginModalExpiry($request, $announcement)
                : null,
        ]);
        $announcement->save();

        // Store any newly-added secure attachments.
        $service = app(AnnouncementAttachmentService::class);
        $this->storeUploadedFiles($service, $announcement, $request->file('attachments', []), $admin, $request);

        return redirect()
            ->route('admin.announcements')
            ->with('success', 'Announcement updated successfully.');
    }

    public function destroy(Announcement $announcement)
    {
        $this->authorizeAnnouncementOwner($announcement);

        // Remove secure attachments (objects + metadata + audit).
        app(AnnouncementAttachmentService::class)->deleteAnnouncementAttachments(
            $announcement,
            $this->currentAdmin(),
            request(),
        );

        // Remove the legacy single attachment file, if any.
        $this->deleteLegacyAttachment($announcement->getAttribute('attachment_path'));

        $announcement->delete();

        return redirect()
            ->route('admin.announcements')
            ->with('success', 'Announcement deleted successfully.');
    }

    public function show(Announcement $announcement)
    {
        $announcement->load('attachments');

        return view('admin.announcements.show', compact('announcement'));
    }

    /**
     * Add one or more attachments to an existing announcement.
     */
    public function storeAttachment(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->authorizeAnnouncementOwner($announcement);

        $request->validate([
            'attachments' => ['required', 'array', 'max:' . AnnouncementAttachmentService::MAX_ATTACHMENTS],
            'attachments.*' => $this->attachmentFileRules(),
        ]);

        $admin = $this->currentAdmin();
        $service = app(AnnouncementAttachmentService::class);

        $stored = $this->storeUploadedFiles($service, $announcement, $request->file('attachments', []), $admin, $request);

        if ($stored === 0) {
            return back()->with('error', 'No attachment was uploaded. Check the file type and size.');
        }

        return back()->with('success', $stored . ' attachment(s) added successfully.');
    }

    /**
     * Delete a single secure attachment.
     */
    public function destroyAttachment(AnnouncementAttachment $attachment): RedirectResponse
    {
        $announcement = $attachment->announcement;

        if (! $announcement) {
            abort(404, 'Attachment does not belong to a valid announcement.');
        }

        $this->authorizeAnnouncementOwner($announcement);

        $deleted = app(AnnouncementAttachmentService::class)->deleteAttachment(
            $attachment,
            $this->currentAdmin(),
            request(),
        );

        if (! $deleted) {
            return back()->with('error', 'Unable to remove the attachment. Please try again.');
        }

        return back()->with('success', 'Attachment removed successfully.');
    }

    // ------------------------------------------------------------------
    // Secure attachment helpers
    // ------------------------------------------------------------------

    protected function attachmentFileRules(): array
    {
        return app(AnnouncementAttachmentService::class)->singleFileRules();
    }

    /**
     * Store the uploaded secure attachment files (multiple). Returns count.
     *
     * @param  \Illuminate\Http\UploadedFile[]  $files
     */
    protected function storeUploadedFiles(
        AnnouncementAttachmentService $service,
        Announcement $announcement,
        array $files,
        ?Admin $admin,
        ?Request $request,
    ): int {
        $stored = 0;

        foreach ($files as $file) {
            if (! $file instanceof \Illuminate\Http\UploadedFile) {
                continue;
            }

            $attachment = $service->storeUploaded($announcement, $file, $admin, $request);

            if ($attachment) {
                $stored++;
            }
        }

        return $stored;
    }

    protected function currentAdmin(): ?Admin
    {
        $admin = Auth::guard('admin')->user();

        return $admin instanceof Admin ? $admin : null;
    }

    /**
     * Any logged-in admin may post a new announcement, but only the admin who
     * created an announcement (or the IT Administrator) may edit/delete it.
     */
    protected function authorizeAnnouncementOwner(Announcement $announcement): void
    {
        $admin = Auth::guard('admin')->user();
        /** @var Admin|null $admin */
        $admin = $admin instanceof Admin ? $admin : null;

        if (! $admin) {
            abort(403, 'Unauthenticated.');
        }

        if ($admin->isItAdministrator()) {
            return;
        }

        if ((int) $announcement->admin_id !== (int) $admin->getKey()) {
            abort(403, 'You can only edit or delete announcements you created.');
        }
    }

    /**
     * Resolve the expiry timestamp for a login-modal announcement.
     *
     * Duration choices: 1 Week, 1 Month, or a custom date.
     * When no expiry is supplied (e.g. legacy records with only show_on_login),
     * defaults to 1 week so the modal never stays up indefinitely.
     */
    protected function resolveLoginModalExpiry(Request $request, ?Announcement $current = null): \Illuminate\Support\Carbon
    {
        $duration = (string) $request->input('login_modal_duration');

        if ($duration === 'custom' && $request->filled('login_modal_expires_at')) {
            return \Illuminate\Support\Carbon::parse($request->input('login_modal_expires_at'))->endOfDay();
        }

        if ($duration === '1_month') {
            return \Illuminate\Support\Carbon::now()->addMonth()->endOfDay();
        }

        if ($duration === '1_week') {
            return \Illuminate\Support\Carbon::now()->addWeek()->endOfDay();
        }

        // Fallback: preserve the existing expiry if one is set.
        if ($current && $current->login_modal_expires_at) {
            return $current->login_modal_expires_at;
        }

        return \Illuminate\Support\Carbon::now()->addWeek()->endOfDay();
    }

    // ------------------------------------------------------------------
    // Legacy single-attachment helpers (kept for backward compatibility
    // with announcements that predate the secure attachment system)
    // ------------------------------------------------------------------

    /**
     * Save a legacy single attachment to public/uploads/announcements.
     */
    protected function saveLegacyAttachment(Request $request): ?string
    {
        if (! $request->hasFile('attachment')) {
            return null;
        }

        $file = $request->file('attachment');
        $directory = public_path('uploads/announcements');

        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filename = uniqid('announcement_', true) . '.' . $file->getClientOriginalExtension();
        $file->move($directory, $filename);

        return "uploads/announcements/{$filename}";
    }

    /**
     * Delete a legacy single attachment file, if present.
     */
    protected function deleteLegacyAttachment(?string $path): void
    {
        if (! $path) {
            return;
        }

        $filePath = public_path($path);

        if (File::exists($filePath)) {
            File::delete($filePath);
        }
    }
}
