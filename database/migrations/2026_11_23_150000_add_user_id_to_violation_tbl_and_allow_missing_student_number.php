<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Temporarily supports student accounts whose student number has not yet
     * been assigned. user_id becomes the stable identity key for violations.
     * Existing rows are backfilled before the new relationship is used.
     */
    public function up(): void
    {
        if (!Schema::hasTable('violation_tbl') || !Schema::hasTable('users_tbl')) {
            throw new RuntimeException('violation_tbl and users_tbl must exist before this migration runs.');
        }

        // Legacy installations use different names for this FK. Discover and
        // remove every FK from violation_tbl.student_number to users_tbl.
        $constraints = DB::select("\n            SELECT CONSTRAINT_NAME\n            FROM information_schema.KEY_COLUMN_USAGE\n            WHERE TABLE_SCHEMA = DATABASE()\n              AND TABLE_NAME = 'violation_tbl'\n              AND COLUMN_NAME = 'student_number'\n              AND REFERENCED_TABLE_NAME = 'users_tbl'\n        ");

        foreach ($constraints as $constraint) {
            $name = str_replace('`', '``', $constraint->CONSTRAINT_NAME);
            DB::statement("ALTER TABLE `violation_tbl` DROP FOREIGN KEY `{$name}`");
        }

        if (!Schema::hasColumn('violation_tbl', 'user_id')) {
            Schema::table('violation_tbl', function (Blueprint $table): void {
                // users_tbl.user_id is a signed INT in the supplied schema.
                $table->integer('user_id')->nullable()->after('violation_id');
                $table->index('user_id', 'violation_tbl_user_id_index');
            });
        }

        // Temporary compatibility: a violation can exist even when this
        // profile field has not yet been assigned. Keep its existing VARCHAR.
        DB::statement('ALTER TABLE `violation_tbl` MODIFY `student_number` VARCHAR(50) NULL');

        // Preserve the relationship for every historical violation that can
        // already be resolved by student number.
        DB::statement("\n            UPDATE `violation_tbl` v\n            INNER JOIN `users_tbl` u ON u.`student_number` = v.`student_number`\n            SET v.`user_id` = u.`user_id`\n            WHERE v.`user_id` IS NULL\n        ");

        $hasUserForeignKey = DB::selectOne("\n            SELECT CONSTRAINT_NAME\n            FROM information_schema.KEY_COLUMN_USAGE\n            WHERE TABLE_SCHEMA = DATABASE()\n              AND TABLE_NAME = 'violation_tbl'\n              AND COLUMN_NAME = 'user_id'\n              AND REFERENCED_TABLE_NAME = 'users_tbl'\n            LIMIT 1\n        ");

        if (!$hasUserForeignKey) {
            Schema::table('violation_tbl', function (Blueprint $table): void {
                $table->foreign('user_id', 'violation_tbl_user_id_foreign')
                    ->references('user_id')->on('users_tbl')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('violation_tbl')) {
            return;
        }

        $constraints = DB::select("\n            SELECT CONSTRAINT_NAME\n            FROM information_schema.KEY_COLUMN_USAGE\n            WHERE TABLE_SCHEMA = DATABASE()\n              AND TABLE_NAME = 'violation_tbl'\n              AND COLUMN_NAME = 'user_id'\n              AND REFERENCED_TABLE_NAME = 'users_tbl'\n        ");

        foreach ($constraints as $constraint) {
            $name = str_replace('`', '``', $constraint->CONSTRAINT_NAME);
            DB::statement("ALTER TABLE `violation_tbl` DROP FOREIGN KEY `{$name}`");
        }

        if (Schema::hasColumn('violation_tbl', 'user_id')) {
            Schema::table('violation_tbl', function (Blueprint $table): void {
                // Keep the index removal explicit because it has a stable name.
                $table->dropIndex('violation_tbl_user_id_index');
                $table->dropColumn('user_id');
            });
        }

        // Do not make the column NOT NULL while records still depend on this
        // temporary exception. The operator must resolve them first.
        $nullCount = (int) DB::table('violation_tbl')->whereNull('student_number')->count();
        if ($nullCount === 0) {
            DB::statement('ALTER TABLE `violation_tbl` MODIFY `student_number` VARCHAR(50) NOT NULL');
        }
    }
};
