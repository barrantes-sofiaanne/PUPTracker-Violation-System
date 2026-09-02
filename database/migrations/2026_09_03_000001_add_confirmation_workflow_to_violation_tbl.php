<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the "admin confirmation" workflow to recorded violations.
 *
 * Security-recorded violations are created with status = 'pending' and are
 * sent to an administrator for confirmation. The administrator may:
 *   - confirm the violation (status = 'confirmed'), or
 *   - deny it (status = 'denied') and provide a required review reason.
 *
 * Denied violations are excluded from offense counts, dashboards, reports,
 * and the student's actionable record (but the row is retained for audit).
 * Existing rows (recorded before this change) have no status; they are
 * treated as confirmed/effective so no existing data silently disappears.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('violation_tbl')) {
            return;
        }

        Schema::table('violation_tbl', function (Blueprint $table): void {
            if (! Schema::hasColumn('violation_tbl', 'status')) {
                $table->string('status', 20)->nullable()->after('recorder_name');
            }
            if (! Schema::hasColumn('violation_tbl', 'reviewed_by_admin_id')) {
                $table->unsignedBigInteger('reviewed_by_admin_id')->nullable()->after('status');
            }
            if (! Schema::hasColumn('violation_tbl', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_admin_id');
            }
            if (! Schema::hasColumn('violation_tbl', 'review_remarks')) {
                $table->text('review_remarks')->nullable()->after('reviewed_at');
            }
        });

        // Backfill: security-recorded rows that pre-date confirmation become
        // 'confirmed' (they were already being counted before this change), so
        // adding the column never makes existing data disappear or change count.
        if (Schema::hasColumn('violation_tbl', 'status') && Schema::hasColumn('violation_tbl', 'recorder_type')) {
            try {
                DB::table('violation_tbl')
                    ->whereNull('status')
                    ->update(['status' => 'confirmed']);
            } catch (\Throwable $e) {
                // Non-destructive backfill; never block the migration on it.
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('violation_tbl')) {
            return;
        }

        $columns = collect(['status', 'reviewed_by_admin_id', 'reviewed_at', 'review_remarks'])
            ->filter(fn (string $column): bool => Schema::hasColumn('violation_tbl', $column))
            ->all();

        if ($columns !== []) {
            Schema::table('violation_tbl', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
