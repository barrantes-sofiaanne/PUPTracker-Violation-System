<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SecurityDashboardController extends Controller
{
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
            ->groupBy(fn($violation) => $violation->violation_category_display)
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
                'violation_tbl.violation_type',
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

        $majorViolations = (int) ($violationsBySeverity['Level 1'] ?? 0);
        $minorViolations = (int) ($violationsBySeverity['Level 2'] ?? 0);

        return view('security.dashboard', compact(
            'activeStudents',
            'totalViolations',
            'recentViolations',
            'activeOffenders',
            'violationsByCategory',
            'violationsBySeverity',
            'majorViolations',
            'minorViolations'
        ));
    }

    private function applyRecorderScope($query): void
    {
        $security = Auth::guard('security')->user();

        $label = !empty($security?->email)
            ? 'Security: ' . $security->email
            : 'Security #' . ($security?->id ?? 'Unknown');

        $legacySecurityIdLabel = 'Security #' . ($security?->id ?? 'Unknown');

        $query->where('recorder_type', 'security')
            ->where(function ($innerQuery) use ($label, $legacySecurityIdLabel) {
                $innerQuery->where('recorder_name', $label)
                    ->orWhere('recorder_name', $legacySecurityIdLabel);
            });
    }
}
