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

        return view(
            'admin.announcements.index',
            compact('announcements', 'unreadNotificationCount')
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
        ]);

        $announcement = new Announcement();
        $announcement->admin_id = Auth::guard('admin')->id();
        $announcement->title = $request->title;
        $announcement->content = $request->content;
        $announcement->attachment_path = $this->saveAttachment($request);
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
        ]);

        $announcement->title = $request->title;
        $announcement->content = $request->content;

        if ($request->has('remove_attachment') && $request->boolean('remove_attachment')) {
            $this->deleteAttachment($announcement->attachment_path);
            $announcement->attachment_path = null;
        }

        if ($request->hasFile('attachment')) {
            $this->deleteAttachment($announcement->attachment_path);
            $announcement->attachment_path = $this->saveAttachment($request);
        }

        $announcement->save();

        return redirect()
            ->route('admin.announcements')
            ->with('success', 'Announcement updated successfully.');
    }

    public function destroy(Announcement $announcement)
    {
        $this->deleteAttachment($announcement->attachment_path);
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
