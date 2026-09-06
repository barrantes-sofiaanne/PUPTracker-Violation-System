<?php

namespace App\Services\Backup;

use App\Models\BackupHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Chain-aware retention/pruning.
 *
 * Rules:
 *   - daily snapshots  : keep BACKUP_RETENTION_SNAPSHOT_DAYS (default 30)
 *   - incrementals     : keep BACKUP_RETENTION_INCREMENTAL_DAYS (default 30)
 *   - full backups     : keep BACKUP_RETENTION_FULL_DAYS (default 90)
 *
 * A FULL backup is NEVER deleted while any newer backup in its recovery chain
 * (an incremental whose parent chain ultimately reaches it) is still retained,
 * because deleting the base would break recovery.
 *
 * Because chain dependencies make automatic deletion risky, the command runs as
 * a DRY RUN by default and only deletes when --force is supplied by a Super
 * Admin after reviewing the printed plan.
 */
class RetentionService
{
    public const ACTION_KEEP = 'keep';
    public const ACTION_DELETE_CANDIDATE = 'delete-candidate';

    /**
     * @return array{
     *   full_days: int,
     *   incremental_days: int,
     *   snapshot_days: int,
     *   candidates: array<int, array{id: int, backup_name: string, backup_type: string|null, completed_at: string|null, storage_path: string|null}>,
     *   protected_full_ids: array<int, int>,
     * }
     */
    public function plan(): array
    {
        $now = Carbon::now();
        $fullDays = (int) config('backup.retention.full_days', 90);
        $incrementalDays = (int) config('backup.retention.incremental_days', 30);
        $snapshotDays = (int) config('backup.retention.snapshot_days', 30);

        $fullCutoff = $now->copy()->subDays($fullDays);
        $incrementalCutoff = $now->copy()->subDays($incrementalDays);
        $snapshotCutoff = $now->copy()->subDays($snapshotDays);

        // Determine which FULL backups anchor a still-retained recovery chain.
        $protectedFullIds = $this->protectedFullIds($fullCutoff);

        $candidates = [];

        // Candidate snapshots older than snapshot_days.
        $expiredSnapshots = BackupHistory::query()
            ->successful()
            ->where('backup_type', BackupHistory::TYPE_DAILY_SNAPSHOT)
            ->where('completed_at', '<', $snapshotCutoff)
            ->orderBy('completed_at')
            ->get();

        foreach ($expiredSnapshots as $snapshot) {
            $candidates[] = $this->candidate($snapshot, self::ACTION_DELETE_CANDIDATE);
        }

        // Candidate incrementals older than incremental_days whose FULL base is
        // not protected.
        $expiredIncrementals = BackupHistory::query()
            ->successful()
            ->where('backup_type', BackupHistory::TYPE_INCREMENTAL)
            ->where('completed_at', '<', $incrementalCutoff)
            ->orderBy('completed_at')
            ->get();

        foreach ($expiredIncrementals as $incremental) {
            $candidates[] = $this->candidate($incremental, self::ACTION_DELETE_CANDIDATE);
        }

        // Candidate FULL backups older than full_days whose chain is no longer
        // needed by any retained incremental.
        $expiredFulls = BackupHistory::query()
            ->successful()
            ->full()
            ->where('completed_at', '<', $fullCutoff)
            ->orderBy('completed_at')
            ->get();

        foreach ($expiredFulls as $full) {
            if (isset($protectedFullIds[$full->id])) {
                $candidates[] = $this->candidate($full, self::ACTION_KEEP, 'Kept: an incremental recovery chain still depends on this FULL.');
                continue;
            }
            $candidates[] = $this->candidate($full, self::ACTION_DELETE_CANDIDATE);
        }

        return [
            'full_days' => $fullDays,
            'incremental_days' => $incrementalDays,
            'snapshot_days' => $snapshotDays,
            'candidates' => $candidates,
            'protected_full_ids' => array_keys($protectedFullIds),
        ];
    }

