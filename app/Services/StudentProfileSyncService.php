<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps denormalized student-number display snapshots aligned with the
 * canonical `users_tbl.student_number` value.
 *
 * Identity is ALWAYS `users_tbl.user_id`. A student number is display data and
 * may be NULL/empty, so it is never used to decide which account a record
 * belongs to. Updates are scoped to the affected account (directly, or through
 * its violation/sanction records) — never a project-wide update by number.
 */
class StudentProfileSyncService
{
    /**
     * Synchronize one student's display snapshots after their student number
     * changes. Runs inside the caller's transaction.
     *
     * @return array<string, int>  Table => rows updated.
     */
    public function synchronizeStudentNumber(
        User $student,
        ?string $newStudentNumber,
        ?string $previousStudentNumber = null
    ): array {
        $userId = (int) $student->user_id;
        $new = $this->normalize($newStudentNumber);
        $old = $this->normalize($previousStudentNumber);

        if ($userId <= 0) {
            return [];
        }

        $updated = [];

        // Capture the affected sanction-record ids BEFORE their own snapshot is
        // rewritten, so proofs/history belonging to them are scoped correctly.
        $sanctionRecordIds = $this->sanctionRecordIdsFor($userId, $old);

        // 1. Violations are owned by user_id.
        if ($this->has('violation_tbl', 'student_number') && $this->has('violation_tbl', 'user_id')) {
            $updated['violation_tbl'] = DB::table('violation_tbl')
                ->where('user_id', $userId)
                ->update(['student_number' => $new]);
        }

        // 2. Sanction records follow their violation (or the old number when the
        //    record is not linked to a violation).
        if ($sanctionRecordIds->isNotEmpty() && $this->has('student_sanction_records_tbl', 'student_number')) {
            $updated['student_sanction_records_tbl'] = DB::table('student_sanction_records_tbl')
                ->whereIn('record_id', $sanctionRecordIds->all())
                ->update(['student_number' => $new]);
        }

        // 3. Proof submissions belong to a sanction record.
        if ($sanctionRecordIds->isNotEmpty()
            && $this->has('sanction_proofs_tbl', 'student_number')
            && $this->has('sanction_proofs_tbl', 'record_id')) {
            $updated['sanction_proofs_tbl'] = DB::table('sanction_proofs_tbl')
                ->whereIn('record_id', $sanctionRecordIds->all())
                ->update(['student_number' => $new]);
        }

        // 4. Sanction compliance history belongs to a sanction record.
        if ($sanctionRecordIds->isNotEmpty()
            && $this->has('sanction_compliance_history', 'student_number')
            && $this->has('sanction_compliance_history', 'record_id')) {
            $updated['sanction_compliance_history'] = DB::table('sanction_compliance_history')
                ->whereIn('record_id', $sanctionRecordIds->all())
                ->update(['student_number' => $new]);
        }

        // 5. Violation correction/deletion requests follow their violation and
        //    retain the student number snapshot.
        $canScopeCorrections = ($this->has('violation_correction_requests', 'violation_id')
                && $this->has('violation_tbl', 'violation_id')
                && $this->has('violation_tbl', 'user_id'))
            || ($old !== null && $old !== '');

        if ($canScopeCorrections && $this->has('violation_correction_requests', 'student_number')) {
            $query = DB::table('violation_correction_requests');

            $query->where(function ($inner) use ($userId, $old): void {
                $matched = false;

                if ($this->has('violation_correction_requests', 'violation_id')
                    && $this->has('violation_tbl', 'violation_id')
                    && $this->has('violation_tbl', 'user_id')) {
                    $inner->whereIn('violation_id', function ($sub) use ($userId): void {
                        $sub->select('violation_id')
                            ->from('violation_tbl')
                            ->where('user_id', $userId);
                    });
                    $matched = true;
                }

                if ($old !== null && $old !== '') {
                    $matched
                        ? $inner->orWhere('student_number', $old)
                        : $inner->where('student_number', $old);
                }
            });

            $updated['violation_correction_requests'] = $query->update([
                'student_number' => $new,
            ]);
        }

        // 6. Notifications: recipient_id is the canonical account key; legacy
        //    rows fall back to the old number.
        $canScopeNotifications = $this->has('notifications_tbl', 'recipient_id')
            || ($old !== null && $old !== '');

        if ($canScopeNotifications && $this->has('notifications_tbl', 'student_number')) {
            $query = DB::table('notifications_tbl');

            $query->where(function ($inner) use ($userId, $old): void {
                $matched = false;

                if ($this->has('notifications_tbl', 'recipient_id')) {
                    $inner->where('recipient_id', $userId);
                    $matched = true;
                }

                if ($old !== null && $old !== '') {
                    $legacy = function ($legacyQuery) use ($old): void {
                        $legacyQuery->where('student_number', $old);

                        if ($this->has('notifications_tbl', 'recipient_id')) {
                            $legacyQuery->whereNull('recipient_id');
                        }
                    };

                    $matched
                        ? $inner->orWhere($legacy)
                        : $inner->where($legacy);
                }
            });

            $updated['notifications_tbl'] = $query->update([
                'student_number' => $new,
            ]);
        }

        // 7. Sanction requests only store the student number (no user_id).
        //    Updating by the old number is safe: users_tbl enforces uniqueness
        //    for non-NULL student numbers.
        if ($this->has('sanction_requests_tbl', 'student_number')
            && $old !== null && $old !== '') {
            $updated['sanction_requests_tbl'] = DB::table('sanction_requests_tbl')
                ->where('student_number', $old)
                ->update(['student_number' => $new]);
        }

        // 8. Saved AI graduation reviews are historical reports keyed by the
        //    student number; keep them viewable under the updated number.
        if ($this->has('ai_graduation_reviews', 'student_number')
            && $old !== null && $old !== '') {
            $updated['ai_graduation_reviews'] = DB::table('ai_graduation_reviews')
                ->where('student_number', $old)
                ->update(['student_number' => $new]);
        }

        return array_filter($updated, fn ($count) => $count > 0);
    }

