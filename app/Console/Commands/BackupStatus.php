<?php

namespace App\Console\Commands;

use App\Models\BackupHistory;
use App\Services\Backup\BackupDecisionService;
use App\Services\Backup\BinaryLogSupportService;
use Illuminate\Console\Command;

/**
 * Reports the current backup status: whether TRUE incremental (binary-log)
 * backup is available, when the next FULL backup is due, and the latest
 * history entries. This is a read-only diagnostic command.
 */
class BackupStatus extends Command
{
    protected $signature = 'backup:status';

    protected $description = 'Show backup status: binlog support, full-backup due date, latest history.';

    public function handle(
        BackupDecisionService $decisionService,
        BinaryLogSupportService $binaryLogSupport,
    ): int {
        $decision = $decisionService->decide();
        $probe = $binaryLogSupport->probe();

        $this->info('Binary-log (TRUE incremental) support:');
        $this->line('  available        : ' . ($probe['available'] ? 'YES' : 'no'));
        $this->line('  log_bin          : ' . ($probe['log_bin'] ?? 'n/a'));
        $this->line('  binlog_format    : ' . ($probe['binlog_format'] ?? 'n/a'));
        $this->line('  archive dir      : ' . ($probe['archive_dir_configured'] ? 'configured' : 'not configured'));
        $this->line('  reason           : ' . ($probe['reason'] ?? 'n/a'));

        $this->newLine();
        $this->info('Today\'s decision:');
        $this->line('  type             : ' . strtoupper((string) $decision['type']));
        $this->line('  reason           : ' . (string) $decision['reason']);

        $full = $decision['last_successful_full'];
        $this->newLine();
        $this->info('Full backup baseline:');
        $this->line('  last successful FULL : ' . ($full ? $full->completed_at?->format('Y-m-d H:i:s') . ' (#' . $full->id . ')' : 'none'));
        if ($full) {
            $next = $full->completed_at->copy()->addDays($decisionService->fullIntervalDays());
            $this->line('  next FULL due        : ' . $next->format('Y-m-d H:i:s'));
        }

        $this->newLine();
        $this->info('Latest backup history:');
        $latest = BackupHistory::query()->latest('completed_at')->limit(5)->get();
        foreach ($latest as $row) {
            $status = $row->isSuccessful() ? 'SUCCESS' : 'FAILED';
            $this->line('  #' . $row->id . ' ' . str_pad($row->typeLabel(), 16) . ' ' . str_pad($status, 7) . ' ' . ($row->completed_at?->format('Y-m-d H:i:s') ?: '-') . '  ' . $row->backup_name);
        }

        return self::SUCCESS;
    }
}
