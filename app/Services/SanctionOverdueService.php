<?php

namespace App\Services;

use App\Models\StudentSanctionRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Detects sanctions that have passed their deadline without being completed
 * and flips them to the "Overdue" status (firing a one-time notification).
 *
 * The sweep is idempotent: a record already marked Overdue is skipped, and a
 * notification is only sent on the Pending/In Progress -> Overdue transition,
 * so repeated sweeps never spam the student or the administrators.
 */
class SanctionOverdueService
{
    /**
     * Run the overdue sweep. Returns the number of records newly marked overdue.
     */
    public function sweep(): int
    {
        $now = Carbon::now();

        $candidates = StudentSanctionRecord::query()
            ->whereNotNull('deadline_date')
            ->where(function ($q) {
                $q->where('status', StudentSanctionRecord::STATUS_PENDING)
                    ->orWhere('status', StudentSanctionRecord::STATUS_IN_PROGRESS);
            })
            ->where('deadline_date', '<', $now)
            ->get();

        $flipped = 0;

        foreach ($candidates as $record) {
            // Prevent double notification if two requests race.
            $updated = DB::table('student_sanction_records_tbl')
                ->where('record_id', $record->record_id)
                ->whereIn('status', [StudentSanctionRecord::STATUS_PENDING, StudentSanctionRecord::STATUS_IN_PROGRESS])
                ->update(['status' => StudentSanctionRecord::STATUS_OVERDUE]);

            if ($updated === 1) {
                $flipped++;
                $this->notifyOverdue($record);
            }
        }

        return $flipped;
    }

    /**
     * Notify the student (and any administrator) that a sanction is overdue.
     */
    protected function notifyOverdue(StudentSanctionRecord $record): void
    {
        $studentNumber = (string) $record->student_number;
        $deadline = $record->deadline_date?->format('M d, Y');

        if ($studentNumber === '') {
            return;
        }

        try {
            app(\App\Services\StudentNotificationService::class)->create(
                studentNumber: $studentNumber,
                type: 'sanction',
                title: 'Sanction Overdue',
                message: 'Your disciplinary sanction has passed its deadline (' . ($deadline ?? 'N/A') . ') and has not been marked as completed. Please contact the appropriate administrator.',
                targetType: 'sanction_request',
                targetId: (int) $record->record_id,
            );
        } catch (\Throwable $e) {
            // Notification must never break the sweep.
        }

        try {
            $student = $record->student;
            $studentLabel = $student
                ? trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? ''))
                : $studentNumber;

            app(\App\Services\AdminNotificationService::class)->create(
                type: 'sanction_overdue',
                title: 'Sanction Overdue',
                message: 'Sanction record #' . $record->record_id . ' for student ' . $studentLabel . ' (' . $studentNumber . ') is overdue.',
                targetType: 'sanction_record',
                targetId: (int) $record->record_id,
            );
        } catch (\Throwable $e) {
            // Notification must never break the sweep.
        }
    }
}
