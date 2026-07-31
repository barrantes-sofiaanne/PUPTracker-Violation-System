<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $notifications = Notification::where(
                'student_number',
                $user->student_number
            )
            ->orderBy('is_read', 'asc')
            ->latest('created_at')
            ->paginate(10);

        $unreadCount = Notification::where(
                'student_number',
                $user->student_number
            )
            ->where('is_read', false)
            ->count();

        return view(
            'student.notifications',
            compact(
                'user',
                'notifications',
                'unreadCount'
            )
        );
    }

    public function updateStatus(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'action' => ['required', 'in:mark_all_read,mark_selected_read,mark_selected_unread'],
            'notification_ids' => ['nullable', 'array'],
            'notification_ids.*' => ['integer'],
        ]);

        $query = Notification::where('student_number', $user->student_number);
        $action = $validated['action'];

        if ($action === 'mark_all_read') {
            $updatedRows = (clone $query)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            return back()->with('success', "{$updatedRows} notification(s) marked as read.");
        }

        $selectedIds = $validated['notification_ids'] ?? [];

        if ($selectedIds === []) {
            return back()->withErrors([
                'notification_ids' => 'Please select at least one notification.',
            ]);
        }

        $markAsRead = $action === 'mark_selected_read';

        $updatedRows = (clone $query)
            ->whereIn('notification_id', $selectedIds)
            ->update(['is_read' => $markAsRead]);

        $statusLabel = $markAsRead ? 'read' : 'unread';

        return back()->with('success', "{$updatedRows} notification(s) marked as {$statusLabel}.");
    }
}