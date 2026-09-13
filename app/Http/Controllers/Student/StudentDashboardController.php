<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Violation;
use App\Models\StudentSanctionRecord;
use App\Models\ViolationCategory;

class StudentDashboardController extends Controller
{
    /**
     * How many recent violation records the dashboard preview shows.
     */
    private const VIOLATION_PREVIEW_LIMIT = 3;

    /**
     * How many approaching sanction deadlines the dashboard shows.
     */
    private const DEADLINE_LIMIT = 5;

    public function index()
    {
        $user = Auth::user();

        // Flip this student's open sanctions past their deadline to Overdue
        // (same authoritative sweep the Record page runs) so the dashboard
        // shows the correct status.
        try {
            app(\App\Services\SanctionOverdueService::class)->sweep();
        } catch (\Throwable $e) {
            // A sweep failure must never block the dashboard.
        }

        /*
        |--------------------------------------------------------------------------
        | Student Violations
        |--------------------------------------------------------------------------
        |
        | Dashboard preview only: the three MOST RECENT records, newest first.
        | Ordering and scoping match the Violation Record tab, so this preview
        | always reflects the same authoritative violation data.
        */

        $recentViolations = Violation::with([
                'violationType.violationCategory'
            ])
            ->where('student_number', $user->student_number)
            ->effective()
            ->orderByDesc('violation_date')
            ->take(self::VIOLATION_PREVIEW_LIMIT)
            ->get();

        $totalViolations = Violation::where(
                'student_number',
                $user->student_number
            )
            ->effective()
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Approaching Sanction Deadlines
        |--------------------------------------------------------------------------
        |
        | Upcoming deadlines for THIS student's own sanctions only. Completed
        | sanctions are excluded (they are no longer upcoming); open sanctions
        | that are past their deadline are still surfaced so the student can act.
        | Ordered by nearest deadline first.
        */

        $approachingDeadlines = StudentSanctionRecord::with([
                'violation.violationType',
                'assignedSanction',
            ])
            ->where('student_number', $user->student_number)
            ->whereNotNull('deadline_date')
            ->where('status', '!=', StudentSanctionRecord::STATUS_COMPLETED)
            ->orderBy('deadline_date')
            ->take(self::DEADLINE_LIMIT)
            ->get();

        $overdueDeadlineCount = StudentSanctionRecord::where(
                'student_number',
                $user->student_number
            )
            ->whereNotNull('deadline_date')
            ->where('status', '!=', StudentSanctionRecord::STATUS_COMPLETED)
            ->whereDate('deadline_date', '<', now()->toDateString())
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
                'approachingDeadlines',
                'overdueDeadlineCount',
                'categories'
            )
        );
    }
}