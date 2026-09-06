<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\BackupHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ReportBackupResult extends Command
{
    protected $signature = 'backup:report
        {backupName : The backup name, e.g. 2026-08-26-backup or full_2026-09-15_020000}
        {--status=success : success or failed}
        {--type= : full|incremental|daily_snapshot}
        {--started-at= : ISO timestamp}
        {--completed-at= : ISO timestamp}
        {--destination=Cloudflare R2 : Destination label}
        {--size= : Backup size in bytes}
        {--files= : File count}
        {--checksum= : SHA-256 checksum}
        {--checksum-algorithm=SHA-256 : Checksum algorithm}
        {--verified-at= : ISO timestamp when verification passed}
        {--storage-path= : Private object key on the backup disk}
        {--parent-backup-id= : Id of the parent/base backup in the recovery chain}
        {--error= : Error message (required when status=failed)}';

    protected $description = 'Record the result of a database backup job (MyDumper → Cloudflare R2 or in-app) into backup_history.';

    public function handle(): int
    {
        $status = strtolower((string) $this->option('status'));
        $backupName = (string) $this->argument('backupName');
        $type = $this->normalizeType((string) $this->option('type'));

        if (! in_array($status, [BackupHistory::STATUS_SUCCESS, BackupHistory::STATUS_FAILED], true)) {
            $this->error('Status must be success or failed.');

            return self::FAILURE;
        }

        if ($status === BackupHistory::STATUS_FAILED && ! $this->option('error')) {
            $this->error('An --error message is required when status=failed.');

            return self::FAILURE;
        }

        $completedAt = $this->option('completed-at') ?: now();

        $attributes = [
            'backup_name' => $backupName,
            'backup_type' => $type,
            'status' => $status,
            'started_at' => $this->option('started-at') ?: $completedAt,
            'completed_at' => $completedAt,
            'destination' => (string) ($this->option('destination') ?: 'Cloudflare R2'),
            'backup_size' => $this->option('size') !== null ? (int) $this->option('size') : null,
            'file_count' => $this->option('files') !== null ? (int) $this->option('files') : null,
            'checksum' => $this->option('checksum') ?: null,
            'checksum_algorithm' => (string) ($this->option('checksum-algorithm') ?: 'SHA-256'),
            // verified_at is only set when the reporter explicitly confirms the
            // backup passed verification (never assumed from status).
            'verified_at' => $this->option('verified-at') ?: null,
            'storage_path' => $this->option('storage-path') ?: null,
            'parent_backup_id' => $this->option('parent-backup-id') !== null ? (int) $this->option('parent-backup-id') : null,
            'error_message' => $this->option('error') ?: null,
        ];

        // Idempotent: if an external job re-reports the same backup_name, update
        // the existing row instead of creating duplicates.
        $backup = BackupHistory::query()
            ->where('backup_name', $backupName)
            ->latest('id')
            ->first();

        // Only write columns that exist on the current schema (pre-policy
        // deployments that have not run the migration must not break).
        $writeable = array_filter(
            $attributes,
            fn ($value, $key) => BackupHistory::hasColumn($key),
            ARRAY_FILTER_USE_BOTH
        );

        if ($backup) {
            $backup->forceFill($writeable)->save();
        } else {
            $backup = BackupHistory::create($writeable);
        }

        if (Schema::hasTable('audit_logs')) {
            try {
                AuditLog::create([
                    'actor_type' => 'system',
                    'actor_id' => null,
                    'action' => $backup->isSuccessful() ? 'Backup Completed' : 'Backup Failed',
                    'module' => 'Backup',
                    'description' => $backup->isSuccessful()
                        ? 'Database ' . $backup->typeLabel() . ' backup ' . $backup->backup_name . ' completed to ' . ($backup->destination ?: 'Cloudflare R2') . '.'
                        : 'Database ' . $backup->typeLabel() . ' backup ' . $backup->backup_name . ' failed: ' . ($backup->error_message ?: 'Unknown error') . '.',
                    'created_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to write backup audit entry', ['error' => $e->getMessage()]);
            }
        }

        $this->info('Backup result recorded: ' . $backup->backup_name . ' (' . $backup->status . ').');

        return self::SUCCESS;
    }

    protected function normalizeType(string $type): ?string
    {
        $type = strtolower($type);
        if ($type === '') {
            return null;
        }
        if (! in_array($type, BackupHistory::TYPES, true)) {
            $this->warn('Unknown --type value ignored: ' . Str::limit($type, 30));

            return null;
        }

        return $type;
    }
}
