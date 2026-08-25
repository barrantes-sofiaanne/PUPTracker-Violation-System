<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminNotification;
use App\Services\AdminNotificationService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $adminId = auth('admin')->id();
        $query = AdminNotification::query()
            ->where(function ($recipientQuery) use ($adminId): void {
                $recipientQuery->whereNull('recipient_type')
                    ->orWhere('recipient_type', 'admin');
            })
            ->where(function ($recipientQuery) use ($adminId): void {
                $recipientQuery->whereNull('recipient_id')
                    ->orWhere('recipient_id', $adminId);
            });

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

        $notifications = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();
        $unreadCount = $this->accessibleNotifications($adminId)->where('is_read', false)->count();
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
        $this->authorizeNotification($notification);
        $notification->update(['is_read' => true]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back();
    }

    public function markAsUnread(Request $request, AdminNotification $notification)
    {
        $this->authorizeNotification($notification);
        $notification->update(['is_read' => false]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back();
    }

    public function markAllRead()
    {
        $this->accessibleNotifications(auth('admin')->id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return back()->with('success', 'All notifications marked as read.');
    }

    /**
     * Lightweight unread-count endpoint used to refresh the header bell
     * without reloading the page or polling aggressively.
     */
    public function unreadCount()
    {
        return response()->json([
            'unread_count' => $this->accessibleNotifications(auth('admin')->id())
                ->where('is_read', false)
                ->count(),
        ]);
    }

    public function open(AdminNotification $notification, AdminNotificationService $service): Response
    {
        $this->authorizeNotification($notification);

        $admin = auth('admin')->user();
        if ($notification->target_type === 'email_incident'
            && $admin instanceof Admin
            && ! $admin->isItAdministrator()) {
            abort(403);
        }

        $url = $service->openUrl($notification);
        if ($url === null) {
            // The underlying record no longer exists (e.g. the sanction request
            // was removed). Never dead-end the admin on a raw 404 — take them
            // to the relevant list so they can still act on what remains.
            $fallback = match ($notification->target_type) {
                'sanction_request' => route('admin.sanctions.index', ['sanctions_tab' => 'sanction-requests']),
                'email_incident' => route('admin.super-admin.email-incidents.index'),
                default => route('admin.notifications'),
            };

            $notification->update(['is_read' => true]);

            return redirect($fallback)
                ->with('warning', 'This notification\'s associated record is no longer available. You were taken to the nearest list.');
        }

        $notification->update(['is_read' => true]);

        return redirect()->to($url);
    }

    private function accessibleNotifications(?int $adminId)
    {
        return AdminNotification::query()
            ->where(function ($query): void {
                $query->whereNull('recipient_type')->orWhere('recipient_type', 'admin');
            })
            ->where(function ($query) use ($adminId): void {
                $query->whereNull('recipient_id')->orWhere('recipient_id', $adminId);
            });
    }

    private function authorizeNotification(AdminNotification $notification): void
    {
        $adminId = auth('admin')->id();
        $accessible = ($notification->recipient_type === null || $notification->recipient_type === 'admin')
            && ($notification->recipient_id === null || (int) $notification->recipient_id === (int) $adminId);

        abort_unless($accessible, 403);
    }
}
