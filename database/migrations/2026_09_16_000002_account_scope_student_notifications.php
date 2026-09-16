<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ACCOUNT-scoped student notifications.
 *
 * WHY THIS IS NEEDED (existing design cannot enforce ownership safely):
 *
 * `notifications_tbl` already has `recipient_type` + `recipient_id`, and the
 * Security side uses them. Student notifications, however, were written with
 * `recipient_id = NULL` and were queried only by `student_number`:
 *
 *     Notification::where('student_number', $user->student_number)
 *
 * `student_number` is profile data that the student THEMSELVES supplies during
 * first-time registration (PUPTracker has no pre-created student-number
 * roster). It is not the account identity — `users_tbl.idp_subject` is. Scoping
 * private notifications on a self-asserted profile value is weaker than scoping
 * them on the account key, so:
 *
 *   1. New student notifications store `recipient_id = users_tbl.user_id`.
 *   2. This migration BACKFILLS the same key onto existing student rows.
 *   3. Reads are authorised on `recipient_id` (account ownership), with
 *      `student_number` kept only as a legacy fallback for rows whose account
 *      can no longer be resolved.
 *
 * No new table is created: `notifications_tbl` plus its existing `recipient_id`
 * column already express per-account ownership. This migration only adds an
 * index and backfills data, so nothing existing is deleted or renamed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications_tbl') || ! Schema::hasColumn('notifications_tbl', 'recipient_id')) {
            return;
        }

        // Composite index: every account-scoped read filters on
        // (recipient_type, recipient_id).
        $this->addRecipientIndex();

        $this->backfillStudentRecipientIds();
    }

    public function down(): void
    {
        // The backfilled recipient_id values are harmless and are required for
        // correct authorisation, so the data is intentionally left in place.
        // Only the index added by this migration is removed.
        if (! Schema::hasTable('notifications_tbl') || ! Schema::hasColumn('notifications_tbl', 'recipient_id')) {
            return;
        }

        try {
            Schema::table('notifications_tbl', function (Blueprint $table): void {
                $table->dropIndex('notifications_tbl_recipient_type_recipient_id_index');
            });
        } catch (\Throwable $e) {
            // Some engines may name the index differently; leaving it is safe.
        }
    }

    /**
     * Backfill `recipient_id` on existing student notifications from the
     * authoritative users_tbl row. Chunked so a large notifications table does
     * not blow up memory during deploy.
     */
    private function backfillStudentRecipientIds(): void
    {
        if (! Schema::hasTable('users_tbl') || ! Schema::hasColumn('users_tbl', 'student_number')) {
            return;
        }

        // One account per student number. `orderBy('user_id')` + `unique()` makes
        // the LOWEST user_id win, so a pre-existing duplicate cannot cause the
        // backfill to thrash between accounts.
        $accounts = DB::table('users_tbl')
            ->whereNotNull('student_number')
            ->orderBy('user_id')
            ->get(['user_id', 'student_number'])
            ->unique('student_number')
            ->pluck('user_id', 'student_number');

        if ($accounts->isEmpty()) {
            return;
        }

        foreach ($accounts->chunk(500) as $chunk) {
            // `update()` cannot express "set a DIFFERENT value per row" portably,
            // so group by account and update each group with its own value.
            foreach ($chunk as $studentNumber => $userId) {
                DB::table('notifications_tbl')
                    ->where('recipient_type', 'student')
                    ->whereNull('recipient_id')
                    ->where('student_number', $studentNumber)
                    ->update(['recipient_id' => $userId]);
            }
        }
    }

    private function addRecipientIndex(): void
    {
        $indexName = 'notifications_tbl_recipient_type_recipient_id_index';

        try {
            // Already indexed? Check the actual columns (name-agnostic), so a
            // differently-named equivalent index does not get duplicated.
            foreach (Schema::getIndexes('notifications_tbl') as $index) {
                $columns = array_map('strtolower', $index['columns'] ?? []);
                sort($columns);

                if ($columns === ['recipient_id', 'recipient_type']) {
                    return;
                }
            }
        } catch (\Throwable $e) {
            // Driver cannot introspect indexes; fall through and let the
            // create attempt below decide.
        }

        try {
            Schema::table('notifications_tbl', function (Blueprint $table) use ($indexName): void {
                $table->index(['recipient_type', 'recipient_id'], $indexName);
            });
        } catch (\Throwable $e) {
            // An equivalent index already existing is not an error.
        }
    }
};
