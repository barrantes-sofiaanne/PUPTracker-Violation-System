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

        $totalViolations = Violation::effective()->count();

        $pendingRequests = SanctionRequest::where(
            'status',
            'Pending'
        )->count();

        $announcementCount = Announcement::count();

        $recentViolations = Violation::with(
            'violationType'
        )
        ->effective()
        ->latest('violation_date')
        ->take(10)
        ->get();

        // Get top students with most violations
        $topOffenders = Violation::effective()
            ->select('student_number', DB::raw('COUNT(*) as violation_count'))
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
     * JSON endpoint used by the dashboard's violation trend chart.
     * Filterable by period (week|month|year|all) and status.
     */
    public function chartData()
    {
        $trend = $this->violationTrend(
            (string) request()->query('period', 'week'),
            (string) request()->query('status', '')
        );

        return response()->json([
            'labels' => $trend['labels'],
            'values' => $trend['values'],
            'total' => $trend['total'],
        ]);
    }

    private function weeklyViolationTrend(): array
    {
        return $this->violationTrend('week', '');
    }

    private function violationTrend(string $period, string $status): array
    {
        // Base on ALL violations so the status filter (pending/confirmed/denied)
        // is meaningful; the dashboard can be filtered down as needed.
        $query = Violation::query();

        if (in_array($status, ['pending', 'confirmed', 'denied'], true)) {
            $query->where('status', $status);
        }

        $start = match ($period) {
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => null,
        };

        if ($start !== null) {
            $query->where('violation_date', '>=', $start);
        }

        $violations = $query
            ->whereNotNull('violation_date')
            ->get(['violation_date']);

        $byDay = $violations->groupBy(function ($violation) {
            return Carbon::parse($violation->violation_date)
                ->setTimezone('Asia/Manila')
                ->format('Y-m-d');
        });

        $byMonth = $violations->groupBy(function ($violation) {
            return Carbon::parse($violation->violation_date)
                ->setTimezone('Asia/Manila')
                ->format('Y-m');
        });

        [$labels, $keys, $monthly] = $this->trendSeries($period, $start);

        $values = [];
        foreach ($keys as $key) {
            $bucket = $monthly ? ($byMonth[$key] ?? null) : ($byDay[$key] ?? null);
            $values[] = $bucket ? $bucket->count() : 0;
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'total' => array_sum($values),
        ];
    }

    /**
     * @return array{0: array<int,string>, 1: array<int,string>, 2: bool}  [labels, keys, monthly]
     */
    private function trendSeries(string $period, ?Carbon $start): array
    {
        if (in_array($period, ['week', 'month'], true)) {
            $end = $period === 'month'
                ? now()->startOfMonth()->addMonth()->subDay()
                : now()->startOfWeek()->addWeek()->subDay();

            $labels = [];
            $keys = [];
            $cursor = $start->copy();
            while ($cursor->lte($end) && count($keys) < 92) {
                $labels[] = $cursor->format('M d');
                $keys[] = $cursor->format('Y-m-d');
                $cursor->addDay();
            }

            return [$labels, $keys, false];
        }

        // Month series for year/all.
        $months = [];
        if ($period === 'year') {
            for ($m = 1; $m <= 12; $m++) {
                $months[] = Carbon::create(now()->year, $m, 1);
            }
        } else {
            for ($i = 11; $i >= 0; $i--) {
                $months[] = now()->copy()->startOfMonth()->subMonths($i);
            }
        }

        $labels = [];
        $keys = [];
        foreach ($months as $month) {
            $labels[] = $month->format('M y');
            $keys[] = $month->format('Y-m');
        }

        return [$labels, $keys, true];
    }
}