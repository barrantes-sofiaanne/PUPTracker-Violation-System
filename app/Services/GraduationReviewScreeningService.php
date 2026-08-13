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
        $violations = Violation::query()
            ->with(['violationType.violationCategory'])
            ->where('student_number', $student->student_number)
            ->orderByDesc('violation_date')
            ->get();

        $sanctionRecords = StudentSanctionRecord::query()
            ->with(['violation.violationType', 'assignedSanction'])
            ->where('student_number', $student->student_number)
            ->get();

        $sanctionsByStatus = $sanctionRecords->groupBy(function ($record) {
            return strtolower(trim((string) $record->status));
        });

        $completed = $sanctionsByStatus->get('completed', collect())->count();
        $pending = $sanctionsByStatus->get('pending', collect())->count();
        $outstanding = $pending; // Pending sanction = outstanding/not yet completed.

        $mostRecentViolation = $violations->first();

        $patternCounts = $violations
            ->map(function ($violation) {
                $type = $violation->resolvedViolationType();

                return $type?->violation_type_id
                    ? 'id:' . $type->violation_type_id
                    : 'raw:' . strtolower(trim((string) $violation->violation_type));
            })
            ->countBy();

        $repeatedViolations = $patternCounts->filter(fn ($count) => $count > 1)->isNotEmpty();

        $requiresReview = $outstanding > 0 || $repeatedViolations;

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
            'requires_factual_review' => $requiresReview,
            'violations' => $this->violationsForPrompt($violations),
            'sanction_records' => $this->sanctionsForPrompt($sanctionRecords),
        ];
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
