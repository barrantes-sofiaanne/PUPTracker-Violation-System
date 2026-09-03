<?php

namespace App\Services;

use App\Models\StudentSanctionRecord;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Support\Collection;

/**
 * Deterministic disciplinary screening for the AI-Assisted Graduation
 * Disciplinary Review.
 *
 * This service ONLY computes factual information directly from the database.
 * It never calls the AI and never makes graduation/Good Moral decisions.
 */
class GraduationReviewScreeningService
{
    /**
     * Resolve the graduating student cohort from the configured filter.
     */
    public function graduatingStudentsQuery()
    {
        $query = User::query()
            ->with([
                'studentInfo.program',
                'studentInfo.year',
                'studentInfo.section',
                'studentInfo.studentStatus',
                'status',
            ])
            ->where('roles_id', 2); // Student role

        $programIds = (array) config('graduation_review.graduating.program_ids', []);
        if ($programIds !== []) {
            $query->whereHas('studentInfo', fn ($q) => $q->whereIn('program_id', $programIds));
        }

        $yearIds = (array) config('graduation_review.graduating.year_ids', []);
        if ($yearIds !== []) {
            $query->whereHas('studentInfo', fn ($q) => $q->whereIn('year_id', $yearIds));
        }

        $statusName = trim((string) config('graduation_review.graduating.student_status', ''));
        if ($statusName !== '') {
            $query->whereHas('studentInfo.studentStatus', function ($q) use ($statusName) {
                $q->whereRaw('LOWER(status_name) LIKE ?', ['%' . strtolower($statusName) . '%']);
            });
        }

        return $query;
    }

    /**
     * Build a screening result for one student.
     *
     * All numbers are derived from the database. No AI involved.
     */
    public function screenStudent(User $student): array
    {
        // Chronological ascending (oldest → newest) so the AI can analyze
        // frequency, gaps between incidents, escalation, and improvement.
        $violations = Violation::query()
            ->with(['violationType.violationCategory'])
            ->where('student_number', $student->student_number)
            ->effective()
            ->orderBy('violation_date')
            ->orderBy('violation_id')
            ->get();

        $sanctionRecords = StudentSanctionRecord::query()
            ->with(['violation.violationType', 'assignedSanction'])
            ->where('student_number', $student->student_number)
            ->orderBy('date_assigned')
            ->orderBy('record_id')
            ->get();

        $sanctionsByStatus = $sanctionRecords->groupBy(function ($record) {
            return strtolower(trim((string) $record->status));
        });

        $completed = $sanctionsByStatus->get('completed', collect())->count();
        // Open / not-yet-completed sanctions include Pending, In Progress and
        // Overdue records. Any of them keeps the student from being "clear".
        $pending = $sanctionsByStatus->filter(function ($group, $status) {
            return in_array($status, ['pending', 'in progress', 'overdue'], true);
        })->sum(function ($group) {
            return $group->count();
        });
        $outstanding = $pending; // Open sanction = outstanding/not yet completed.

        $mostRecentViolation = $violations->last();

        $patternCounts = $violations
            ->map(function (\App\Models\Violation $violation) {
                $type = $violation->resolvedViolationType();

                return $type?->violation_type_id
                    ? 'id:' . $type->violation_type_id
                    : 'raw:' . strtolower(trim((string) $violation->violation_type));
            })
            ->countBy();

        $repeatedViolations = $patternCounts->filter(fn ($count) => $count > 1)->isNotEmpty();

        $categoryCounts = $violations
            ->map(fn (\App\Models\Violation $violation) => strtolower((string) ($violation->violationType?->violationCategory?->category_name ?? $violation->getViolationCategoryDisplayAttribute() ?? 'uncategorized')))
            ->countBy();

        $repeatedCategories = $categoryCounts->filter(fn ($count) => $count > 1)->isNotEmpty();

        $requiresReview = $outstanding > 0 || $repeatedViolations;

        // Data fingerprint = deterministic hash of the student's disciplinary
        // state. If it changes, any existing AI review is "outdated".
        $fingerprint = $this->fingerprint($violations, $sanctionRecords);

        // Contradiction detection: screening summary vs raw records.
        $contradictions = $this->detectContradictions($completed, $pending, $sanctionRecords);

        return [
            'student_number' => $student->student_number,
            'student_name' => trim(implode(' ', array_filter([
                $student->first_name,
                $student->middle_name,
                $student->last_name,
            ]))),
            'program' => $student->program?->program_name ?? $student->studentInfo?->program?->program_name ?? 'N/A',
            'year' => $student->year?->year ?? $student->studentInfo?->year?->year ?? 'N/A',
            'section' => $student->section?->section_name ?? $student->studentInfo?->section?->section_name ?? 'N/A',
            'total_violations' => $violations->count(),
            'total_sanction_records' => $sanctionRecords->count(),
            'completed_sanctions' => $completed,
            'pending_sanctions' => $pending,
            'outstanding_sanctions' => $outstanding,
            'most_recent_violation_date' => $mostRecentViolation?->violation_date?->toDateString(),
            'most_recent_violation_type' => $mostRecentViolation
                ? ($mostRecentViolation->violationTypeDisplay ?? $mostRecentViolation->violation_type)
                : null,
            'repeated_violations_exist' => $repeatedViolations,
            'repeated_categories_exist' => $repeatedCategories,
            'violation_periods' => $this->academicPeriods($violations),
            'data_fingerprint' => $fingerprint,
            'data_contradictions' => $contradictions,
            'requires_factual_review' => $requiresReview,
            'violations' => $this->violationsForPrompt($violations),
            'sanction_records' => $this->sanctionsForPrompt($sanctionRecords),
        ];
    }

