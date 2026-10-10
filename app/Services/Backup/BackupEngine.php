<?php

namespace App\Services\Backup;

use App\Models\AuditLog;
use App\Models\BackupHistory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Orchestrates the end-to-end backup pipeline:
 *
 *   START -> dump/binlog -> compress -> checksum -> upload (R2) ->
 *   verify object exists -> [optional download + re-hash] -> record SUCCESS
 *
 * A backup history row is only persisted with SUCCESS after every required step
 * passes. Any failure records FAILED with a sanitized error message and does NOT
 * touch the "last successful FULL" interval (the decision layer measures only
 * from successful FULL records).
 */
class BackupEngine
{
    public function __construct(
        protected DatabaseDumpService $dumpService,
        protected BinlogArchiveService $binlogArchiveService,
        protected BinaryLogSupportService $binaryLogSupport,
        protected BackupDecisionService $decisionService,
    ) {
    }

    /**
     * Run a backup of the given type.
     *
     * @return array{success: bool, backup: BackupHistory|null, type: string, error: string|null}
     */
    public function run(string $type): array
    {
        $type = $this->normalizeType($type);
        if ($type === null) {
            return [
                'success' => false,
                'backup' => null,
                'type' => 'unknown',
                'error' => 'Unknown backup type requested.',
            ];
        }

        $startedAt = now();
        $backupName = $this->generateName($type);
        $error = null;
        $tempFile = null;
        $metadata = [];

        try {
            switch ($type) {
                case BackupHistory::TYPE_FULL:
                case BackupHistory::TYPE_DAILY_SNAPSHOT:
                    [$tempFile, $metadata] = $this->runLogicalDump($type, $startedAt);
                    break;

                case BackupHistory::TYPE_INCREMENTAL:
                    [$tempFile, $metadata] = $this->runIncremental($startedAt);
                    break;
            }
        } catch (\Throwable $e) {
            $error = $this->safeError($e->getMessage());
            Log::error('Database backup: stage failure', ['backup_type' => $type, 'error' => $error]);
        }

        if ($error === null && ($tempFile === null || ! is_file($tempFile))) {
            $error = 'Backup produced no output file.';
        }

        if ($error !== null) {
            if ($tempFile) {
                @unlink($tempFile);
            }
            $failed = $this->recordFailure($backupName, $type, $error, $startedAt);

            return [
                'success' => false,
                'backup' => $failed,
                'type' => $type,
                'error' => $error,
            ];
        }

        try {
            $record = $this->uploadVerifyAndRecordSuccess($backupName, $type, $tempFile, $metadata, $startedAt);
        } catch (\Throwable $e) {
            $error = $this->safeError($e->getMessage());
            Log::error('Database backup: upload/verification failure', ['backup_type' => $type, 'error' => $error]);
            $failed = $this->recordFailure($backupName, $type, $error, $startedAt);
            @unlink($tempFile);

            return [
                'success' => false,
                'backup' => $failed,
                'type' => $type,
                'error' => $error,
            ];
        }

        @unlink($tempFile);

        return [
            'success' => true,
            'backup' => $record,
            'type' => $type,
            'error' => null,
        ];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}  [tempFile, metadata]
     */
protected function runLogicalDump(string $type, $startedAt): array
{
    $result = $this->dumpService->dumpDatabase();

    if (! $result['success']) {
        throw new \RuntimeException($result['error'] ?? 'Database dump failed.');
    }

    $metadata = [
        'type' => $type,
        'checksum' => $result['checksum'],
        'checksum_algorithm' => 'SHA-256',
        'size' => $result['compressed_size'],
        'file_count' => 1,
        'started_at' => $startedAt,
    ];

    /*
     * Capture the binary-log boundary immediately after the logical dump.
     * This becomes the recovery-chain anchor for subsequent incrementals.
     *
     * Only FULL backups need to establish the initial anchor. Daily
     * snapshots retain their existing behavior.
     */
    if ($type === BackupHistory::TYPE_FULL) {
        $position = $this->binaryLogSupport->currentPosition();

        if ($position === null) {
            throw new \RuntimeException(
                'FULL backup completed, but the current MySQL binary-log position could not be captured.'
            );
        }

        $metadata['binlog_file_end'] = $position['file'];
        $metadata['binlog_position_end'] = $position['position'];
    }

    return [
        $result['temp_file'],
        $metadata,
    ];
}

