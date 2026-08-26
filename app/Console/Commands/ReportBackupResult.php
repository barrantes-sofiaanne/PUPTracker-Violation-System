<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\BackupHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ReportBackupResult extends Command
{
    protected $signature = 'backup:report
        {backupName : The backup name, e.g. 2026-08-26-backup}
        {--status=success : success or failed}
        {--started-at= : ISO timestamp}
        {--completed-at= : ISO timestamp}
        {--destination=Cloudflare R2 : Destination label}
        {--size= : Backup size in bytes}
        {--files= : File count}
        {--error= : Error message (required when status=failed)}';

    protected $description = 'Record the result of the MyDumper → Cloudflare R2 backup job into backup_history.';

    public function handle(): int
    {
        $status = strtolower((string) $this->option('status'));
        $backupName = (string) $this->argument('backupName');

        if (! in_array($status, [BackupHistory::STATUS_SUCCESS, BackupHistory::STATUS_FAILED], true)) {
            $this->error('Status must be success or failed.');

            return self::FAILURE;
        }

        if ($status === BackupHistory::STATUS_FAILED && ! $this->option('error')) {
            $this->error('An --error message is required when status=failed.');

            return self::FAILURE;
        }

        $backup = BackupHistory::create([
            'backup_name' => $backupName,
            'status' => $status,
            'started_at' => $this->option('started-at') ?: now(),
            'completed_at' => $this->option('completed-at') ?: now(),
            'destination' => (string) ($this->option('destination') ?: 'Cloudflare R2'),
            'backup_size' => $this->option('size') !== null ? (int) $this->option('size') : null,
            'file_count' => $this->option('files') !== null ? (int) $this->option('files') : null,
            'error_message' => $this->option('error') ?: null,
        ]);

        if (Schema::hasTable('audit_logs')) {
            try {
                AuditLog::create([
                    'actor_type' => 'system',
                    'actor_id' => null,
                    'action' => $backup->isSuccessful() ? 'Backup Completed' : 'Backup Failed',
                    'module' => 'Backup',
                    'description' => $backup->isSuccessful()
                        ? 'Database backup ' . $backup->backup_name . ' completed to ' . ($backup->destination ?: 'Cloudflare R2') . '.'
                        : 'Database backup ' . $backup->backup_name . ' failed: ' . ($backup->error_message ?: 'Unknown error') . '.',
                    'created_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to write backup audit entry', ['error' => $e->getMessage()]);
            }
        }

        $this->info('Backup result recorded: ' . $backup->backup_name . ' (' . $backup->status . ').');

        return self::SUCCESS;
    }
}
