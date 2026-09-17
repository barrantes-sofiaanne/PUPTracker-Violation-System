<?php

namespace App\Http\Controllers\OssStaff;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Admin;
use App\Services\AdminNotificationService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * OSS Staff notifications.
 *
 * OSS Staff accounts are ADMIN accounts, so their notifications are rows in
 * `admin_notifications_tbl` addressed to the ADMIN audience and to this exact
 * account. Authorisation reuses {@see AdminNotification::scopeForAudience} — the
 * same single authoritative rule the Admin inbox uses — so an OSS Staff member
 * can only ever see (and open) notifications inside their own audience and no
 * Super Admin alert leaks in.
 *
 * The OSS Staff member is NOT a Super Admin, so `isSuperAdmin` is always false
 * here.
 */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $adminId = $this->adminId();
        $query = $this->accessibleNotifications($adminId);

        // Status filter (read/unread/all)
        $status = $request->input('status', '');
        if ($status === 'read') {
            $query->where('is_read', true);
        } elseif ($status === 'unread') {
            $query->where('is_read', false);
        }

        $notifications = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        $unreadCount = $this->accessibleNotifications($adminId)->where('is_read', false)->count();

        return view('oss-staff.notifications.index', compact('notifications', 'unreadCount', 'status'));
    }

    public function markAsRead(Request $request, AdminNotification $notification): Response
    {
        $this->authorizeNotification($notification);
        $notification->update(['is_read' => true]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'unread_count' => $this->unreadCountFor($this->adminId()),
            ]);
        }

        return back();
    }

    public function markAllRead(): Response
    {
        $this->accessibleNotifications($this->adminId())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return back()->with('success', 'All notifications marked as read.');
    }

    /**
     * Lightweight unread-count endpoint used by the header bell.
     */
    public function unreadCount(): Response
    {
        return response()->json([
            'unread_count' => $this->unreadCountFor($this->adminId()),
        ]);
    }

    /**
     * Open a notification: authorize, resolve the exact target, mark as read
     * and redirect the OSS Staff member to the correct page.
     */
    public function open(AdminNotification $notification, AdminNotificationService $service): Response
    {
        $this->authorizeNotification($notification);

        $url = $service->openUrl($notification);

        if ($url === null) {
            // The underlying record no longer exists. Never dead-end the user
            // on a raw 404 — take them to the relevant list so they can still
            // act on what remains.
            $fallback = match ($notification->target_type) {
                'violation' => route('oss-staff.violations.students', ['tab' => 'submitted']),
                'sanction_request' => route('oss-staff.sanction-requests'),
                default => route('oss-staff.notifications'),
            };

            $notification->update(['is_read' => true]);

            return redirect($fallback)
                ->with('warning', 'This notification\'s associated record is no longer available. You were taken to the nearest list.');
        }

        $notification->update(['is_read' => true]);

        return redirect()->to($url);
    }

    /**
     * The ONLY query used for the OSS Staff inbox: the admin audience rule with
     * the Super Admin flag forced OFF, so only operational-admin notifications
     * addressed to this account (or broadcast to the admin audience) appear.
     */
   private function accessibleNotifications(?int $adminId)
{
    return AdminNotification::query()
        ->where('recipient_type', AdminNotification::AUDIENCE_ADMIN)
        ->where('recipient_id', $adminId);
}

    private function unreadCountFor(?int $adminId): int
    {
        return $this->accessibleNotifications($adminId)->where('is_read', false)->count();
    }

    private function adminId(): ?int
    {
        $id = auth('admin')->id();

        return $id !== null ? (int) $id : null;
    }

    /**
     * Same rule as the inbox query — an id outside the caller's audience is
     * refused, so notification ids cannot be probed or replayed.
     */
    private function authorizeNotification(AdminNotification $notification): void
    {
        $adminId = $this->adminId();

        abort_unless(
            AdminNotification::query()
    ->whereKey($notification->getKey())
    ->where('recipient_type', AdminNotification::AUDIENCE_ADMIN)
    ->where('recipient_id', $adminId)
    ->exists(),
            403
        );
    }
}
