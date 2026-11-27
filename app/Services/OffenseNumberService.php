<?php

namespace App\Services;

use App\Models\Violation;
use App\Models\ViolationType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Centralized offense-number calculation.
 *
 * The offense number is DERIVED from the eligible violation sequence for one
 * student account + one violation type (and therefore one category). It is
 * never stored as a separate source of truth, so Admin, Security, OSS Staff and
 * the Student module always display the same value.
 *
 * A row counts when its status is `pending`, `confirmed`, or NULL (legacy).
 * `denied` / `deleted` rows never count. Ordering uses the actual violation
 * date and the violation primary key as the stable tie-breaker.
 *
 * Identity is always `users_tbl.user_id`; `student_number` is display data and
 * must never be used to group students (NULL/empty numbers would merge them).
 */
class OffenseNumberService
{
    /**
     * Statuses that reserve / occupy an offense slot.
     *
     * @var array<int, string>
     */
    public const COUNTED_STATUSES = [
        Violation::STATUS_PENDING,
        Violation::STATUS_CONFIRMED,
    ];

    /**
     * Eligible violations for one student + violation type, in chronological
     * order (violation date, then violation id).
     *
     * @return Collection<int, Violation>
     */
    public function eligibleViolations(int $userId, int $violationTypeId): Collection
    {
        if ($userId <= 0 || $violationTypeId <= 0) {
            return collect();
        }

        $hasTypeIdColumn = Schema::hasColumn('violation_tbl', 'violation_type_id');
        $hasRawTypeColumn = Schema::hasColumn('violation_tbl', 'violation_type');

        $typeName = (string) (ViolationType::query()
            ->whereKey($violationTypeId)
            ->value('violation_type') ?? '');

        // Match the FK, plus legacy rows that still store the type id/name in
        // the raw `violation_type` column (before reconciliation ran).
        $rawTypeCondition = function ($raw) use ($violationTypeId, $typeName): void {
            $raw->where('violation_type', (string) $violationTypeId);

            if ($typeName !== '') {
                $raw->orWhereRaw(
                    'LOWER(TRIM(violation_type)) = ?',
                    [mb_strtolower($typeName)]
                );
            }
        };

        return Violation::query()
            ->where('user_id', $userId)
            ->where(function ($query) use (
                $violationTypeId,
                $hasTypeIdColumn,
                $hasRawTypeColumn,
                $rawTypeCondition
            ): void {
                if ($hasTypeIdColumn) {
                    $query->where('violation_type_id', $violationTypeId);
                }

                if ($hasRawTypeColumn) {
                    if ($hasTypeIdColumn) {
                        $query->orWhere(function ($legacy) use ($rawTypeCondition): void {
                            $legacy->whereNull('violation_type_id')
                                ->where($rawTypeCondition);
                        });
                    } else {
                        $query->where($rawTypeCondition);
                    }
                } elseif (! $hasTypeIdColumn) {
                    // No usable type column: never mix unrelated violations.
                    $query->whereRaw('1 = 0');
                }
            })
            ->where(function ($query): void {
                $query->whereNull('status')
                    ->orWhereRaw(
                        'LOWER(TRIM(status)) IN (?, ?)',
                        self::COUNTED_STATUSES
                    );
            })
            ->orderBy('violation_date')
            ->orderBy('violation_id')
            ->get();
    }

    /**
     * Map every eligible violation id to its 1-based offense number.
     *
     * @return array<int, int>
     */
    public function offenseNumbersFor(int $userId, int $violationTypeId): array
    {
        $numbers = [];

        foreach ($this->eligibleViolations($userId, $violationTypeId) as $index => $violation) {
            $numbers[(int) $violation->violation_id] = $index + 1;
        }

        return $numbers;
    }

    /**
     * The offense number for one violation, or null when it is not eligible
     * (denied/deleted) or cannot be resolved.
     */
    public function offenseNumberFor(
        int $userId,
        int $violationId,
        int $violationTypeId
    ): ?int {
        if ($userId <= 0 || $violationId <= 0 || $violationTypeId <= 0) {
            return null;
        }

        return $this->offenseNumbersFor($userId, $violationTypeId)[$violationId] ?? null;
    }

    /**
     * Formatted offense level for one violation, or an empty string when the
     * row does not occupy an offense slot.
     */
    public function offenseLevelFor(
        int $userId,
        int $violationId,
        int $violationTypeId
    ): string {
        $number = $this->offenseNumberFor($userId, $violationId, $violationTypeId);

        return $number === null ? '' : $this->formatOffenseLevel($number);
    }

