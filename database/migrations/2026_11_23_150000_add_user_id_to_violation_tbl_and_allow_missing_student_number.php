<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Makes `user_id` the stable identity of a violation and lets a violation exist
 * for a student whose student number has not been assigned yet.
 *
 * CONSOLIDATED MIGRATION
 * ----------------------
 * Two byte-identical migrations previously carried these changes
 * (2026_11_23_150000 and 2026_11_24_000001). Neither had been applied in the
 * project's environment (verified with `migrate:status` and the `migrations`
 * table before consolidation), so they are merged here into this single
 * authoritative migration and the duplicate file was removed.
 *
 * SAFETY
 * ------
 * • Idempotent: every step checks the current schema first, so a partially
 *   applied or re-run migration converges instead of failing.
 * • Data-preserving: existing student numbers are never modified; they are
 *   only used to backfill `user_id` where it can be resolved unambiguously.
 * • NULL student numbers stay supported end to end.
 * • Portable: schema introspection (Schema::getIndexes / getForeignKeys) is
 *   used instead of MySQL-only information_schema queries. Only the
 *   `ALTER TABLE ... ADD CONSTRAINT` step is MySQL-only, because other engines
 *   cannot add a foreign key to an existing table.
 */
return new class extends Migration
{
    private const VIOLATION_TABLE = 'violation_tbl';

    private const USERS_TABLE = 'users_tbl';

    private const USER_ID_INDEX = 'violation_tbl_user_id_index';

    private const USER_ID_FOREIGN = 'violation_tbl_user_id_foreign';

    public function up(): void
    {
        if (! Schema::hasTable(self::VIOLATION_TABLE) || ! Schema::hasTable(self::USERS_TABLE)) {
            throw new RuntimeException(
                'violation_tbl and users_tbl must exist before this migration runs.'
            );
        }

        // 1. The legacy FK violation_tbl.student_number → users_tbl.student_number
        //    must go: the number is display data that can now be edited (or left
        //    empty) without detaching the violation from its student.
        $this->dropLegacyStudentNumberForeignKeys();

        // 2. Stable identity column (mirrors users_tbl.user_id exactly).
        if (! Schema::hasColumn(self::VIOLATION_TABLE, 'user_id')) {
            Schema::table(self::VIOLATION_TABLE, function (Blueprint $table): void {
                $this->addUserIdColumn($table)->nullable()->after('violation_id');
            });
        }

        $this->ensureIndex(self::USER_ID_INDEX, ['user_id']);

        // 3. A violation can be recorded before the student number is assigned.
        if (Schema::hasColumn(self::VIOLATION_TABLE, 'student_number')) {
            Schema::table(self::VIOLATION_TABLE, function (Blueprint $table): void {
                $table->string('student_number', 50)->nullable()->change();
            });
        }

        // 4. Backfill historical violations by their (unchanged) student number.
        $backfilled = $this->backfillUserId();

        // 5. Referential integrity for the new identity column.
        $this->ensureUserIdForeignKey();

        Log::info('violation_tbl user_id migration completed.', [
            'backfilled_rows' => $backfilled,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::VIOLATION_TABLE)) {
            return;
        }

        // Remove the identity FK first (MySQL only; other drivers never added it).
        $this->dropForeignKeyOn(['user_id']);

        if (Schema::hasColumn(self::VIOLATION_TABLE, 'user_id')) {
            if ($this->hasIndexOn(['user_id'])) {
                Schema::table(self::VIOLATION_TABLE, function (Blueprint $table): void {
                    $table->dropIndex(self::USER_ID_INDEX);
                });
            }

            Schema::table(self::VIOLATION_TABLE, function (Blueprint $table): void {
                $table->dropColumn('user_id');
            });
        }

        // Restore NOT NULL only when every row has a number again. Leaving the
        // column nullable is the safe option when NULLs exist, because forcing
        // NOT NULL would either fail or invent data.
        if (Schema::hasColumn(self::VIOLATION_TABLE, 'student_number')) {
            $nullNumbers = DB::table(self::VIOLATION_TABLE)
                ->whereNull('student_number')
                ->count();

            if ($nullNumbers === 0) {
                Schema::table(self::VIOLATION_TABLE, function (Blueprint $table): void {
                    $table->string('student_number', 50)->nullable(false)->change();
                });
            }
        }

        // NOTE: the legacy student_number FK is intentionally not restored.
        // Historical rows may legitimately reference a number that was later
        // edited or cleared, so re-adding the constraint could fail (or force
        // a data rewrite) during a rollback.
    }

    /**
     * Add `user_id` using the exact integer type of `users_tbl.user_id`, so the
     * foreign key can be created without an errno-150 type mismatch.
     */
    private function addUserIdColumn(Blueprint $table)
    {
        $column = collect(Schema::getColumns(self::USERS_TABLE))
            ->firstWhere('name', 'user_id');

        $type = strtolower((string) ($column['type'] ?? $column['type_name'] ?? 'int'));
        $name = strtolower((string) ($column['type_name'] ?? $type));
        $unsigned = str_contains($type, 'unsigned');

        if (str_contains($name, 'bigint')) {
            return $unsigned ? $table->unsignedBigInteger('user_id') : $table->bigInteger('user_id');
        }

        if (str_contains($name, 'smallint')) {
            return $unsigned ? $table->unsignedSmallInteger('user_id') : $table->smallInteger('user_id');
        }

        if (str_contains($name, 'mediumint')) {
            return $unsigned ? $table->unsignedMediumInteger('user_id') : $table->mediumInteger('user_id');
        }

        if (str_contains($name, 'tinyint')) {
            return $unsigned ? $table->unsignedTinyInteger('user_id') : $table->tinyInteger('user_id');
        }

        return $unsigned ? $table->unsignedInteger('user_id') : $table->integer('user_id');
    }

    /**
     * Drop every foreign key that ties violation_tbl.student_number to users_tbl.
     *
     * Only MySQL exposes a constraint name that can be dropped on an existing
     * table; other engines are skipped (their test/dev schemas are built by the
     * application, not by this migration).
     */
    private function dropLegacyStudentNumberForeignKeys(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (Schema::getForeignKeys(self::VIOLATION_TABLE) as $foreign) {
            $columns = array_map('strtolower', (array) ($foreign['columns'] ?? []));

            if (! in_array('student_number', $columns, true)) {
                continue;
            }

            if (($foreign['foreign_table'] ?? null) !== self::USERS_TABLE) {
                continue;
            }

            $name = $foreign['name'] ?? null;

            if ($name === null || $name === '') {
                continue;
            }

            Schema::table(self::VIOLATION_TABLE, function (Blueprint $table) use ($name): void {
                $table->dropForeign($name);
            });
        }
    }

    private function ensureIndex(string $name, array $columns): void
    {
        if ($this->hasIndexOn($columns)) {
            return;
        }

        Schema::table(self::VIOLATION_TABLE, function (Blueprint $table) use ($name, $columns): void {
            $table->index($columns, $name);
        });
    }

    private function hasIndexOn(array $columns): bool
    {
        $expected = array_map('strtolower', $columns);

        foreach (Schema::getIndexes(self::VIOLATION_TABLE) as $index) {
            $actual = array_map('strtolower', (array) ($index['columns'] ?? []));

            if ($actual === $expected) {
                return true;
            }
        }

        return false;
    }

    private function ensureUserIdForeignKey(): void
    {
        // `ALTER TABLE ... ADD CONSTRAINT` is not supported by SQLite (used by
        // the test suite). The index still keeps lookups fast there.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (Schema::getForeignKeys(self::VIOLATION_TABLE) as $foreign) {
            $columns = array_map('strtolower', (array) ($foreign['columns'] ?? []));

            if ($columns === ['user_id'] && ($foreign['foreign_table'] ?? null) === self::USERS_TABLE) {
                return;
            }
        }

        Schema::table(self::VIOLATION_TABLE, function (Blueprint $table): void {
            $table->foreign('user_id', self::USER_ID_FOREIGN)
                ->references('user_id')
                ->on(self::USERS_TABLE)
                ->cascadeOnDelete();
        });
    }

    private function dropForeignKeyOn(array $columns): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $expected = array_map('strtolower', $columns);

        foreach (Schema::getForeignKeys(self::VIOLATION_TABLE) as $foreign) {
            $actual = array_map('strtolower', (array) ($foreign['columns'] ?? []));
            $name = $foreign['name'] ?? null;

            if ($actual !== $expected || $name === null || $name === '') {
                continue;
            }

            Schema::table(self::VIOLATION_TABLE, function (Blueprint $table) use ($name): void {
                $table->dropForeign($name);
            });
        }
    }

    /**
     * Resolve `user_id` for every historical violation that has a student
     * number but no identity yet.
     *
     * Rows are grouped by their (unchanged) student number, so the query count
     * is bounded by the number of distinct numbers, not the number of rows.
     * A number that resolves to no account is left as-is (user_id stays NULL)
     * and is never guessed.
     */
    private function backfillUserId(): int
    {
        $updated = 0;

        DB::table(self::VIOLATION_TABLE)
            ->whereNull('user_id')
            ->whereNotNull('student_number')
            ->where('student_number', '!=', '')
            ->distinct()
            ->select('student_number')
            ->orderBy('student_number')
            ->chunk(500, function ($rows) use (&$updated): void {
                $numbers = $rows
                    ->pluck('student_number')
                    ->filter(static fn ($number): bool => trim((string) $number) !== '')
                    ->values()
                    ->all();

                if ($numbers === []) {
                    return;
                }

                $map = DB::table(self::USERS_TABLE)
                    ->whereIn('student_number', $numbers)
                    ->pluck('user_id', 'student_number');

                foreach ($map as $number => $userId) {
                    $updated += DB::table(self::VIOLATION_TABLE)
                        ->whereNull('user_id')
                        ->where('student_number', $number)
                        ->update(['user_id' => $userId]);
                }
            });

        return $updated;
    }
};
