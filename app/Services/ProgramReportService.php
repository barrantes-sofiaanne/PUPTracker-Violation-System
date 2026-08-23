<?php

namespace App\Services;

use App\Models\Program;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationCategory;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Laravel-only aggregation for the Program Sanction Summary Report.
 *
 * Processes the ENTIRE selected program + graduating cohort (no arbitrary
 * student limit) and computes factual statistics directly from the database.
 * The AI is only ever given the aggregated dataset for the narrative.
 */
class ProgramReportService
{
    public function __construct(
        protected GraduationReviewScreeningService $screening,
    ) {
    }

    /**
     * All graduating students for a program + academic year (no limit).
     */
    public function studentsFor(Program $program, ?string $academicYear = null, string $reportMode = 'regular'): Collection
    {
        $query = User::query()
            ->with([
                'studentInfo.program',
                'studentInfo.year',
                'studentInfo.section',
                'studentInfo.studentStatus',
                'status',
            ])
            ->where('roles_id', 2)
            ->whereHas('studentInfo', fn ($q) => $q->where('program_id', $program->program_id));

        if ($reportMode === 'graduating') {
            $graduatingYear = $program->graduatingYear();
            if ($graduatingYear === null) {
                throw new InvalidArgumentException('This program has no valid degree-type classification. Update the program configuration before generating a graduating report.');
            }

            $yearLabels = match ($graduatingYear) {
                3 => ['3', '3rd Year', 'Third Year'],
                4 => ['4', '4th Year', 'Fourth Year'],
                default => [(string) $graduatingYear, $graduatingYear . 'th Year'],
            };

            $query->whereHas('studentInfo.year', fn ($q) => $q->whereIn('year', $yearLabels));
        } else {
            if ($academicYear === null || trim($academicYear) === '') {
                throw new InvalidArgumentException('An academic year is required for a regular program report.');
            }

            $query->whereHas('studentInfo.year', function ($q) use ($academicYear) {
                $q->where('year', 'like', '%' . $academicYear . '%')
                    ->orWhereRaw('LOWER(year) LIKE ?', ['%' . strtolower($academicYear) . '%']);
            });
        }

        return $query->orderBy('last_name')->get();
    }

    /**
     * Aggregate factual statistics for the selected program cohort.
     */
    public function aggregate(Program $program, ?string $academicYear, string $reportType = 'full', string $reportMode = 'regular'): array
    {
        $students = $this->studentsFor($program, $academicYear, $reportMode);

        $screened = $students
            ->map(fn (User $student) => $this->screening->screenStudent($student))
            ->values();

        // Apply report-type filtering after screening (respects cohort).
        $filtered = match ($reportType) {
            'violations' => $screened->filter(fn ($s) => (int) $s['total_violations'] > 0)->values(),
            'outstanding' => $screened->filter(fn ($s) => (int) $s['outstanding_sanctions'] > 0)->values(),
            'manual_review' => $screened->filter(fn ($s) => (bool) $s['requires_factual_review'])->values(),
            default => $screened,
        };

        $noHistory = $filtered->filter(fn ($s) => (int) $s['total_violations'] === 0)->count();
        $completed = $filtered->filter(fn ($s) => (int) $s['total_violations'] > 0 && (int) $s['outstanding_sanctions'] === 0)->count();
        $pending = $filtered->filter(fn ($s) => (int) $s['pending_sanctions'] > 0)->count();
        $outstanding = $filtered->filter(fn ($s) => (int) $s['outstanding_sanctions'] > 0)->count();
        $repeated = $filtered->filter(fn ($s) => (bool) $s['repeated_violations_exist'])->count();
        $manualReview = $filtered->filter(fn ($s) => (bool) $s['requires_factual_review'])->count();

        $totalViolationRecords = $filtered->sum(fn ($s) => (int) $s['total_violations']);

        // Category breakdown.
        $categoryCounts = $this->violationCategories($students);

        // Violation type breakdown.
        $typeCounts = $this->violationTypes($students);

        // Repeated violation summary: common repeated categories.
        $repeatedSummary = $this->repeatedViolationSummary($screened);

        return [
            'program_id' => $program->program_id,
            'program' => $program->program_name,
            'academic_year' => $academicYear,
            'report_type' => $reportType,
            'report_mode' => $reportMode,
            'degree_type' => $program->normalizedDegreeType(),
            'graduating_year' => $program->graduatingYear(),
            'students_with_violations' => $filtered->filter(fn ($s) => (int) $s['total_violations'] > 0)->count(),
            'students' => $filtered->all(),
            'total_students' => $filtered->count(),
            'cohort_total' => $students->count(),
            'students_with_no_violations' => $noHistory,
            'students_with_completed_sanctions' => $completed,
            'students_with_pending_sanctions' => $pending,
            'students_with_outstanding_sanctions' => $outstanding,
            'students_with_repeated_violations' => $repeated,
            'students_requiring_manual_review' => $manualReview,
            'total_violation_records' => $totalViolationRecords,
            'violation_categories' => $categoryCounts,
            'violation_types' => $typeCounts,
            'repeated_violations' => $repeatedSummary,
            'outstanding_students' => $filtered->filter(fn ($s) => (int) $s['outstanding_sanctions'] > 0)
                ->map(fn ($s) => [
                    'student_number' => $s['student_number'],
                    'student_name' => $s['student_name'],
                    'outstanding' => $s['outstanding_sanctions'],
                ])->values()->all(),
            'manual_review_students' => $filtered->filter(fn ($s) => (bool) $s['requires_factual_review'])
                ->map(fn ($s) => [
                    'student_number' => $s['student_number'],
                    'student_name' => $s['student_name'],
                    'reason' => $s['outstanding_sanctions'] > 0
                        ? 'Outstanding sanction(s)'
                        : 'Repeated violations',
                ])->values()->all(),
        ];
    }

    /**
     * Breakdown of violations by category for the whole cohort.
     */
    protected function violationCategories(Collection $students): array
    {
        return Violation::query()
            ->join('violation_type_tbl', 'violation_tbl.violation_type', '=', 'violation_type_tbl.violation_type_id')
            ->join('violation_category_tbl', 'violation_type_tbl.violation_category_id', '=', 'violation_category_tbl.violation_category_id')
            ->whereIn('violation_tbl.student_number', $students->pluck('student_number'))
            ->selectRaw('violation_category_tbl.category_name, COUNT(*) as total')
            ->groupBy('violation_category_tbl.category_name')
            ->orderByDesc('total')
            ->pluck('total', 'category_name')
            ->all();
    }

    /**
     * Breakdown of violations by type for the whole cohort.
     */
    protected function violationTypes(Collection $students): array
    {
        return Violation::query()
            ->join('violation_type_tbl', 'violation_tbl.violation_type', '=', 'violation_type_tbl.violation_type_id')
            ->whereIn('violation_tbl.student_number', $students->pluck('student_number'))
            ->selectRaw('violation_type_tbl.violation_type, COUNT(*) as total')
            ->groupBy('violation_type_tbl.violation_type')
            ->orderByDesc('total')
            ->pluck('total', 'violation_type')
            ->all();
    }

    /**
     * Summarize repeated violation patterns across the screened cohort.
     */
    protected function repeatedViolationSummary(Collection $screened): array
    {
        $studentsWithRepeats = $screened
            ->filter(fn ($s) => (bool) $s['repeated_violations_exist'])
            ->values();

        return [
            'student_count' => $studentsWithRepeats->count(),
            'total_incidents' => $studentsWithRepeats->sum(fn ($s) => (int) $s['total_violations']),
        ];
    }
}
