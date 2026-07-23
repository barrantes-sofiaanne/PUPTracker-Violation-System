<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\User;
use App\Models\Violation;
use App\Models\Announcement;
use App\Models\SanctionRequest;
use App\Models\ViolationCategory;
use Illuminate\Support\Facades\DB;

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

        // Get violation statistics by category
        $violationsByCategory = ViolationCategory::withCount('violations')
            ->orderByDesc('violations_count')
            ->take(5)
            ->get();

        // Get top students with most violations
        $topOffenders = Violation::select('student_number', DB::raw('COUNT(*) as violation_count'))
            ->groupBy('student_number')
            ->orderByDesc('violation_count')
            ->take(5)
            ->get();

        // Get monthly violation trend (last 6 months)
        $monthlyData = Violation::select(
            DB::raw('MONTH(violation_date) as month'),
            DB::raw('COUNT(*) as count')
        )
        ->where('violation_date', '>=', now()->subMonths(6))
        ->groupBy(DB::raw('MONTH(violation_date)'))
        ->orderBy('month')
        ->get();

        // Get violations by severity
        $violationsBySeverity = Violation::select('offense_level', DB::raw('COUNT(*) as count'))
            ->groupBy('offense_level')
            ->get()
            ->pluck('count', 'offense_level');

        return view(
            'admin.dashboard',
            compact(
                'totalStudents',
                'totalViolations',
                'pendingRequests',
                'announcementCount',
                'recentViolations',
                'violationsByCategory',
                'topOffenders',
                'monthlyData',
                'violationsBySeverity'
            )
        );
    }
}