    /**
     * Number of eligible violations (the latest chronological position).
     */
    public function currentOffenseNumber(int $userId, int $violationTypeId): int
    {
        return $this->eligibleViolations($userId, $violationTypeId)->count();
    }

    /**
     * Formatted current offense level for a student + violation type.
     */
    public function currentOffenseLevel(int $userId, int $violationTypeId): string
    {
        return $this->formatOffenseLevel(
            $this->currentOffenseNumber($userId, $violationTypeId)
        );
    }

    /**
     * The offense number a violation WOULD occupy if moved to the given type,
     * keeping its real chronological position (used by correction previews).
     */
    public function projectedOffenseNumberFor(
        int $userId,
        int $violationTypeId,
        mixed $violationDate,
        int $violationId
    ): int {
        if ($userId <= 0 || $violationTypeId <= 0) {
            return 0;
        }

        $rows = $this->eligibleViolations($userId, $violationTypeId)
            ->reject(fn (Violation $row): bool => (int) $row->violation_id === $violationId)
            ->all();

        // Insert the candidate at its chronological position and read the
        // 1-based index. A missing date sorts first, like the query ordering.
        $rows[] = (object) [
            'violation_id' => $violationId,
            'violation_date' => $violationDate,
        ];

        usort($rows, function ($left, $right): int {
            $leftDate = $left->violation_date ? (string) $left->violation_date : '';
            $rightDate = $right->violation_date ? (string) $right->violation_date : '';

            return [$leftDate, (int) $left->violation_id]
                <=> [$rightDate, (int) $right->violation_id];
        });

        foreach ($rows as $index => $row) {
            if ((int) $row->violation_id === $violationId) {
                return $index + 1;
            }
        }

        return 0;
    }

    /**
     * The offense number a NEW recording would receive.
     */
    public function nextOffenseNumber(int $userId, int $violationTypeId): int
    {
        return $this->currentOffenseNumber($userId, $violationTypeId) + 1;
    }

    /**
     * The formatted offense level a NEW recording would receive.
     */
    public function nextOffenseLevel(int $userId, int $violationTypeId): string
    {
        return $this->formatOffenseLevel(
            $this->nextOffenseNumber($userId, $violationTypeId)
        );
    }

    /**
     * Recalculate the sequence for one student (and optionally one violation
     * type) after an administrator decision.
     *
     * Nothing is written: offense numbers are derived on read. The returned
     * map is useful for audit/flagging when a decision shifts later rows.
     *
     * @return array<int, array<int, int>>  [violation_type_id => [violation_id => offense_number]]
     */
    public function recalculateFor(int $userId, ?int $violationTypeId = null): array
    {
        if ($userId <= 0) {
            return [];
        }

        $typeIds = $violationTypeId !== null && $violationTypeId > 0
            ? [$violationTypeId]
            : Violation::query()
                ->where('user_id', $userId)
                ->whereNotNull('violation_type_id')
                ->distinct()
                ->pluck('violation_type_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

        $sequences = [];

        foreach ($typeIds as $typeId) {
            $sequences[$typeId] = $this->offenseNumbersFor($userId, $typeId);
        }

        return $sequences;
    }

    /**
     * Serialize recordings for one student inside the caller's transaction.
     *
     * Locking the canonical student row makes two simultaneous submissions for
     * the same student run one after the other, so their derived offense
     * positions cannot race. The violation primary key remains the final
     * tie-breaker within the same violation date.
     */
    public function lockStudent(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        if (! Schema::hasTable('users_tbl') || ! Schema::hasColumn('users_tbl', 'user_id')) {
            return;
        }

        // SQLite (the test database) ignores FOR UPDATE, which is fine because
        // its transactions are serialized anyway.
        DB::table('users_tbl')
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Whether a violation status occupies an offense slot.
     */
    public function statusCountsTowardOffense(?string $status): bool
    {
        $normalized = strtolower(trim((string) $status));

        if ($normalized === '') {
            return true; // Legacy rows without a status remain effective.
        }

        return in_array($normalized, self::COUNTED_STATUSES, true);
    }

    /**
     * "1st Offense", "2nd Offense", ... for an offense count.
     */
    public function formatOffenseLevel(int $count): string
    {
        if ($count <= 0) {
            return '';
        }

        if ($count === 1) {
            return '1st Offense';
        }

        if ($count === 2) {
            return '2nd Offense';
        }

        if ($count === 3) {
            return '3rd Offense';
        }

        $suffix = match (true) {
            $count % 100 >= 11 && $count % 100 <= 13 => 'th',
            $count % 10 === 1 => 'st',
            $count % 10 === 2 => 'nd',
            $count % 10 === 3 => 'rd',
            default => 'th',
        };

        return "{$count}{$suffix} Offense";
    }
}
