<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Student numbers are now COLLECTED during first-time self-registration instead
 * of being matched against an administrator-created roster, so the database
 * itself must guarantee "one account per student number" rather than relying on
 * the application-level `unique:users_tbl,student_number` rule alone.
 *
 * This closes the double-submit race: two simultaneous registrations with the
 * same number can both pass the application check but only one can win here.
 *
 * The migration is idempotent and safe to run on a database that already has
 * duplicate student numbers: duplicates are reported and the index is skipped
 * rather than leaving the schema half-changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users_tbl') || ! Schema::hasColumn('users_tbl', 'student_number')) {
            return;
        }

        if ($this->hasUniqueIndexOnStudentNumber()) {
            return;
        }

        // Never build a unique index over existing duplicates — MySQL would
        // reject the ALTER and SQLite would fail the whole migration.
        $duplicates = DB::table('users_tbl')
            ->select('student_number')
            ->whereNotNull('student_number')
            ->groupBy('student_number')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('student_number');

        if ($duplicates->isNotEmpty()) {
            // Surfacing this is better than silently skipping: an operator needs
            // to resolve the duplicates before the constraint can be added.
            throw new RuntimeException(
                'Cannot add the unique student_number index: duplicate values exist for '
                . $duplicates->count()
                . ' student number(s): ' . $duplicates->take(10)->implode(', ')
            );
        }

        Schema::table('users_tbl', function (Blueprint $table): void {
            $table->unique('student_number', 'users_tbl_student_number_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users_tbl') || ! $this->hasUniqueIndexOnStudentNumber()) {
            return;
        }

        Schema::table('users_tbl', function (Blueprint $table): void {
            $table->dropUnique('users_tbl_student_number_unique');
        });
    }

    /**
     * Detect an existing unique index covering `student_number`.
     *
     * Laravel's Schema builder has no portable "index exists" helper, so the
     * driver is queried directly. Both MySQL and SQLite are supported here
     * because the test suite runs on SQLite in-memory.
     */
    private function hasUniqueIndexOnStudentNumber(): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('users_tbl')");

            foreach ($indexes as $index) {
                if ((int) ($index->unique ?? 0) !== 1) {
                    continue;
                }

                $columns = DB::select("PRAGMA index_info('" . $index->name . "')");

                if (count($columns) === 1 && ($columns[0]->name ?? null) === 'student_number') {
                    return true;
                }
            }

            return false;
        }

        $database = Schema::getConnection()->getDatabaseName();

        $rows = DB::select(
            'SELECT INDEX_NAME AS name FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND NON_UNIQUE = 0',
            [$database, 'users_tbl', 'student_number']
        );

        return count($rows) > 0;
    }
};