    /**
     * Sanction record ids that belong to this account, resolved through its
     * violations first and the previous display number second.
     *
     * @return Collection<int, int>
     */
    private function sanctionRecordIdsFor(int $userId, ?string $oldStudentNumber): Collection
    {
        if (! $this->has('student_sanction_records_tbl', 'record_id')) {
            return collect();
        }

        $canScopeByViolation = $this->has('violation_tbl', 'violation_id')
            && $this->has('violation_tbl', 'user_id')
            && $this->has('student_sanction_records_tbl', 'violation_id');

        $canScopeByNumber = $oldStudentNumber !== null
            && $oldStudentNumber !== ''
            && $this->has('student_sanction_records_tbl', 'student_number');

        if (! $canScopeByViolation && ! $canScopeByNumber) {
            return collect();
        }

        $query = DB::table('student_sanction_records_tbl')->select('record_id');

        $query->where(function ($inner) use ($userId, $oldStudentNumber): void {
            $matched = false;

            if ($this->has('violation_tbl', 'violation_id')
                && $this->has('violation_tbl', 'user_id')
                && $this->has('student_sanction_records_tbl', 'violation_id')) {
                $inner->whereIn('violation_id', function ($sub) use ($userId): void {
                    $sub->select('violation_id')
                        ->from('violation_tbl')
                        ->where('user_id', $userId);
                });
                $matched = true;
            }

            if ($oldStudentNumber !== null && $oldStudentNumber !== ''
                && $this->has('student_sanction_records_tbl', 'student_number')) {
                $matched
                    ? $inner->orWhere('student_number', $oldStudentNumber)
                    : $inner->where('student_number', $oldStudentNumber);
            }
        });

        return $query->pluck('record_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values();
    }

    private function normalize(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function has(string $table, string $column): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
    }
}
