<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Notification;
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
}