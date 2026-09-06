<?php

namespace App\Console\Commands;

use App\Services\Backup\RetentionService;
use Illuminate\Console\Command;

/**
 * Chain-aware retention pruning.
 *
 * Runs as a DRY RUN by default (safe). Only deletes objects + history rows when
 * a Super Admin passes --force after reviewing the printed plan. A FULL backup
 * is never deleted while a retained incremental recovery chain depends on it.
 */
class BackupPrune extends Command
{
    protected $signature = 'backup:prune
        {--force : Actually delete the planned backups (default is a dry run)}
        {--json : Output the plan as JSON}';

    protected $description = 'Prune expired backups using a chain-aware retention policy (dry run by default).';

    public function handle(RetentionService $retention): int
    {
        $force = (bool) $this->option('force');
        $plan = $retention->plan();

        if ($this->option('json')) {
            $this->line(json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Retention policy: FULL ' . $plan['full_days'] . 'd | INCREMENTAL ' . $plan['incremental_days'] . 'd | SNAPSHOT ' . $plan['snapshot_days'] . 'd');
        $this->info('Protected FULL backups (chains still in use): ' . count($plan['protected_full_ids']));

        $this->newLine();
        $this->info(($force ? 'Execution plan' : 'Dry-run plan') . ' ('. count($plan['candidates']) . ' reviewed):');

        $deleteCount = 0;
        foreach ($plan['candidates'] as $candidate) {
            $isDelete = $candidate['action'] === RetentionService::ACTION_DELETE_CANDIDATE;
            $label = $isDelete ? 'DELETE' : 'keep  ';
            if ($isDelete) {
                $deleteCount++;
            }
            $note = $candidate['note'] ? ' — ' . $candidate['note'] : '';
            $this->line('  [' . $label . '] #' . $candidate['id'] . ' ' . str_pad((string) $candidate['backup_type'], 16) . ' ' . ($candidate['backup_name'] ?? '') . ' @ ' . ($candidate['completed_at'] ?? '-') . $note);
        }

        $this->newLine();
        $this->info('Candidates to delete: ' . $deleteCount . (count($plan['protected_full_ids']) > 0 ? ' | protected FULLs: ' . count($plan['protected_full_ids']) : ''));

        if (! $force) {
            $this->warn('Dry run: no backups were deleted. Re-run with --force to apply after review.');

            return self::SUCCESS;
        }

        $result = $retention->executePlan(false);

        $this->info('Deleted: ' . $result['deleted'] . ' | skipped: ' . $result['skipped']);

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        return count($result['errors']) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
