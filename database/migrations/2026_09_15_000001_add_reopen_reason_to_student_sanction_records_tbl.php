<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records WHY a completed sanction was reopened.
 *
 * Reopening is an administrative override, so the reason is required by
 * validation and kept on the record itself (not only in the audit log) so it
 * stays visible next to the sanction it applies to.
 *
 * Additive and idempotent: existing rows keep NULL, nothing is backfilled and
 * no existing column is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_sanction_records_tbl')) {
            return;
        }

        Schema::table('student_sanction_records_tbl', function (Blueprint $table): void {
            if (! Schema::hasColumn('student_sanction_records_tbl', 'reopen_reason')) {
                $table->text('reopen_reason')->nullable()->after('completion_remarks');
            }

            if (! Schema::hasColumn('student_sanction_records_tbl', 'reopened_at')) {
                $table->timestamp('reopened_at')->nullable()->after('reopen_reason');
            }

            if (! Schema::hasColumn('student_sanction_records_tbl', 'reopened_by_admin_id')) {
                $table->unsignedBigInteger('reopened_by_admin_id')->nullable()->after('reopened_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('student_sanction_records_tbl')) {
            return;
        }

        Schema::table('student_sanction_records_tbl', function (Blueprint $table): void {
            foreach (['reopened_by_admin_id', 'reopened_at', 'reopen_reason'] as $column) {
                if (Schema::hasColumn('student_sanction_records_tbl', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
