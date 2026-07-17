<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Violation;
use App\Models\Notification;
use App\Models\ViolationCategory;

class StudentDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        /*
        |--------------------------------------------------------------------------
        | Student Violations
        |--------------------------------------------------------------------------
        */

        $recentViolations = Violation::with([
                'violationType.violationCategory'
            ])
            ->where('student_number', $user->student_number)
            ->orderByDesc('violation_date')
->take(5)            ->get();

        $totalViolations = Violation::where(
                'student_number',
                $user->student_number
            )
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Notifications
        |--------------------------------------------------------------------------
        */

       $notifications = Notification::where(
        'student_number',
        $user->student_number
    )
    ->where('recipient_type', 'student')
    ->latest('created_at')
    ->take(5)
    ->get();

      $notificationCount = Notification::where(
        'student_number',
        $user->student_number
    )
    ->where('recipient_type', 'student')
    ->where('is_read', 0)
    ->count();

        /*
        |--------------------------------------------------------------------------
        | Student Handbook
        |--------------------------------------------------------------------------
        */

        $categories = ViolationCategory::with([
            'violationTypes.disciplinarySanctions'
        ])->get();

        return view(
            'student.dashboard',
            compact(
                'user',
                'recentViolations',
                'totalViolations',
                'notifications',
                'notificationCount',
                'categories'
            )
        );
    }
}