    /**
     * @return array{0: string, 1: array<string, mixed>}  [tempFile, metadata]
     */
    protected function runIncremental($startedAt): array
    {
        $probe = $this->binaryLogSupport->probe();
        if (! $probe['available']) {
            throw new \RuntimeException($probe['reason'] ?? 'Binary logs are not available for an incremental backup.');
        }

        $chain = $this->recoveryChainAnchor();

        $result = $this->binlogArchiveService->archiveIncremental([
            'base_file' => $chain['binlog_file_start'] ?? $chain['binlog_file_end'],
            'base_position' => $chain['binlog_position_end'] ?? 0,
            'archive_dir' => (string) config('backup.binlog_archive_dir', ''),
        ]);

        if (! $result['success']) {
            throw new \RuntimeException($result['error'] ?? 'Incremental archive failed.');
        }

        return [
            $result['temp_file'],
            [
                'type' => BackupHistory::TYPE_INCREMENTAL,
                'parent_backup_id' => $chain['id'],
                'checksum' => $result['checksum'],
                'checksum_algorithm' => 'SHA-256',
                'size' => $result['size'],
                'file_count' => 1,
                'binlog_file_start' => $chain['binlog_file_end'],
                'binlog_position_start' => $chain['binlog_position_end'],
                'binlog_file_end' => $result['binlog_file_end'],
                'binlog_position_end' => $result['binlog_position_end'],
                'started_at' => $startedAt,
            ],
        ];
    }

    /**
     * Find the anchor of the current incremental chain (the last backup that
     * recorded a binary-log end boundary, else the last successful FULL).
     *
     * @return array{id: int, binlog_file_start: string|null, binlog_file_end: string|null, binlog_position_start: int|null, binlog_position_end: int|null}
     */
    protected function recoveryChainAnchor(): array
    {
        $lastBoundary = BackupHistory::query()
            ->successful()
            ->whereNotNull('binlog_file_end')
            ->latest('completed_at')
            ->first();

        if ($lastBoundary) {
            return [
                'id' => $lastBoundary->id,
                'binlog_file_start' => $lastBoundary->binlog_file_start,
                'binlog_file_end' => $lastBoundary->binlog_file_end,
                'binlog_position_start' => $lastBoundary->binlog_position_start,
                'binlog_position_end' => $lastBoundary->binlog_position_end,
            ];
        }

        $full = $this->decisionService->lastSuccessfulFull();

        if ($full) {
            return [
                'id' => $full->id,
                'binlog_file_start' => $full->binlog_file_start,
                'binlog_file_end' => $full->binlog_file_end,
                'binlog_position_start' => $full->binlog_position_start,
                'binlog_position_end' => $full->binlog_position_end,
            ];
        }

        throw new \RuntimeException('No recovery-chain anchor found (no successful FULL or incremental with binlog metadata).');
    }

    protected function generateName(string $type): string
    {
        $stamp = now()->format('Y-m-d_H-i-s');
        $prefix = match ($type) {
            BackupHistory::TYPE_FULL => 'full',
            BackupHistory::TYPE_INCREMENTAL => 'incremental',
            default => 'snapshot',
        };

        return $prefix . '_' . $stamp;
    }

    /**
     * Upload to the private disk, verify object existence, then persist a
     * SUCCESS row with checksum/size/verification metadata.
     */
    protected function uploadVerifyAndRecordSuccess(
        string $backupName,
        string $type,
        string $tempFile,
        array $metadata,
        $startedAt,
    ): BackupHistory {
        $diskName = (string) config('backup.disk', 's3');
        $disk = Storage::disk($diskName);

        $objectKey = $this->objectKey($type, $backupName);

        $stream = @fopen($tempFile, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Unable to open the local backup file for upload.');
        }

        try {
            $this->uploadStream($disk, $objectKey, $stream);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Upload to backup storage failed: ' . $this->safeError($e->getMessage()),
                0,
                $e
            );
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        // Verify object existence (a successful write does not guarantee it is
        // visible/listable).
        try {
            $exists = $this->objectExists($disk, $objectKey);
        } catch (\Throwable $e) {
            $exists = false;
        }

        if (! $exists) {
            throw new \RuntimeException('Backup object verify failed: the uploaded object could not be confirmed on storage.');
        }

        // Optional download + re-hash for byte-for-byte integrity.
        $verifiedAt = now();
        if ((bool) config('backup.verify_download', false)) {
            $remoteHash = $this->downloadAndHash($disk, $objectKey);
            if ($remoteHash === null || ! hash_equals((string) ($metadata['checksum'] ?? ''), $remoteHash)) {
                throw new \RuntimeException('Backup download integrity check failed (SHA-256 mismatch).');
            }
        }

        $record = BackupHistory::create([
            'backup_name' => $backupName,
            'backup_type' => $type,
            'parent_backup_id' => $metadata['parent_backup_id'] ?? null,
            'status' => BackupHistory::STATUS_SUCCESS,
            'started_at' => $metadata['started_at'] ?? $startedAt,
            'completed_at' => $verifiedAt,
            'destination' => $this->destinationLabel(),
            'backup_size' => $metadata['size'] ?? filesize($tempFile) ?: null,
            'file_count' => $metadata['file_count'] ?? 1,
            'checksum' => $metadata['checksum'] ?? null,
            'checksum_algorithm' => $metadata['checksum_algorithm'] ?? 'SHA-256',
            'verified_at' => $verifiedAt,
            'storage_path' => $objectKey,
            'binlog_file_start' => $metadata['binlog_file_start'] ?? null,
            'binlog_position_start' => $metadata['binlog_position_start'] ?? null,
            'binlog_file_end' => $metadata['binlog_file_end'] ?? null,
            'binlog_position_end' => $metadata['binlog_position_end'] ?? null,
            'binlog_timestamp_start' => $metadata['binlog_timestamp_start'] ?? null,
            'binlog_timestamp_end' => $metadata['binlog_timestamp_end'] ?? null,
            'error_message' => null,
        ]);

        $this->audit($record, 'Backup Completed');

        return $record;
    }

