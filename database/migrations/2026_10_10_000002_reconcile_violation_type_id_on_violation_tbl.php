<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reconcile the violation schema across environments.
     *
     * Some deployed databases already have violation_type_id, while others
     * still have only the legacy violation_type column containing numeric IDs.
     * Keep the legacy column untouched so existing data remains recoverable.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('violation_tbl', 'violation_type_id')) {
            Schema::table('violation_tbl', function (Blueprint $table): void {
                // Match violation_type_tbl.violation_type_id (INT, signed).
                $table->integer('violation_type_id')->nullable()->after('violation_type');
            });
        }

        // Add a supporting index only if this environment does not already have it.
        $indexExists = collect(DB::select('SHOW INDEX FROM `violation_tbl`'))
            ->contains(fn ($index) => $index->Key_name === 'violation_tbl_violation_type_id_index');

        if (!$indexExists) {
            Schema::table('violation_tbl', function (Blueprint $table): void {
                $table->index('violation_type_id', 'violation_tbl_violation_type_id_index');
            });
        }

        // Legacy values are numeric strings (e.g. "8", "14"). Backfill only
        // when they resolve to a real lookup ID; leave unmatched rows untouched.
        if (Schema::hasColumn('violation_tbl', 'violation_type')) {
            DB::statement(<<<'SQL'
                UPDATE `violation_tbl` AS v
                INNER JOIN `violation_type_tbl` AS vt
                    ON v.`violation_type` REGEXP '^[0-9]+$'
                   AND vt.`violation_type_id` = CAST(v.`violation_type` AS UNSIGNED)
                SET v.`violation_type_id` = vt.`violation_type_id`
                WHERE v.`violation_type_id` IS NULL
            SQL);
        }
    }

    /**
     * Intentionally non-destructive: this migration may run in environments
     * where violation_type_id existed before this migration. Automatically
     * dropping it on rollback could remove application data/schema that this
     * migration did not create. Remove it manually only after environment-specific
     * verification if a rollback is ever required.
     */
    public function down(): void
    {
        // No-op by design to protect pre-existing columns and their data.
    }
};
