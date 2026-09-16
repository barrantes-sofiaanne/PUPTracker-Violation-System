<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SUPER ADMIN notification separation.
 *
 * PROBLEM
 * -------
 * `admin_notifications_tbl` already distinguishes recipients with
 * `recipient_type` + `recipient_id`, but every application write left
 * `recipient_id` NULL and used the single value `recipient_type = 'admin'`.
 * Because the Super Admin IS an `admins` row, the IT Administrator therefore
 * inherited every ordinary admin notification — including student-specific
 * ones ("John Doe's sanction was approved", violation confirmations, overdue
 * sanctions). Role was accidentally acting as notification ownership.
 *
 * FIX (no new table, no duplicate concept)
 * ----------------------------------------
 * `recipient_type` becomes the AUDIENCE marker:
 *
 *   'admin'        -> operational admins (violation/sanction/student workflow)
 *   'super_admin'  -> system-level alerts only (backup, infrastructure, security)
 *
 * Combined with `recipient_id` this expresses ownership properly:
 *
 *   recipient_type='admin',       recipient_id=NULL  -> every admin EXCEPT super admins
 *   recipient_type='super_admin', recipient_id=NULL  -> every Super Admin
 *   recipient_type='admin'|'super_admin', recipient_id=N -> exactly that account
 *
 * The Super Admin's own inbox is therefore scoped to `super_admin` rows and can
 * never inherit a student/personnel notification.
 *
 * This migration is additive and guarded: priority is only added when absent,
 * and the backfill only touches rows that are still ambiguous (NULL
 * recipient_type), so no existing row is re-pointed or deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_notifications_tbl')) {
            return;
        }

        if (! Schema::hasColumn('admin_notifications_tbl', 'priority')) {
            Schema::table('admin_notifications_tbl', function (Blueprint $table): void {
                $table->string('priority', 16)->nullable()->after('notification_type');
            });
        }

        // Backfill ONLY ambiguous rows (recipient_type still NULL) so the
        // pre-existing broadcast behaviour of those rows is preserved exactly:
        // they were visible to all admins, and they remain visible to all admins.
        // Rows already carrying a type are never rewritten.
        if (Schema::hasColumn('admin_notifications_tbl', 'recipient_type')) {
            DB::table('admin_notifications_tbl')
                ->whereNull('recipient_type')
                ->update(['recipient_type' => 'admin']);
        }

        // Default existing rows to 'info' so priority filtering has a value.
        if (Schema::hasColumn('admin_notifications_tbl', 'priority')) {
            DB::table('admin_notifications_tbl')
                ->whereNull('priority')
                ->update(['priority' => 'info']);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_notifications_tbl')) {
            return;
        }

        if (Schema::hasColumn('admin_notifications_tbl', 'priority')) {
            Schema::table('admin_notifications_tbl', function (Blueprint $table): void {
                $table->dropColumn('priority');
            });
        }

        // The recipient_type backfill is intentionally NOT reverted: setting the
        // rows back to NULL would re-widen their audience to every admin,
        // including Super Admins, which is the behaviour being corrected.
    }
};
