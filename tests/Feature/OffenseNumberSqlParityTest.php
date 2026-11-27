<?php

namespace Tests\Feature;

use App\Models\Violation;
use App\Services\OffenseNumberService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Proves that the SQL offense-number expression used by the Admin confirmation
 * queue produces the SAME number as the PHP OffenseNumberService — including
 * legacy rows that carry their type only in the raw `violation_type` column.
 *
 * Each test rebuilds the schema in the shape under test:
 *   • modern   — violation_type_id only (the live production shape);
 *   • legacy   — both columns (its legacy/backfill shape);
 *   • raw-only — raw violation_type only;
 *   • neither  — the expression must never group unrelated rows.
 */
class OffenseNumberSqlParityTest extends TestCase
{
    // ==================================================================
    // Parity — full legacy support (FK + raw column)
    // ==================================================================

    public function test_sql_and_php_agree_for_modern_legacy_and_mixed_records(): void
    {
        $this->bootSchema(withTypeId: true, withRawType: true);

        $rows = [
            // Modern FK rows (one confirmed, one pending).
            [1, 1, '1', '2026-01-01 09:00:00', 'confirmed'],
            [1, 1, '1', '2026-01-02 09:00:00', 'pending'],
            // Legacy rows: numeric id in the raw column, and a TYPE NAME.
            [1, null, '1', '2026-01-03 09:00:00', 'pending'],
            [1, null, 'dress code', '2026-01-04 09:00:00', 'pending'],
            // A different type for the same student.
            [1, 2, '2', '2026-01-01 09:00:00', 'pending'],
            // A different student with the same type.
            [2, 1, '1', '2026-01-01 09:00:00', 'pending'],
        ];

        $this->seedViolations($rows);

        $this->assertParity([1, 2]);

        // Explicit expectations for the primary student/type sequence.
        $php = $this->phpNumbers(1, 1);
        $this->assertSame([1, 2, 3, 4], array_values($php), 'Modern + legacy rows must share one chronological sequence.');
    }

    public function test_pending_confirmed_and_null_status_count_while_denied_and_deleted_do_not(): void
    {
        $this->bootSchema(withTypeId: true, withRawType: true);

        $this->seedViolations([
            [1, 1, '1', '2026-01-01 09:00:00', 'pending'],
            [1, 1, '1', '2026-01-02 09:00:00', 'confirmed'],
            [1, null, '1', '2026-01-03 09:00:00', null],
            [1, 1, '1', '2026-01-04 09:00:00', 'denied'],
            [1, 1, '1', '2026-01-05 09:00:00', 'deleted'],
            [1, null, 'dress code', '2026-01-06 09:00:00', 'denied'],
        ]);

        $this->assertParity([1, 2]);

        $php = $this->phpNumbers(1, 1);
        $this->assertCount(3, $php, 'Only pending/confirmed/NULL rows occupy a slot.');
        $this->assertSame([1, 2, 3], array_values($php));

        $sql = $this->sqlNumbers();
        foreach ([4, 5, 6] as $excludedId) {
            $this->assertSame(0, $sql[$excludedId], "Denied/deleted row {$excludedId} must report 0.");
        }
    }

    public function test_duplicate_and_null_dates_order_identically_in_both_implementations(): void
    {
        $this->bootSchema(withTypeId: true, withRawType: true);

        // Insertion order deliberately differs from chronological order.
        $this->seedViolations([
            [1, 1, '1', '2026-01-05 09:00:00', 'pending'],
            [1, 1, '1', '2026-01-02 09:00:00', 'pending'], // tie A (lower id)
            [1, null, '1', '2026-01-02 09:00:00', 'pending'], // tie B (higher id)
            [1, 1, '1', null, 'pending'], // NULL date sorts first
        ]);

        $this->assertParity([1, 2]);

        $sql = $this->sqlNumbers();
        $php = $this->phpNumbers(1, 1);

        $this->assertSame(1, $sql[4], 'A NULL violation_date must sort first.');
        $this->assertSame(2, $sql[2]);
        $this->assertSame(3, $sql[3], 'Duplicate dates are broken by violation_id.');

        foreach ($php as $id => $number) {
            $this->assertSame($number, $sql[$id], "Ordering mismatch for violation {$id}.");
        }
    }

