<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Announcement;
use App\Models\AdminNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

class AnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::with('adminInfo')
            ->latest('created_at')
            ->paginate(10);

        $admin = Auth::guard('admin')->user();
        /** @var \App\Models\Admin|null $admin */
        $admin = $admin instanceof Admin ? $admin : null;

        $myAnnouncements = Announcement::with('adminInfo')
            ->where('admin_id', $admin?->getKey())
            ->latest('created_at')
            ->paginate(10, ['*'], 'my_page');

        $unreadNotificationCount = AdminNotification::where('is_read', false)->count();
        $announcementStats = [
            'total' => Announcement::count(),
            'with_attachments' => Announcement::whereNotNull('attachment_path')->count(),
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
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,pdf', 'max:5120'],
            'show_on_login' => ['nullable', 'boolean'],
            'login_modal_duration' => ['nullable', 'in:1_week,1_month,custom'],
            'login_modal_expires_at' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $admin = Auth::guard('admin')->user();
        /** @var \App\Models\Admin|null $admin */
        $admin = $admin instanceof Admin ? $admin : null;
        $canPublishLoginModal = $admin?->isItAdministrator() ?? false;

        $showOnLogin = $canPublishLoginModal && $request->boolean('show_on_login');

        $announcement = new Announcement();
        $announcement->forceFill([
            'admin_id' => $admin?->getKey(),
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'attachment_path' => $this->saveAttachment($request),
            'show_on_login' => $showOnLogin,
            'login_modal_expires_at' => $showOnLogin
                ? $this->resolveLoginModalExpiry($request)
                : null,
        ]);
        $announcement->save();

        return redirect()
            ->route('admin.announcements')
            ->with('success', 'Announcement published successfully.');
    }

    public function edit(Announcement $announcement)
    {
        $this->authorizeAnnouncementOwner($announcement);

        return view('admin.announcements.edit', compact('announcement'));
    }

    public function update(Request $request, Announcement $announcement)
    {
        $this->authorizeAnnouncementOwner($announcement);

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,pdf', 'max:5120'],
            'remove_attachment' => ['nullable', 'boolean'],
            'show_on_login' => ['nullable', 'boolean'],
            'login_modal_duration' => ['nullable', 'in:1_week,1_month,custom'],
            'login_modal_expires_at' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $admin = Auth::guard('admin')->user();
        /** @var \App\Models\Admin|null $admin */
        $admin = $admin instanceof Admin ? $admin : null;
        $canPublishLoginModal = $admin?->isItAdministrator() ?? false;

        $showOnLogin = $canPublishLoginModal && $request->boolean('show_on_login');

        $attachmentPath = $announcement->getAttribute('attachment_path');

        if ($request->has('remove_attachment') && $request->boolean('remove_attachment')) {
            $this->deleteAttachment($attachmentPath);
            $attachmentPath = null;
        }

        if ($request->hasFile('attachment')) {
            $this->deleteAttachment($attachmentPath);
            $attachmentPath = $this->saveAttachment($request);
        }

        $announcement->forceFill([
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'attachment_path' => $attachmentPath,
            'show_on_login' => $showOnLogin,
            'login_modal_expires_at' => $showOnLogin
                ? $this->resolveLoginModalExpiry($request, $announcement)
                : null,
        ]);
        $announcement->save();

        return redirect()
            ->route('admin.announcements')
            ->with('success', 'Announcement updated successfully.');
    }

    public function destroy(Announcement $announcement)
    {
        $this->authorizeAnnouncementOwner($announcement);

        $this->deleteAttachment($announcement->getAttribute('attachment_path'));
        $announcement->delete();

        return redirect()
            ->route('admin.announcements')
            ->with('success', 'Announcement deleted successfully.');
    }

    public function show(Announcement $announcement)
    {
        return view('admin.announcements.show', compact('announcement'));
    }

    /**
     * Any logged-in admin may post a new announcement, but only the admin who
     * created an announcement (or the IT Administrator) may edit/delete it.
     */
    protected function authorizeAnnouncementOwner(Announcement $announcement): void
    {
        $admin = Auth::guard('admin')->user();
        /** @var \App\Models\Admin|null $admin */
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

    protected function saveAttachment(Request $request)
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

    protected function deleteAttachment(?string $path)
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
