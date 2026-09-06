<?php

namespace App\Console\Commands;

use App\Models\BackupHistory;
use App\Services\Backup\BackupDecisionService;
use App\Services\Backup\BackupEngine;
use Illuminate\Console\Command;

/**
 * Single daily backup job.
 *
 * Decides FULL / INCREMENTAL / DAILY_SNAPSHOT based on the elapsed time since
 * the last SUCCESSFUL FULL backup (never on calendar dates), then runs the
 * appropriate pipeline. This is the ONLY command the scheduler calls for
 * creating backups.
 */
class RunBackup extends Command
{
    protected $signature = 'backup:run
        {--type= : Force a specific type: full|incremental|daily_snapshot}
        {--reason= : Optional note for logs}';

    protected $description = 'Run the daily database backup (FULL / INCREMENTAL / DAILY_SNAPSHOT).';

    public function handle(
        BackupDecisionService $decisionService,
        BackupEngine $engine,
    ): int {
        $forceType = $this->option('type');

        if ($forceType !== null) {
            $forceType = strtolower((string) $forceType);
            if (! in_array($forceType, BackupHistory::TYPES, true)) {
                $this->error('Invalid --type. Use one of: ' . implode('|', BackupHistory::TYPES) . '.');

                return self::FAILURE;
            }
            $type = $forceType;
            $reason = 'Forced via --type';
        } else {
            $decision = $decisionService->decide();
            $type = $decision['type'];
            $reason = (string) $decision['reason'];
        }

        $this->info('Backup decision: ' . strtoupper($type) . ' — ' . $reason);

        $result = $engine->run($type);

        if (! $result['success']) {
            $this->error('Backup FAILED (' . strtoupper($type) . '): ' . ($result['error'] ?? 'Unknown error'));

            return self::FAILURE;
        }

        $backup = $result['backup'];
        $this->info('Backup completed: ' . ($backup?->backup_name ?? '') . ' (' . strtoupper($type) . ').');

        return self::SUCCESS;
    }
}
