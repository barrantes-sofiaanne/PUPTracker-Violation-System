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

        // Get violations by severity from violation_type_tbl.
        // Some rows may have legacy/unmapped violation_type values, so keep them as "Unmapped".
        $violationsBySeverity = Violation::leftJoin(
                'violation_type_tbl',
                'violation_tbl.violation_type',
                '=',
                'violation_type_tbl.violation_type_id'
            )
            ->selectRaw('COALESCE(violation_type_tbl.severity_level, 0) as severity_level, COUNT(*) as count')
            ->groupBy('severity_level')
            ->orderByDesc('count')
            ->get()
            ->mapWithKeys(function ($row) {
                $label = (int) $row->severity_level === 0
                    ? 'Unmapped'
                    : 'Level ' . (int) $row->severity_level;

                return [$label => (int) $row->count];
            });

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