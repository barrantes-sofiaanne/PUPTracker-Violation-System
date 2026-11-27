<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Exercises the consolidated `violation_tbl` user_id migration against a
 * clean, legacy-shaped SQLite database.
 *
 * The migration is portable: MySQL-specific DDL (dropping the legacy
 * student_number FK, adding the user_id FK) is skipped on other engines, while
 * the schema/data guarantees — identity column, index, backfill, nullable
 * student number, and a non-destructive rollback — are engine-independent and
 * are asserted here.
 */
class ViolationUserIdMigrationTest extends TestCase
{
    private function migration(): object
    {
        static $migration = null;

        if ($migration === null) {
            $migration = require database_path(
                'migrations/2026_11_23_150000_add_user_id_to_violation_tbl_and_allow_missing_student_number.php'
            );
        }

        return $migration;
    }

    private function createLegacySchema(): void
    {
        Schema::create('users_tbl', function (Blueprint $table): void {
            $table->increments('user_id');
            $table->string('student_number', 15)->nullable()->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
        });

        // Legacy shape: identity lived in student_number, which was NOT NULL.
        Schema::create('violation_tbl', function (Blueprint $table): void {
            $table->increments('violation_id');
            $table->string('student_number', 15);
            $table->unsignedInteger('violation_type_id')->nullable();
            $table->dateTime('violation_date')->nullable();
            $table->string('status')->nullable();
        });
    }

    private function seedLegacyData(): void
    {
        DB::table('users_tbl')->insert([
            ['user_id' => 1, 'student_number' => '2026-00001', 'first_name' => 'One', 'last_name' => 'Student'],
            ['user_id' => 2, 'student_number' => '2026-00002', 'first_name' => 'Two', 'last_name' => 'Student'],
            ['user_id' => 3, 'student_number' => null, 'first_name' => 'No', 'last_name' => 'Number'],
        ]);

        DB::table('violation_tbl')->insert([
            // resolvable rows
            ['violation_id' => 1, 'student_number' => '2026-00001', 'violation_type_id' => 1, 'violation_date' => '2026-01-01 09:00:00', 'status' => 'confirmed'],
            ['violation_id' => 2, 'student_number' => '2026-00001', 'violation_type_id' => 1, 'violation_date' => '2026-01-02 09:00:00', 'status' => 'pending'],
            ['violation_id' => 3, 'student_number' => '2026-00002', 'violation_type_id' => 2, 'violation_date' => '2026-01-03 09:00:00', 'status' => 'confirmed'],
            // an orphan number: must never be guessed onto another account
            ['violation_id' => 4, 'student_number' => 'UNKNOWN-999', 'violation_type_id' => 1, 'violation_date' => '2026-01-04 09:00:00', 'status' => 'pending'],
        ]);
    }

    public function test_up_adds_identity_column_backfills_and_keeps_student_numbers(): void
    {
        $this->createLegacySchema();
        $this->seedLegacyData();

        $this->migration()->up();

        $this->assertTrue(Schema::hasColumn('violation_tbl', 'user_id'));

        // Resolved rows are linked to their account, by user_id.
        $this->assertSame(1, (int) DB::table('violation_tbl')->where('violation_id', 1)->value('user_id'));
        $this->assertSame(1, (int) DB::table('violation_tbl')->where('violation_id', 2)->value('user_id'));
        $this->assertSame(2, (int) DB::table('violation_tbl')->where('violation_id', 3)->value('user_id'));

        // An unresolvable number stays NULL rather than being guessed.
        $this->assertNull(DB::table('violation_tbl')->where('violation_id', 4)->value('user_id'));

        // Existing numbers are preserved exactly.
        $this->assertSame('2026-00001', DB::table('violation_tbl')->where('violation_id', 1)->value('student_number'));
        $this->assertSame('UNKNOWN-999', DB::table('violation_tbl')->where('violation_id', 4)->value('student_number'));
        $this->assertSame(4, DB::table('violation_tbl')->count());

        // The identity index exists.
        $indexed = collect(Schema::getIndexes('violation_tbl'))
            ->contains(fn (array $index): bool => array_map('strtolower', $index['columns'] ?? []) === ['user_id']);

        $this->assertTrue($indexed, 'violation_tbl.user_id must be indexed.');
    }

    public function test_student_number_accepts_null_after_the_migration(): void
    {
        $this->createLegacySchema();
        $this->seedLegacyData();

        $this->migration()->up();

        // A violation may now be recorded BEFORE the number is assigned.
        DB::table('violation_tbl')->insert([
            'student_number' => null,
            'violation_type_id' => 1,
            'violation_date' => '2026-02-01 09:00:00',
            'status' => 'pending',
            'user_id' => 3,
        ]);

        $this->assertSame(5, DB::table('violation_tbl')->count());
        $this->assertNull(DB::table('violation_tbl')->where('violation_id', 5)->value('student_number'));
        $this->assertSame(3, (int) DB::table('violation_tbl')->where('violation_id', 5)->value('user_id'));
    }

    public function test_up_is_idempotent(): void
    {
        $this->createLegacySchema();
        $this->seedLegacyData();

        $this->migration()->up();

        // Running the same migration again (e.g. after a partial failure) must
        // converge without duplicating the column/index or damaging data.
        $this->migration()->up();

        $this->assertSame(4, DB::table('violation_tbl')->count());
        $this->assertSame(1, (int) DB::table('violation_tbl')->where('violation_id', 1)->value('user_id'));
        $this->assertSame('2026-00001', DB::table('violation_tbl')->where('violation_id', 1)->value('student_number'));
    }

    public function test_down_removes_the_identity_column_without_losing_numbers(): void
    {
        $this->createLegacySchema();
        $this->seedLegacyData();

        $this->migration()->up();
        $this->migration()->down();

        $this->assertFalse(Schema::hasColumn('violation_tbl', 'user_id'));

        // Data and student numbers survive the rollback.
        $this->assertSame(4, DB::table('violation_tbl')->count());
        $this->assertSame('2026-00001', DB::table('violation_tbl')->where('violation_id', 1)->value('student_number'));
        $this->assertSame('2026-00002', DB::table('violation_tbl')->where('violation_id', 3)->value('student_number'));
    }

    public function test_down_keeps_student_number_nullable_when_null_rows_exist(): void
    {
        $this->createLegacySchema();
        $this->seedLegacyData();

        $this->migration()->up();

        DB::table('violation_tbl')->insert([
            'student_number' => null,
            'violation_type_id' => 1,
            'violation_date' => '2026-02-01 09:00:00',
            'status' => 'pending',
            'user_id' => 3,
        ]);

        $this->migration()->down();

        // Forcing NOT NULL would either fail or invent a value, so the column
        // must stay nullable while a NULL row exists.
        $this->assertNull(DB::table('violation_tbl')->where('violation_id', 5)->value('student_number'));
        $this->assertSame(5, DB::table('violation_tbl')->count());
    }

    public function test_migration_requires_both_tables(): void
    {
        $this->createLegacySchema();
        Schema::drop('users_tbl');

        $this->expectException(\RuntimeException::class);

        $this->migration()->up();
    }
}