    /**
     * Deterministic hash of the disciplinary state (for change detection).
     */
    protected function fingerprint($violations, $sanctionRecords): string
    {
        $payload = [
            'violations' => $violations->map(fn ($v) => [
                $v->violation_id,
                (string) $v->violation_type,
                $v->violation_date?->toDateString(),
                (string) $v->description,
            ])->all(),
            'sanctions' => $sanctionRecords->map(fn ($r) => [
                $r->record_id,
                $r->violation_id,
                $r->assigned_sanction_id,
                (string) $r->status,
                $r->date_assigned?->toDateString(),
                $r->date_completed?->toDateString(),
                $r->deadline_date?->toDateString(),
            ])->all(),
        ];

        return hash('sha256', json_encode($payload));
    }

    /**
     * Detect factual contradictions between the screening summary and records.
     */
    protected function detectContradictions(int $completed, int $pending, $sanctionRecords): array
    {
        $contradictions = [];

        $rawCompleted = $sanctionRecords->filter(
            fn ($r) => strtolower(trim((string) $r->status)) === 'completed'
        )->count();
        // Open = Pending + In Progress + Overdue (mirrors the summary counter).
        $rawPending = $sanctionRecords->filter(function ($r) {
            return in_array(strtolower(trim((string) $r->status)), ['pending', 'in progress', 'overdue'], true);
        })->count();

        if ($completed !== $rawCompleted) {
            $contradictions[] = "Screening reports {$completed} completed sanction(s), but {$rawCompleted} record(s) are marked completed.";
        }

        if ($pending !== $rawPending) {
            $contradictions[] = "Screening reports {$pending} open sanction(s), but {$rawPending} record(s) are not completed.";
        }

        return $contradictions;
    }

    /**
     * Distribute violation dates across academic periods (year buckets).
     */
    protected function academicPeriods($violations): array
    {
        return $violations
            ->map(fn ($v) => $v->violation_date?->year ? (string) $v->violation_date->year : 'unknown')
            ->countBy()
            ->sortKeys()
            ->map(fn ($count, $year) => ['period' => $year, 'count' => $count])
            ->values()
            ->all();
    }

    /**
     * Compact representation of violations for the AI prompt. Includes only the
     * minimum necessary facts (reference, type, date, description summary).
     */
    protected function violationsForPrompt(Collection $violations): array
    {
        return $violations->map(function ($violation) {
            return [
                'ref' => $violation->violation_id,
                'type' => $violation->violationTypeDisplay ?? $violation->violation_type,
                'date' => $violation->violation_date?->toDateString(),
                'description' => mb_substr((string) $violation->description, 0, 200),
            ];
        })->values()->all();
    }

    /**
     * Compact representation of sanction records for the AI prompt.
     */
    protected function sanctionsForPrompt(Collection $records): array
    {
        return $records->map(function ($record) {
            return [
                'ref' => $record->record_id,
                'violation_ref' => $record->violation_id,
                'sanction' => $record->assignedSanction?->disciplinary_sanction ?? 'Unspecified sanction',
                'status' => $record->status,
                'date_assigned' => $record->date_assigned?->toDateString(),
                'date_completed' => $record->date_completed?->toDateString(),
                'deadline' => $record->deadline_date?->toDateString(),
            ];
        })->values()->all();
    }
}