    public function test_a_row_with_both_a_type_id_and_a_raw_value_is_counted_once(): void
    {
        $this->bootSchema(withTypeId: true, withRawType: true);

        $this->seedViolations([
            [1, 1, '1', '2026-01-01 09:00:00', 'pending'],
            // The raw value contradicts the FK; the FK wins and the row is
            // still counted exactly once.
            [1, 1, '999', '2026-01-02 09:00:00', 'pending'],
        ]);

        $this->assertParity([1]);

        $php = $this->phpNumbers(1, 1);
        $this->assertSame([1, 2], array_values($php), 'No row may occupy two slots.');

        $sql = $this->sqlNumbers();
        $this->assertSame(2, $sql[2]);
    }

    public function test_unresolvable_legacy_rows_are_excluded_from_both_implementations(): void
    {
        $this->bootSchema(withTypeId: true, withRawType: true);

        $this->seedViolations([
            [1, 1, '1', '2026-01-01 09:00:00', 'pending'],
            [1, null, 'Improper Uniform', '2026-01-02 09:00:00', 'pending'], // no such type
            [1, null, '', '2026-01-03 09:00:00', 'pending'], // empty raw value
        ]);

        $this->assertParity([1, 2]);

        $sql = $this->sqlNumbers();
        $this->assertSame(0, $sql[2], 'A raw value that matches no violation type must not occupy a slot.');
        $this->assertSame(0, $sql[3], 'An empty raw value must not occupy a slot.');

        // The model accessor also reports no offense for them (consistent UI).
        $this->assertSame('', Violation::findOrFail(2)->offense_level);
        $this->assertSame('', Violation::findOrFail(3)->offense_level);
    }

    public function test_different_students_and_types_have_independent_sequences(): void
    {
        $this->bootSchema(withTypeId: true, withRawType: true);

        $this->seedViolations([
            [1, 1, '1', '2026-01-01 09:00:00', 'pending'],
            [1, 1, '1', '2026-01-02 09:00:00', 'pending'],
            [1, 2, '2', '2026-01-01 09:00:00', 'pending'],
            [2, 1, '1', '2026-01-01 09:00:00', 'pending'],
            [2, null, '1', '2026-01-02 09:00:00', 'pending'],
        ]);

        $this->assertParity([1, 2]);

        $this->assertSame([1, 2], array_values($this->phpNumbers(1, 1)));
        $this->assertSame([1], array_values($this->phpNumbers(1, 2)));
        $this->assertSame([1, 2], array_values($this->phpNumbers(2, 1)));
    }

    public function test_rejection_recalculates_both_implementations_identically(): void
    {
        $this->bootSchema(withTypeId: true, withRawType: true);

        $first = $this->seedViolations([
            [1, 1, '1', '2026-01-01 09:00:00', 'pending'],
            [1, null, 'dress code', '2026-01-02 09:00:00', 'pending'],
            [1, 1, '1', '2026-01-03 09:00:00', 'pending'],
        ]);

        $this->assertSame([1, 2, 3], array_values($this->phpNumbers(1, 1)));

        DB::table('violation_tbl')->where('violation_id', $first)->update([
            'status' => Violation::STATUS_DENIED,
        ]);

        $this->assertParity([1]);

        $this->assertSame([1, 2], array_values($this->phpNumbers(1, 1)), 'Remaining rows shift down after a rejection.');

        // The rejected row itself occupies no slot.
        $this->assertSame(0, $this->sqlNumbers()[$first]);
        $this->assertSame('', Violation::findOrFail($first)->offense_level);
    }

    // ==================================================================
    // Parity — other schema shapes
    // ==================================================================

    public function test_sql_expression_matches_php_when_only_the_fk_column_exists(): void
    {
        $this->bootSchema(withTypeId: true, withRawType: false);

        $this->seedViolations([
            [1, 1, null, '2026-01-01 09:00:00', 'pending'],
            [1, 1, null, '2026-01-02 09:00:00', 'confirmed'],
            [1, 2, null, '2026-01-01 09:00:00', 'pending'],
        ]);

        $this->assertParity([1, 2]);
        $this->assertSame([1, 2], array_values($this->phpNumbers(1, 1)));
    }

