<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normalize student_info_tbl.ladderized to tinyint(1).
     *
     * The legacy Railway column was defined as a bit(1) (or similar) column,
     * which rejects the 0/1 integer values Laravel sends (MySQL strict mode:
     * "SQLSTATE[01000] Warning: 1265 Data truncated for column 'ladderized'").
     * Altering it to tinyint(1) NOT NULL DEFAULT 0 makes it accept 0/1.
     */
    public function up(): void
    {
        if (! Schema::hasTable('student_info_tbl')) {
            return;
        }

        if (! Schema::hasColumn('student_info_tbl', 'ladderized')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `student_info_tbl`
                MODIFY `ladderized` TINYINT(1) NOT NULL DEFAULT 0');
        }
    }

    public function down(): void
    {
        // No automatic reversal — restoring the original bit(1) type would
        // reintroduce the truncation error. Left intentionally as a no-op.
    }
};
