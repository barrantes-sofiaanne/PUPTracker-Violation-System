<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\StudentNotificationService;
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

    /**
     * Open a notification: authorize, resolve the exact target, mark as read,
     * and redirect the student to the correct module (never a wrong page).
     */
    public function open(Notification $notification, StudentNotificationService $service): \Illuminate\Http\RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        $this->authorizeNotification($notification);

        $url = $service->openUrl($notification);

        if ($url === null) {
            // Fallback: notification center if the target is gone.
            $notification->update(['is_read' => true]);

            return redirect()
                ->route('student.notifications')
                ->with('warning', 'This notification\'s associated record is no longer available.');
        }

        $notification->update(['is_read' => true]);

        return redirect()->to($url);
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

    private function authorizeNotification(Notification $notification): void
    {
        $user = Auth::user();

        abort_unless(
            (string) $notification->student_number === (string) $user->student_number,
            403
        );
    }
}