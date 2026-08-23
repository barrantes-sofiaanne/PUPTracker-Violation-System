<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;

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

        // Get top students with most violations
        $topOffenders = Violation::select('student_number', DB::raw('COUNT(*) as violation_count'))
            ->groupBy('student_number')
            ->orderByDesc('violation_count')
            ->take(5)
            ->get();

        $weeklyTrend = $this->weeklyViolationTrend();

        return view(
            'admin.dashboard',
            compact(
                'totalStudents',
                'totalViolations',
                'pendingRequests',
                'announcementCount',
                'recentViolations',
                'topOffenders',
                'weeklyTrend'
            )
        );
    }

    /**
     * JSON endpoint used by the dashboard's weekly trend chart.
     */
    public function chartData()
    {
        $trend = $this->weeklyViolationTrend();

        return response()->json([
            'labels' => $trend['labels'],
            'values' => $trend['values'],
        ]);
    }

    private function weeklyViolationTrend(): array
    {
        $start = now()->subWeeks(7)->startOfWeek();
        $period = collect(range(0, 7))->map(function ($offset) use ($start) {
            $weekStart = $start->copy()->addWeeks($offset);

            return [
                'key' => $weekStart->format('Y-m-d'),
                'label' => $weekStart->format('M d'),
            ];
        });

        $violations = Violation::query()
            ->whereNotNull('violation_date')
            ->where('violation_date', '>=', $start)
            ->get();

        $counts = $violations->groupBy(function ($violation) {
            $date = Carbon::parse($violation->violation_date);

            return $date->startOfWeek()->format('Y-m-d');
        })->map->count();

        $labels = [];
        $values = [];

        foreach ($period as $week) {
            $labels[] = $week['label'];
            $values[] = (int) ($counts[$week['key']] ?? 0);
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }
}