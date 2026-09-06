<?php

namespace App\Services\Backup;

use App\Models\BackupHistory;
use Carbon\CarbonInterface;

/**
 * Decides what kind of backup the single daily job should produce.
 *
 * The decision is elapsed-time based on the LAST SUCCESSFUL FULL backup:
 *
 *   - No successful FULL backup   -> FULL
 *   - >= FULL_INTERVAL_DAYS since the last successful FULL -> FULL
 *   - otherwise:
 *       - binary logs available   -> INCREMENTAL
 *       - otherwise               -> DAILY_SNAPSHOT
 *
 * Calendar dates are NEVER used to pick the type. A FAILED attempt does not
 * reset the 14-day interval because we only ever measure from the last
 * SUCCESSFUL full record.
 */
class BackupDecisionService
{
    public function __construct(
        protected BinaryLogSupportService $binaryLogSupport,
    ) {
    }

    /**
     * @param  CarbonInterface|null  $now  injectable clock for tests
     */
    public function decide(?CarbonInterface $now = null): array
    {
        $now = $now ?: now();
        $lastSuccessfulFull = $this->lastSuccessfulFull();

        $dueForFull = $lastSuccessfulFull === null
            || $now->greaterThanOrEqualTo($lastSuccessfulFull->completed_at->copy()->addDays($this->fullIntervalDays()));

        if ($dueForFull) {
            return [
                'type' => BackupHistory::TYPE_FULL,
                'last_successful_full' => $lastSuccessfulFull,
                'due_for_full' => true,
                'reason' => $lastSuccessfulFull === null
                    ? 'No successful FULL backup exists yet.'
                    : 'At least ' . $this->fullIntervalDays() . ' days have elapsed since the last successful FULL backup.',
            ];
        }

        $probe = $this->binaryLogSupport->probe();

        if ($probe['available']) {
            return [
                'type' => BackupHistory::TYPE_INCREMENTAL,
                'last_successful_full' => $lastSuccessfulFull,
                'due_for_full' => false,
                'binary_log_support' => $probe,
                'reason' => 'Within the ' . $this->fullIntervalDays() . '-day window and binary logs are available.',
            ];
        }

        return [
            'type' => BackupHistory::TYPE_DAILY_SNAPSHOT,
            'last_successful_full' => $lastSuccessfulFull,
            'due_for_full' => false,
            'binary_log_support' => $probe,
            'reason' => $probe['reason'] ?? 'Within the full-backup window; binary logs unavailable, using a daily snapshot fallback.',
        ];
    }

    public function lastSuccessfulFull(): ?BackupHistory
    {
        return BackupHistory::query()
            ->successful()
            ->full()
            ->latest('completed_at')
            ->first();
    }

    public function fullIntervalDays(): int
    {
        return max(1, (int) config('backup.full_interval_days', 14));
    }
}