    protected function recordFailure(string $backupName, string $type, string $message, $startedAt): BackupHistory
    {
        $record = BackupHistory::create([
            'backup_name' => $backupName,
            'backup_type' => $type,
            'parent_backup_id' => null,
            'status' => BackupHistory::STATUS_FAILED,
            'started_at' => $startedAt,
            'completed_at' => now(),
            'destination' => $this->destinationLabel(),
            'backup_size' => null,
            'file_count' => null,
            'checksum' => null,
            'checksum_algorithm' => 'SHA-256',
            'verified_at' => null,
            'storage_path' => null,
            'error_message' => Str::limit($message, 2000),
        ]);

        $this->audit($record, 'Backup Failed');

        return $record;
    }

    protected function audit(BackupHistory $backup, string $action): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        try {
            AuditLog::create([
                'actor_type' => 'system',
                'actor_id' => null,
                'action' => $action,
                'module' => 'Backup',
                'description' => $backup->isSuccessful()
                    ? 'Database ' . $backup->typeLabel() . ' backup ' . $backup->backup_name . ' completed to ' . $backup->displayDestination() . '.'
                    : 'Database ' . $backup->typeLabel() . ' backup ' . $backup->backup_name . ' failed: ' . ($backup->error_message ?: 'Unknown error') . '.',
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to write backup audit entry', ['error' => $this->safeError($e->getMessage())]);
        }
    }

    protected function objectKey(string $type, string $backupName): string
    {
        $prefix = trim((string) config('backup.prefix', 'database-backups'), '/');
        $folder = match ($type) {
            BackupHistory::TYPE_FULL => 'full',
            BackupHistory::TYPE_INCREMENTAL => 'incremental',
            default => 'snapshot',
        };
        $datePath = now()->format('Y/m');

        return $prefix . '/' . $folder . '/' . $datePath . '/' . $backupName . '.sql.gz';
    }

    /**
     * Destination label shown in history/UI. Matches the configured disk so the
     * UI never claims R2 when the backup actually landed on a local test disk.
     */
    protected function destinationLabel(): string
    {
        return (string) config('backup.disk', 's3') === 's3'
            ? 'Cloudflare R2'
            : 'Local (private) ' . (string) config('backup.disk', 's3');
    }

    protected function uploadStream($disk, string $key, $stream): void
    {
        $disk->writeStream($key, $stream);
    }

    protected function objectExists($disk, string $key): bool
    {
        return $disk->exists($key);
    }

    protected function downloadAndHash($disk, string $objectKey): ?string
    {
        $ctx = hash_init('sha256');
        if ($ctx === false) {
            return null;
        }

        try {
            $stream = $disk->readStream($objectKey);
            if (! is_resource($stream)) {
                return null;
            }
            while (! feof($stream)) {
                $chunk = fread($stream, 1048576);
                if ($chunk === false) {
                    fclose($stream);

                    return null;
                }
                hash_update($ctx, $chunk);
            }
            fclose($stream);

            return hash_final($ctx);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function normalizeType(string $type): ?string
    {
        $type = strtolower($type);
        if (! in_array($type, BackupHistory::TYPES, true)) {
            return null;
        }

        return $type;
    }

    protected function safeError(string $message): string
    {
        $message = Str::limit($message, 500);
        $message = preg_replace('/password[=:\s]+[^\s,;]+/i', 'password=***', $message) ?? $message;

        return $message;
    }
}
