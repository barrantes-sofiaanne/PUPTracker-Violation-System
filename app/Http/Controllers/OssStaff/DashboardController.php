<?php

namespace App\Http\Controllers\OssStaff;

use App\Http\Controllers\Controller;
use App\Http\Controllers\OssStaff\Concerns\ResolvesOssStaffActor;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * OSS Staff dashboard.
 *
 * Operationally the same dashboard the Security module shows — the same stats,
 * the same recent-violation list, the same repeat-offender ranking, the same
 * chart — but scoped to the OSS Staff account's OWN recorded violations and
 * rendered with OSS Staff branding and routes.
 *
 * The recorder scope is the ONLY behavioural difference: it resolves the actor
 * through the admin guard (see {@see ResolvesOssStaffActor}) instead of the
 * security guard, so an OSS Staff member never sees (or is counted against) a
 * Security officer's records.
 */
class DashboardController extends Controller
{
    use ResolvesOssStaffActor;

    public function index()
    {
        $baseViolations = Violation::query();
        $this->applyRecorderScope($baseViolations);

        // Get active students count
        $activeStudents = User::count();

        // Get total violations
        $totalViolations = (clone $baseViolations)->count();

        // Get recent violations (last 7 days)
        $recentViolations = Violation::with(['student', 'violationType.violationCategory'])
            ->where(function (Builder $query) {
                $this->applyRecorderScope($query);
            })
            ->where('violation_date', '>=', now()->subDays(7))
            ->latest('violation_date')
            ->take(10)
            ->get();

        // Get students with active violations (repeat offenders)
        $activeOffenders = Violation::select('student_number', DB::raw('COUNT(*) as violation_count'))
            ->where(function (Builder $query) {
                $this->applyRecorderScope($query);
            })
            ->groupBy('student_number')
            ->orderByDesc('violation_count')
            ->take(10)
            ->get();

        // Get violations by category for the week
        $violationsByCategory = Violation::with(['violationType.violationCategory'])
            ->where(function (Builder $query) {
                $this->applyRecorderScope($query);
            })
            ->where('violation_date', '>=', now()->subDays(7))
            ->get()
            ->groupBy(fn ($violation) => $violation->violation_category_display)
            ->map(function ($items, $categoryName) {
                return (object) [
                    'category_name' => $categoryName,
                    'count' => $items->count(),
                ];
            })
            ->sortByDesc('count')
            ->values();

        // Get violations by severity from violation_type_tbl.
        // Some rows may still have legacy or unmapped violation types, so keep them as Unmapped.
        $violationsBySeverity = Violation::leftJoin(
    'violation_type_tbl',
    'violation_tbl.violation_type_id',
    '=',
    'violation_type_tbl.violation_type_id'
            )
            ->where(function (Builder $query) {
                $this->applyRecorderScope($query);
            })
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

        // Violation and Sanction Guide. Uses the SAME data source and the SAME
        // shared partial as the Student/Security dashboards, so every module
        // displays identical violation/sanction information.
        $categories = ViolationCategory::with([
            'violationTypes.disciplinarySanctions'
        ])->get();

        return view('oss-staff.dashboard', compact(
            'activeStudents',
            'totalViolations',
            'recentViolations',
            'activeOffenders',
            'violationsByCategory',
            'violationsBySeverity',
            'categories'
        ));
    }

    /**
     * Filterable chart data: violations recorded by the CURRENT OSS Staff
     * account, grouped per day (week/month) or per month (year/all). Supports a
     * status filter. Returns JSON for the dashboard chart (Chart.js).
     */
    public function chartData()
    {
        $period = (string) request()->query('period', 'week'); // week | month | year | all
        $status = (string) request()->query('status', '');     // '' | pending | confirmed | denied

        $query = Violation::query();
        $this->applyRecorderIdentityOnlyScope($query);

        if (in_array($status, [Violation::STATUS_PENDING, Violation::STATUS_CONFIRMED, Violation::STATUS_DENIED], true)) {
            $query->where('status', $status);
        }

        $start = $this->rangeStart($period);
        if ($start !== null) {
            $query->where('violation_date', '>=', $start);
        }

        $violations = $query
            ->whereNotNull('violation_date')
            ->get(['violation_date', 'status']);

        $byDay = $violations->groupBy(function (Violation $violation) {
            return \Illuminate\Support\Carbon::parse($violation->violation_date)
                ->setTimezone('Asia/Manila')
                ->format('Y-m-d');
        });

        $byMonth = $violations->groupBy(function (Violation $violation) {
            return \Illuminate\Support\Carbon::parse($violation->violation_date)
                ->setTimezone('Asia/Manila')
                ->format('Y-m');
        });

        [$labels, $keys, $monthly] = $this->rangeSeries($period, $start);

        $values = [];
        foreach ($keys as $key) {
            $bucket = $monthly ? ($byMonth[$key] ?? null) : ($byDay[$key] ?? null);
            $values[] = $bucket ? $bucket->count() : 0;
        }

        return response()->json([
            'labels' => $labels,
            'values' => $values,
            'total' => array_sum($values),
            'filters' => [
                'period' => $period,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Recorder restriction WITHOUT the denied exclusion, so the filterable
     * chart can still show denied rows when that status is requested.
     */
    private function applyRecorderIdentityOnlyScope($query): void
    {
        $this->applyRecorderScope($query, true);
    }

    private function rangeStart(string $period): ?\Illuminate\Support\Carbon
    {
        return match ($period) {
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => null,
        };
    }

    /**
     * @return array{0: array<int,string>, 1: array<int,string>, 2: bool}  [labels, keys, monthly]
     */
    private function rangeSeries(string $period, ?\Illuminate\Support\Carbon $start): array
    {
        // Day series for week/month.
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

        // Month series for year/all. "all" covers the last 12 months; "year"
        // covers Jan..Dec of the current year.
        $months = [];
        if ($period === 'year') {
            for ($m = 1; $m <= 12; $m++) {
                $months[] = \Illuminate\Support\Carbon::create(now()->year, $m, 1);
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
