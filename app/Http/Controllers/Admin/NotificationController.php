<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $query = AdminNotification::query();

        // Status filter (read/unread/all)
        $status = $request->input('status', '');
        if ($status === 'read') {
            $query->where('is_read', true);
        } elseif ($status === 'unread') {
            $query->where('is_read', false);
        }

        // Type filter
        $type = $request->input('type', '');
        if ($type !== '') {
            $query->where('notification_type', $type);
        }

        // Search filter
        if ($request->filled('search')) {
            $query->where('message', 'like', '%' . trim($request->input('search')) . '%');
        }

        $notifications = $query->orderBy('created_at', 'desc')->paginate(15);
        $unreadCount = AdminNotification::where('is_read', false)->count();
        $types = AdminNotification::query()
            ->distinct()
            ->pluck('notification_type')
            ->filter()
            ->values();

        return view(
            'admin.notifications.index',
            compact('notifications', 'unreadCount', 'types', 'status', 'type')
        );
    }

    public function markAsRead(Request $request, AdminNotification $notification)
    {
        $notification->update(['is_read' => true]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back();
    }

    public function markAsUnread(Request $request, AdminNotification $notification)
    {
        $notification->update(['is_read' => false]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back();
    }

    public function markAllRead()
    {
        AdminNotification::where('is_read', false)->update(['is_read' => true]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
