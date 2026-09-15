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
     * All students for a program report (no limit).
     *
     * - regular mode: filters by the academic year label (year_tbl).
     * - graduating mode: filters by the program's graduating YEAR LEVEL
     *   (student_info_tbl.year_id -> year_tbl.year). The required level is
     *   derived from the program degree type: 4th Year for Bachelor's /
     *   Ladderized Bachelor programs and 3rd Year for Diploma programs.
     *   Specific students added manually by number are always included.
     *
     * Optional $ladderized filter (graduating mode, BSOA/BSIT only):
     *   null = include both ladderized and non-ladderized
     *   1    = only students whose student_info_tbl.ladderized flag is 1
     *   0    = only students whose student_info_tbl.ladderized flag is 0
     */
    public function studentsFor(
        Program $program,
        ?string $academicYear = null,
        string $reportMode = 'regular',
        array $graduatingYears = [],
        ?int $ladderized = null,
        array $additionalStudents = [],
        array $excludedStudents = []
    ): Collection {
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

        // Declared OUTSIDE the branch below: `$explicit` is used after the
        // if/else (the graduating sort), so defining it only inside
        // `$reportMode === 'graduating'` left it undefined for regular reports.
        $explicit = [];

        if ($reportMode === 'graduating') {
            $explicit = array_values(array_unique(array_filter(
                array_map(fn ($v) => trim((string) $v), $additionalStudents),
                fn ($v) => $v !== ''
            )));

            // Automatic eligibility: the program's graduating YEAR LEVEL
            // (4th Year for Bachelor's / Ladderized Bachelor, 3rd Year for
            // Diploma). Student-number prefixes are NOT used.
            $graduatingLevel = $program->graduatingYear();

            if ($graduatingLevel === null && $explicit === []) {
                throw new InvalidArgumentException(
                    'The selected program has no recognized degree type, so no students can be auto-selected. Add specific students manually instead.'
                );
            }

            // The graduating cohort is the UNION of:
            //   - students whose YEAR LEVEL matches the program's graduating
            //     level (Bachelor/ladderized => 4th Year, Diploma => 3rd Year), and
            //   - specific students explicitly added by number.
            $query->where(function ($q) use ($graduatingLevel, $explicit): void {
                if ($graduatingLevel !== null) {
                    $q->where(function ($inner) use ($graduatingLevel): void {
                        $inner->whereHas('studentInfo.year', function ($yearQuery) use ($graduatingLevel): void {
                            // year_tbl.year stores ordinal labels such as
                            // "4th Year" (or a plain "4"). Match on the leading
                            // level number portably across MySQL and SQLite.
                            $yearQuery->where('year', (string) $graduatingLevel)
                                ->orWhere('year', 'like', $graduatingLevel . '%');
                        });
                    });
                }

                if ($explicit !== []) {
                    $q->orWhereIn('student_number', $explicit);
                }
            });

            // Ladderized filter uses the real ladderized flag on
            // student_info_tbl. A specifically added student is always kept.
            if ($ladderized === 1) {
                $query->where(function ($q) use ($explicit): void {
                    $q->whereHas('studentInfo', fn ($info) => $info->where('ladderized', 1))
                        ->orWhereIn('student_number', $explicit);
                });
            } elseif ($ladderized === 0) {
                $query->where(function ($q) use ($explicit): void {
                    $q->whereHas('studentInfo', fn ($info) => $info->where('ladderized', 0))
                        ->orWhereIn('student_number', $explicit);
                });
            }
        } else {
            if ($academicYear === null || trim($academicYear) === '') {
                throw new InvalidArgumentException('An academic year is required for a regular program report.');
            }

            $query->whereHas('studentInfo.year', function ($q) use ($academicYear) {
                $q->where('year', 'like', '%' . $academicYear . '%')
                    ->orWhereRaw('LOWER(year) LIKE ?', ['%' . strtolower($academicYear) . '%']);
            });
        }

        $students = $query
            ->when($excludedStudents !== [], function ($q) use ($excludedStudents) {
                // Drop students the administrator removed from the PREVIEW.
                // This only narrows the candidate set for THIS request — no
                // student record is ever modified or deleted.
                $q->whereNotIn('student_number', $excludedStudents);
            })
            ->orderBy('last_name')->get();

        if ($reportMode === 'graduating') {
            $students = $this->sortGraduatingPreview($students, $explicit);
        }

        return $students;
    }

    /**
     * Order the graduating preview for display.
     *
     * Groups, in this order:
     *   1. automatically-eligible NON-ladderized students,
     *   2. automatically-eligible LADDERIZED students,
     *   3. students the administrator added by hand ("Add a Specific Student").
     *
     * Within each group the list is alphabetical by FIRST name, so reading down
     * the column starts at "A".
     *
     * Note on the ladderized filter: when the administrator filters by
     * Ladderized Yes/No, the other automatic group is simply absent from the
     * collection, so the group order degrades naturally to the groups present.
     *
     * Group 3 is keyed off the explicit list rather than `ladderized === null`,
     * because an added student is always kept even when a ladderized filter is
     * active — so it must never be folded into an automatic group.
     *
     * @param  Collection<int, User>  $students
     * @param  array<int, string>     $explicit  student numbers added by hand
     * @return Collection<int, User>
     */
    protected function sortGraduatingPreview(Collection $students, array $explicit): Collection
    {
        $explicitSet = array_flip(array_map('strval', $explicit));

        $rank = static function (User $student) use ($explicitSet): int {
            if (isset($explicitSet[(string) $student->student_number])) {
                return 2; // added by hand
            }

            // `ladderized` is cast to boolean by StudentInfo.
            return $student->studentInfo?->ladderized ? 1 : 0;
        };

        $firstName = static fn (User $student) => mb_strtolower(trim((string) $student->first_name));

        return $students
            ->sortBy(static function (User $student) use ($rank, $firstName): string {
                return $rank($student) . '|' . $firstName($student) . '|' . (string) $student->student_number;
            })
            ->values();
    }

    /**
     * Preview the students that would be included in a graduating report for
     * the given program (no screening — raw list). The graduating cohort is
     * determined by the program's graduating YEAR LEVEL; specific students
     * added manually by number are always included.
     */
    public function previewGraduating(Program $program, array $graduatingYears = [], ?int $ladderized = null, array $additionalStudents = [], array $excludedStudents = []): Collection
    {
        return $this->studentsFor($program, null, 'graduating', $graduatingYears, $ladderized, $additionalStudents, $excludedStudents);
    }

    /**
     * Aggregate factual statistics for the selected program cohort.
     */
    public function aggregate(
        Program $program,
        ?string $academicYear,
        string $reportType = 'full',
        string $reportMode = 'regular',
        array $graduatingYears = [],
        ?int $ladderized = null,
        array $additionalStudents = [],
        array $excludedStudents = []
    ): array {
        $students = $this->studentsFor($program, $academicYear, $reportMode, $graduatingYears, $ladderized, $additionalStudents, $excludedStudents);

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
            'excluded_students' => array_values($excludedStudents),
            'degree_type' => $program->normalizedDegreeType(),
            // graduating_year stores the graduating YEAR LEVEL (4 = Bachelor's
            // / Ladderized Bachelor, 3 = Diploma). Admission years no longer
            // drive graduating eligibility (year level does), so the
            // admission_years JSON column is empty.
            'graduating_year' => $program->graduatingYear(),
            'admission_years' => [],
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
            ->effective()
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
            ->effective()
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
