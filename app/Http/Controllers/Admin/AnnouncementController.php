<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

        $unreadNotificationCount = AdminNotification::where('is_read', false)->count();
        $announcementStats = [
            'total' => Announcement::count(),
            'with_attachments' => Announcement::whereNotNull('attachment_path')->count(),
        ];

        return view(
            'admin.announcements.index',
            compact('announcements', 'unreadNotificationCount', 'announcementStats')
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
        ]);

        $admin = Auth::guard('admin')->user();
        $canPublishLoginModal = $admin?->isItAdministrator() ?? false;

        $announcement = new Announcement();
        $announcement->forceFill([
            'admin_id' => $admin?->getKey(),
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'attachment_path' => $this->saveAttachment($request),
            'show_on_login' => $canPublishLoginModal
                ? $request->boolean('show_on_login')
                : false,
        ]);
        $announcement->save();

        return redirect()
            ->route('admin.announcements')
            ->with('success', 'Announcement published successfully.');
    }

    public function edit(Announcement $announcement)
    {
        return view('admin.announcements.edit', compact('announcement'));
    }

    public function update(Request $request, Announcement $announcement)
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,pdf', 'max:5120'],
            'remove_attachment' => ['nullable', 'boolean'],
            'show_on_login' => ['nullable', 'boolean'],
        ]);

        $admin = Auth::guard('admin')->user();
        $canPublishLoginModal = $admin?->isItAdministrator() ?? false;

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
            'show_on_login' => $canPublishLoginModal
                ? $request->boolean('show_on_login')
                : false,
        ]);
        $announcement->save();

        return redirect()
            ->route('admin.announcements')
            ->with('success', 'Announcement updated successfully.');
    }

    public function destroy(Announcement $announcement)
    {
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