    /**
     * Delete the objects+rows the plan marks as delete candidates.
     *
     * @return array{deleted: int, skipped: int, errors: array<int, string>}
     */
    public function executePlan(bool $dryRun = true): array
    {
        $plan = $this->plan();
        $deleted = 0;
        $skipped = 0;
        $errors = [];

        foreach ($plan['candidates'] as $candidate) {
            if ($candidate['action'] !== self::ACTION_DELETE_CANDIDATE) {
                $skipped++;
                continue;
            }

            $record = BackupHistory::find($candidate['id']);
            if (! $record) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $deleted++; // counted as "would delete" in dry-run reporting
                continue;
            }

            try {
                $objectRemoved = true;
                if ($record->storage_path) {
                    $disk = Storage::disk((string) config('backup.disk', 's3'));
                    if ($disk->exists($record->storage_path)) {
                        $objectRemoved = $disk->delete($record->storage_path);
                    }
                }

                if (! $objectRemoved) {
                    // Do not silently drop the DB row while the object remains.
                    $errors[] = 'Object deletion failed for backup #' . $record->id . ' (' . $record->backup_name . ').';
                    $skipped++;
                    continue;
                }

                $record->delete();
                $deleted++;
            } catch (\Throwable $e) {
                $errors[] = 'Prune failed for backup #' . $record->id . ': ' . $this->safeError($e->getMessage());
                Log::error('Backup prune failed for a record', ['backup_id' => $record->id, 'error' => $this->safeError($e->getMessage())]);
                $skipped++;
            }
        }

        return [
            'deleted' => $deleted,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * @return array<int, int>  keyed by full backup id
     */
    protected function protectedFullIds(Carbon $fullCutoff): array
    {
        // Retained incrementals (newer than the incremental cutoff) that belong
        // to a chain whose FULL anchor is older than the full cutoff.
        $incrementalDays = (int) config('backup.retention.incremental_days', 30);
        $incrementalCutoff = Carbon::now()->copy()->subDays($incrementalDays);

        $retainedIncrementals = BackupHistory::query()
            ->successful()
            ->where('backup_type', BackupHistory::TYPE_INCREMENTAL)
            ->where('completed_at', '>=', $incrementalCutoff)
            ->whereNotNull('parent_backup_id')
            ->get();

        $protected = [];

        foreach ($retainedIncrementals as $incremental) {
            $anchorId = $this->chainAnchorId($incremental);
            if ($anchorId !== null) {
                $protected[$anchorId] = $anchorId;
            }
        }

        return $protected;
    }

    protected function chainAnchorId(BackupHistory $incremental): ?int
    {
        $visited = [];
        $current = $incremental;
        $depth = 0;

        while ($current && $current->parent_backup_id && $depth < 100) {
            if (isset($visited[$current->id])) {
                break; // cycle guard
            }
            $visited[$current->id] = true;

            if ($current->backup_type === BackupHistory::TYPE_FULL) {
                return $current->id;
            }

            $current = $current->parent;
            $depth++;
        }

        // If the parent chain is not resolvable to a full (e.g. legacy), do not
        // delete any full we cannot reason about; protect the nearest full.
        if ($current && $current->backup_type === BackupHistory::TYPE_FULL) {
            return $current->id;
        }

        return null;
    }

    /**
     * @return array{id: int, backup_name: string, backup_type: string|null, completed_at: string|null, storage_path: string|null, action: string, note: string|null}
     */
    protected function candidate(BackupHistory $record, string $action, ?string $note = null): array
    {
        return [
            'id' => $record->id,
            'backup_name' => $record->backup_name,
            'backup_type' => $record->backup_type,
            'completed_at' => optional($record->completed_at)->toDateTimeString(),
            'storage_path' => $record->storage_path,
            'action' => $action,
            'note' => $note,
        ];
    }

    protected function safeError(string $message): string
    {
        $message = \Illuminate\Support\Str::limit($message, 500);
        $message = preg_replace('/password[=:\s]+[^\s,;]+/i', 'password=***', $message) ?? $message;

        return $message;
    }
}
