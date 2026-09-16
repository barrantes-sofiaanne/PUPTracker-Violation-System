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
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $notifications = $this->ownedQuery()
            ->orderBy('is_read', 'asc')
            ->latest('created_at')
            ->paginate(10);

        $unreadCount = $this->ownedQuery()
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
     * The ONLY query used for student notification reads.
     *
     * Authorisation is enforced by the database query itself (account
     * ownership), never by filtering in the view/JavaScript.
     */
    private function ownedQuery(): \Illuminate\Database\Eloquent\Builder
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        return Notification::query()->ownedByStudent(
            $user?->user_id !== null ? (int) $user->user_id : null,
            $user?->student_number
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
        $validated = $request->validate([
            'action' => ['required', 'in:mark_all_read,mark_selected_read,mark_selected_unread'],
            'notification_ids' => ['nullable', 'array'],
            'notification_ids.*' => ['integer'],
        ]);

        // Scoped to the authenticated account, so an id belonging to another
        // student simply does not match and cannot be modified.
        $query = $this->ownedQuery();
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

    /**
     * Authorise against ACCOUNT OWNERSHIP.
     *
     * A notification addressed to another account is reported as 404 (not 403)
     * so the response cannot be used to probe whether a given notification id
     * exists at all.
     */
    private function authorizeNotification(Notification $notification): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        abort_unless(
            $notification->isOwnedByStudent(
                $user?->user_id !== null ? (int) $user->user_id : null,
                $user?->student_number
            ),
            404
        );
    }
}