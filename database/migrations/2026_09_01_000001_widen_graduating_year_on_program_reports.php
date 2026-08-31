<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix the graduating_year column so it can hold a 4-digit admission year.
     *
     * The column was originally created as unsignedTinyInteger (max 255) to
     * store a year LEVEL (3 or 4). Now it stores the admission year (e.g. 2022,
     * 2025) which exceeds the tinyint range — so widen it to an unsigned int.
     *
     * Uses raw SQL (doctrine/dbal is not installed, so ->change() is unusable).
     *
     * The full list of admission years is stored separately in the
     * admission_years JSON column.
     */
    public function up(): void
    {
        if (! Schema::hasTable('program_reports') || ! Schema::hasColumn('program_reports', 'graduating_year')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE program_reports MODIFY graduating_year INT UNSIGNED NULL');
        }
        // SQLite: leave as-is; the application stores ints that fit fine.
    }

    public function down(): void
    {
        // Intentionally a no-op: reverting the type is not required and could
        // destroy data already stored as 4-digit admission years.
    }
};
