<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = AdminNotification::orderBy('created_at', 'desc')->paginate(15);
        $unreadCount = AdminNotification::where('is_read', false)->count();

        return view(
            'admin.notifications.index',
            compact('notifications', 'unreadCount')
        );
    }
}
