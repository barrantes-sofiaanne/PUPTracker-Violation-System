
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasTable('gender_tbl') ||
            !Schema::hasColumn('gender_tbl', 'gender_name')
        ) {
            return;
        }

        // The label exceeds the original VARCHAR(10) limit.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE `gender_tbl` MODIFY `gender_name` VARCHAR(30) NOT NULL"
            );
        }

        $exists = DB::table('gender_tbl')
            ->whereRaw(
                'LOWER(gender_name) = ?',
                ['prefer not to say']
            )
            ->exists();

        if (!$exists) {
            DB::table('gender_tbl')->insert([
                'gender_name' => 'Prefer not to say',
            ]);
        }
    }

    public function down(): void
    {
        // Retain the option to avoid breaking existing student records
        // that may reference its gender_id.
    }
};
