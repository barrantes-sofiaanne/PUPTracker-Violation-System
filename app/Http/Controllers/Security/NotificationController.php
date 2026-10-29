<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\SecurityNotificationService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends Controller
{
    public function index()
    {
        $securityId = auth('security')->id();

        $notifications = Notification::forRecipient('security', $securityId)
            ->latest('created_at')
            ->paginate(15);

        $unreadCount = Notification::forRecipient('security', $securityId)
            ->where('is_read', false)
            ->count();

        return view('security.notifications.index', compact('notifications', 'unreadCount'));
    }

    /**
     * Open a security notification: authorize, resolve the exact target, mark
     * as read, and redirect the security officer to the correct page.
     */
    public function open(Notification $notification, SecurityNotificationService $service): Response
    {
        $this->authorizeNotification($notification);

        $url = $service->openUrl($notification);

        if ($url === null) {
            $notification->update(['is_read' => true]);

            return redirect()
                ->route('security.notifications')
                ->with('warning', 'This notification\'s associated record is no longer available.');
        }

        $notification->update(['is_read' => true]);

        return redirect()->to($url);
    }

    /**
     * Mark a single notification read (used by the header bell).
     */
    public function markAsRead(Request $request, Notification $notification): Response
    {
        $this->authorizeNotification($notification);
        $notification->update(['is_read' => true]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back();
    }

    /**
     * Mark all security notifications as read.
     */
    public function markAllRead(): Response
    {
        Notification::forRecipient('security', auth('security')->id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return back()->with('success', 'All notifications marked as read.');
    }

    /**
     * Lightweight unread-count endpoint for the header bell.
     */
    public function unreadCount(): Response
    {
        return response()->json([
            'unread_count' => Notification::forRecipient('security', auth('security')->id())
                ->where('is_read', false)
                ->count(),
        ]);
    }

    private function authorizeNotification(Notification $notification): void
    {
        $securityId = auth('security')->id();

        // Fail closed: a notification must have an explicit recipient and that
        // recipient must be the currently authenticated Security account.
        abort_unless(
            $securityId !== null
            && (string) ($notification->recipient_type ?? '') === 'security'
            && $notification->recipient_id !== null
            && (int) $notification->recipient_id === (int) $securityId,
            403
        );
    }
}