    public function test_sql_expression_matches_php_when_only_the_raw_column_exists(): void
    {
        $this->bootSchema(withTypeId: false, withRawType: true);

        $this->seedViolations([
            [1, null, '1', '2026-01-01 09:00:00', 'pending'],
            [1, null, '1', '2026-01-02 09:00:00', 'pending'],
            [1, null, 'Dress Code', '2026-01-03 09:00:00', 'pending'],
            [1, null, '2', '2026-01-01 09:00:00', 'pending'],
        ]);

        $this->assertParity([1, 2]);
        $this->assertSame([1, 2, 3], array_values($this->phpNumbers(1, 1)));
        $this->assertSame([1], array_values($this->phpNumbers(1, 2)));
    }

    public function test_sql_expression_never_groups_when_no_type_column_exists(): void
    {
        $this->bootSchema(withTypeId: false, withRawType: false);

        $this->assertSame(
            '0',
            app(OffenseNumberService::class)->offenseNumberSql(),
            'Without a type column the expression must not invent a shared sequence.'
        );
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    private function bootSchema(bool $withTypeId, bool $withRawType): void
    {
        Schema::dropIfExists('violation_tbl');
        Schema::dropIfExists('violation_type_tbl');

        Schema::create('violation_type_tbl', function (Blueprint $table): void {
            $table->increments('violation_type_id');
            $table->string('violation_type');
            $table->unsignedBigInteger('violation_category_id')->nullable();
        });

        DB::table('violation_type_tbl')->insert([
            ['violation_type_id' => 1, 'violation_type' => 'Dress Code'],
            ['violation_type_id' => 2, 'violation_type' => 'Curfew'],
        ]);

        Schema::create('violation_tbl', function (Blueprint $table) use ($withTypeId, $withRawType): void {
            $table->increments('violation_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('student_number')->nullable();
            if ($withTypeId) {
                $table->unsignedInteger('violation_type_id')->nullable();
            }
            if ($withRawType) {
                $table->string('violation_type')->nullable();
            }
            $table->dateTime('violation_date')->nullable();
            $table->string('status')->nullable();
        });
    }

    /**
     * @param  array<int, array{0:int, 1:int|null, 2:string|null, 3:string|null, 4:string|null}>  $rows
     * @return int  the first inserted violation id
     */
    private function seedViolations(array $rows): int
    {
        $firstId = null;

        foreach ($rows as $row) {
            $data = [
                'user_id' => $row[0],
                'student_number' => 'S' . $row[0],
                'violation_date' => $row[3],
                'status' => $row[4],
            ];

            if (Schema::hasColumn('violation_tbl', 'violation_type_id')) {
                $data['violation_type_id'] = $row[1];
            }

            if (Schema::hasColumn('violation_tbl', 'violation_type')) {
                $data['violation_type'] = $row[2];
            }

            $id = (int) DB::table('violation_tbl')->insertGetId($data);
            $firstId ??= $id;
        }

        return (int) $firstId;
    }

    /** @return array<int, int> */
    private function phpNumbers(int $userId, int $typeId): array
    {
        return app(OffenseNumberService::class)->offenseNumbersFor($userId, $typeId);
    }

    /** @return array<int, int> */
    private function sqlNumbers(): array
    {
        $expression = app(OffenseNumberService::class)->offenseNumberSql();

        $map = [];

        foreach (DB::select("SELECT violation_id, {$expression} AS n FROM violation_tbl ORDER BY violation_id") as $row) {
            $map[(int) $row->violation_id] = (int) $row->n;
        }

        return $map;
    }

    /**
     * Assert that, for every student/type pair, the SQL number equals the PHP
     * number for each eligible row and is 0 for every ineligible row.
     *
     * @param  array<int, int>  $userIds
     */
    private function assertParity(array $userIds): void
    {
        $typeIds = DB::table('violation_type_tbl')->pluck('violation_type_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $sql = $this->sqlNumbers();
        $php = [];

        foreach ($userIds as $userId) {
            foreach ($typeIds as $typeId) {
                foreach ($this->phpNumbers($userId, $typeId) as $violationId => $number) {
                    $php[(int) $violationId] = $number;
                }
            }
        }

        foreach ($php as $violationId => $number) {
            $this->assertSame(
                $number,
                $sql[$violationId],
                "SQL and PHP disagree for violation {$violationId}."
            );
        }

        foreach ($sql as $violationId => $number) {
            if (! isset($php[$violationId])) {
                $this->assertSame(
                    0,
                    $number,
                    "Violation {$violationId} is not eligible but the SQL expression counted it."
                );
            }
        }
    }
}
