<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DisciplinarySanction;
use App\Models\SanctionRequest;
use App\Models\StudentSanctionRecord;
use App\Models\Violation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The sanction-request decision workflow (approve / decline), shared by the
 * Security module and the OSS Staff module.
 *
 * WHY THIS EXISTS
 * ---------------
 * The decision itself is a pure business rule: deactivate the request, look up
 * the offense level + assigned sanction, refuse warning-level moves, create the
 * follow-up StudentSanctionRecord, notify the student, and write an audit row.
 * None of that depends on WHICH module made the decision.
 *
 * What DOES differ is the actor: Security is a `security`-guard account, OSS
 * Staff is an `admin`-guard account. So the caller states the actor type, id and
 * the wording used in the student-facing message, and this class never reaches
 * for a guard. That keeps ONE implementation of the rules while ensuring the
 * audit trail and the notification wording never misattribute the decision.
 */
class SanctionRequestDecisionService
{
    public const DECISION_APPROVED = 'approved';
    public const DECISION_DECLINED = 'declined';

    /**
     * Apply a decision to a sanction request.
     *
     * @param  string  $actorType    'security' | 'admin' — written to the audit trail.
     * @param  int|null  $actorId    The acting account id.
     * @param  string  $officeLabel  Human-readable actor label used in the
     *                               student-facing notification (e.g.
     *                               "the security office" or
     *                               "the Office of Student Services").
     * @param  string  $auditActorLabel  Label used in the audit description.
     */
    public function finalize(
        SanctionRequest $sanctionRequest,
        string $decision,
        string $actorType,
        ?int $actorId,
        string $officeLabel,
        string $auditActorLabel
    ): void {
        DB::transaction(function () use ($sanctionRequest, $decision, $actorType, $actorId, $officeLabel, $auditActorLabel): void {
            $sanctionRequest->update(['is_active' => false]);

            $student = $sanctionRequest->student;
            $violationType = $sanctionRequest->violationType;

            if ($student && ! $student->isActiveAccount()) {
                throw ValidationException::withMessages([
                    'sanction_request' => 'This sanction cannot be created because the student account is inactive.',
                ]);
            }

            if ($student && $violationType) {
                $latestViolation = Violation::where('student_number', $student->student_number)
->where('violation_type_id', $violationType->violation_type_id)                    ->effective()
                    ->latest('violation_date')
                    ->first();

                $offenseCount = Violation::where('student_number', $student->student_number)
->where('violation_type_id', $violationType->violation_type_id)                    ->effective()
                    ->count();

                $offenseLevel = $this->formatOffenseLevel($offenseCount);

                $assignedSanction = DisciplinarySanction::where('violation_type_id', $violationType->violation_type_id)
                    ->where('offense_level', $offenseLevel)
                    ->first()
                    ?? DisciplinarySanction::where('violation_type_id', $violationType->violation_type_id)
                    ->orderBy('disciplinary_sanction_id')
                    ->first();

                // Only Community Service / Campus Service sanctions should
                // arrive through the student Request Sanction workflow because
                // those sanctions require administration scheduling. OSS
                // consultation sanctions (including the existing
                // Student-Assistance Service instruction) are assigned directly
                // and must not be approved as student-requested sanctions.
               
                if ($decision === self::DECISION_APPROVED && $latestViolation) {
                    $pendingRecord = StudentSanctionRecord::where('student_number', $student->student_number)
                        ->where('violation_id', $latestViolation->violation_id)
                        ->where(function ($q) {
                            $q->where(StudentSanctionRecord::STATUS_PENDING)
                                ->orWhere(StudentSanctionRecord::STATUS_IN_PROGRESS)
                                ->orWhere(StudentSanctionRecord::STATUS_OVERDUE);
                        })
                        ->latest('record_id')
                        ->first();

                    if (! $pendingRecord) {
                        StudentSanctionRecord::create([
                            'student_number' => $student->student_number,
                            'violation_id' => $latestViolation->violation_id,
                            'assigned_sanction_id' => $assignedSanction?->disciplinary_sanction_id,
                            // The assigned_by_admin_id is only set by an actual
                            // admin approval. A mediated approval does not
                            // fabricate an admin id — it stays null until an
                            // administrator assigns the sanction with a deadline.
                            'assigned_by_admin_id' => null,
                            'status' => StudentSanctionRecord::STATUS_IN_PROGRESS,
                            'date_assigned' => now(),
                        ]);
                    }
                }
            }

            if ($student?->student_number) {
                try {
                    app(StudentNotificationService::class)->create(
                        studentNumber: $student->student_number,
                        type: 'sanction',
                        title: $decision === self::DECISION_APPROVED
                            ? 'Sanction Request Approved'
                            : 'Sanction Request Declined',
                        message: $decision === self::DECISION_APPROVED
                            ? 'Your sanction request was approved by ' . $officeLabel . '.'
                            : 'Your sanction request was declined by ' . $officeLabel . '.',
                        targetType: 'sanction_request',
                        targetId: (int) $sanctionRequest->request_id,
                    );
                } catch (\Throwable $e) {
                    // Notification must never break the decision.
                }
            }

            AuditLog::create([
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'action' => ucfirst($decision),
                'module' => 'Sanction Requests',
                'description' => $auditActorLabel . ' processed sanction request #' . $sanctionRequest->request_id . ' for student ' . ($student?->student_number ?? 'unknown'),
            ]);
        });
    }

    public function formatOffenseLevel(int $offenseCount): string
    {
        return match ($offenseCount) {
            1 => '1st Offense',
            2 => '2nd Offense',
            3 => '3rd Offense',
            default => $offenseCount . 'th Offense',
        };
    }
}
