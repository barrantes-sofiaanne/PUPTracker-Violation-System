<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\Notification;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::where('recipient_type', 'security')
            ->latest('created_at')
            ->paginate(15);

        $unreadCount = Notification::where('recipient_type', 'security')
            ->where('is_read', false)
            ->count();

        return view('security.notifications.index', compact('notifications', 'unreadCount'));
    }
}
