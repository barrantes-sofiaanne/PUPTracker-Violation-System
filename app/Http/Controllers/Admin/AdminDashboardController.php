<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\User;
use App\Models\Violation;
use App\Models\Announcement;
use App\Models\SanctionRequest;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $totalStudents = User::count();

        $totalViolations = Violation::count();

        $pendingRequests = SanctionRequest::where(
            'status',
            'Pending'
        )->count();

        $announcementCount = Announcement::count();

        $recentViolations = Violation::with(
            'violationType'
        )
        ->latest('violation_date')
        ->take(10)
        ->get();

        return view(
            'admin.dashboard',
            compact(
                'totalStudents',
                'totalViolations',
                'pendingRequests',
                'announcementCount',
                'recentViolations'
            )
        );
    }
}