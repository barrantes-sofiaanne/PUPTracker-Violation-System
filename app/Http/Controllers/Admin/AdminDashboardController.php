<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;

use App\Models\User;
use App\Models\Violation;
use App\Models\SanctionRequest;
use App\Models\ViolationCategory;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $totalStudents = User::count();

        $totalViolations = Violation::effective()->count();

        $pendingRequests = SanctionRequest::where(
            'status',
            'Pending'
        )->count();

        // Violations awaiting an administrator's confirmation. This is the
        // operational queue, distinct from student-raised sanction requests.
        $pendingConfirmations = Violation::query()
            ->pendingConfirmation()
            ->count();

        // Violations recorded on the current calendar day (app timezone).
        $recordedViolationToday = Violation::effective()
            ->whereDate('violation_date', now()->toDateString())
            ->count();

        // The dashboard's "Recent Violations" is capped at the 5 newest records.
        $recentViolations = Violation::with(
            'violationType'
        )
        ->effective()
        ->latest('violation_date')
        ->take(5)
        ->get();

        // Get top students with most violations
        $topOffenders = Violation::effective()
            ->select('student_number', DB::raw('COUNT(*) as violation_count'))
            ->groupBy('student_number')
            ->orderByDesc('violation_count')
            ->take(5)
            ->get();

        // Daily series for the dashboard's DAILY bar chart (last 7 days).
        $dailyTrend = $this->dailyViolationSeries(7);

        return view(
            'admin.dashboard',
            compact(
                'totalStudents',
                'totalViolations',
                'pendingRequests',
                'pendingConfirmations',
                'recordedViolationToday',
                'recentViolations',
                'topOffenders',
                'dailyTrend'
            )
        );
    }

    /**
     * JSON endpoint used by the dashboard's DAILY violation bar chart.
     * Always returns daily buckets for the trailing window (last 7 days,
     * ending today) in the application timezone.
     */
    public function chartData()
    {
        $trend = $this->dailyViolationSeries(7);

        return response()->json([
            'labels' => $trend['labels'],
            'values' => $trend['values'],
            'total' => $trend['total'],
        ]);
    }

    /**
     * Build the DAILY violation series: one point per calendar day over the
     * trailing window (default: last 7 days ending today). Buckets by day in
     * the application timezone and counts only effective violations.
     *
     * @param  int  $days  Number of consecutive calendar days to include.
     * @return array{labels: array<int,string>, values: array<int,int>, total: int}
     */
    private function dailyViolationSeries(int $days = 7): array
    {
        $timezone = (string) config('app.timezone', 'Asia/Manila');

        $today = now()->setTimezone($timezone)->startOfDay();
        $start = $today->copy()->subDays(max(1, $days) - 1);

        $violations = Violation::effective()
            ->whereBetween('violation_date', [
                $start->copy()->startOfDay()->toDateTimeString(),
                $today->copy()->endOfDay()->toDateTimeString(),
            ])
            ->whereNotNull('violation_date')
            ->pluck('violation_date');

        $byDay = $violations->countBy(function ($value) use ($timezone) {
            return Carbon::parse($value)
                ->setTimezone($timezone)
                ->format('Y-m-d');
        });

        $labels = [];
        $values = [];
        $cursor = $start->copy();
        while ($cursor->lte($today)) {
            $labels[] = $cursor->format('M d');
            $values[] = (int) ($byDay[$cursor->format('Y-m-d')] ?? 0);
            $cursor->addDay();
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'total' => array_sum($values),
        ];
    }
}