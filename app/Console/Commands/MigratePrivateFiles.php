<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Migrate confidential files (violation evidence, sanction proofs, and
 * announcement attachments) from a local/legacy disk to a private
 * S3-compatible disk (Cloudflare R2).
 *
 * Why this exists:
 *   - In production on Railway the container filesystem is ephemeral. Files
 *     written to storage/app/private disappear when the container is replaced,
 *     so database records survive but the physical files return 404.
 *   - The intended persistent location is the private R2 bucket via the
 *     existing 's3' disk.
 *
 * Safety guarantees:
 *   - Re-runnable: an object already present on the target is skipped.
 *   - DB storage paths are preserved (both disks use the same relative path),
 *     so existing records keep working after the switch.
 *   - Local originals are NEVER deleted by this command; you can remove them
 *     later once you have verified the destination.
 *   - Each object is verified to exist on the destination before it is
 *     considered migrated.
 *   - Credentials are never printed or logged.
 */
class MigratePrivateFiles extends Command
{
    protected $signature = 'storage:migrate-private-files
        {--source=local : The legacy disk files currently live on (default: local)}
        {--target=s3 : The private S3-compatible disk to migrate to (default: s3)}';

    protected $description = 'Migrate violation evidence, sanction proofs, and announcement attachments to a private S3/R2 disk.';

    public function handle(): int
    {
        $sourceName = (string) $this->option('source');
        $targetName = (string) $this->option('target');

        if ($sourceName === $targetName) {
            $this->error('Source and target disks must be different.');

            return self::FAILURE;
        }

        try {
            $source = Storage::disk($sourceName);
            $target = Storage::disk($targetName);
        } catch (\Throwable $e) {
            $this->error('Unable to resolve storage disk(s). Check config/filesystems.php.');

            return self::FAILURE;
        }

        $this->info("Migrating private files: {$sourceName} -> {$targetName}");

        $totalMigrated = 0;
        $totalSkipped = 0;
        $totalMissing = 0;
        $totalFailed = 0;

        $groups = [
            'violation evidence' => $this->evidenceRows(),
            'sanction proofs' => $this->proofRows(),
            'announcement attachments' => $this->attachmentRows(),
        ];

        foreach ($groups as $label => $rows) {
            foreach ($rows as $row) {
                [$recordId, $path, $column] = $row;
                $result = $this->migrateObject($source, $target, $path, $recordId, $label, $column);

                match ($result) {
                    'migrated' => $totalMigrated++,
                    'skipped' => $totalSkipped++,
                    'missing' => $totalMissing++,
                    default => $totalFailed++,
                };
            }
        }

        $this->newLine();
        $this->info('Migration summary:');
        $this->info("  Migrated: {$totalMigrated}");
        $this->info("  Skipped (already on target): {$totalSkipped}");
        $this->info("  Missing on source (skipped): {$totalMissing}");
        $this->info("  Failed: {$totalFailed}");
        $this->warn('Local originals were NOT deleted. Verify the destination, then remove them manually if desired.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{0:int,1:string,2:string|null}>  [id, path, column-to-update]
     */
    private function evidenceRows(): array
    {
        $rows = [];
        if (! $this->tableHasColumn('violation_evidence_tbl', 'disk_path')) {
            return $rows;
        }

        foreach (DB::table('violation_evidence_tbl')->whereNotNull('disk_path')->where('disk_path', '!=', '')->get() as $row) {
            $rows[] = [(int) $row->evidence_id, (string) $row->disk_path, null];
        }

        return $rows;
    }

    /**
     * Current proof file on each sanction record + historical proof rows.
     *
     * @return array<int, array{0:int,1:string,2:string|null}>
     */
    private function proofRows(): array
    {
        $rows = [];

        if ($this->tableHasColumn('student_sanction_records_tbl', 'proof_path')) {
            foreach (DB::table('student_sanction_records_tbl')->whereNotNull('proof_path')->where('proof_path', '!=', '')->get() as $row) {
                $rows[] = [(int) $row->record_id, (string) $row->proof_path, null];
            }
        }

        if ($this->tableHasColumn('sanction_proofs_tbl', 'disk_path')) {
            foreach (DB::table('sanction_proofs_tbl')->whereNotNull('disk_path')->where('disk_path', '!=', '')->get() as $row) {
                $rows[] = [(int) $row->proof_id, (string) $row->disk_path, null];
            }
        }

        return $rows;
    }

    /**
     * @return array<int, array{0:int,1:string,2:string|null}>
     */
    private function attachmentRows(): array
    {
        $rows = [];
        if (! $this->tableHasColumn('announcement_attachments', 'storage_path')) {
            return $rows;
        }

        foreach (DB::table('announcement_attachments')->whereNotNull('storage_path')->where('storage_path', '!=', '')->get() as $row) {
            // Update the recorded storage_disk to the target after a successful copy.
            $rows[] = [(int) $row->attachment_id, (string) $row->storage_path, 'storage_disk'];
        }

        return $rows;
    }

    private function migrateObject(
        Filesystem $source,
        Filesystem $target,
        string $path,
        int $recordId,
        string $label,
        ?string $updateColumn,
    ): string {
        $path = ltrim($path, '/');

        if ($path === '') {
            return 'missing';
        }

        try {
            // Already present on the destination? Skip (re-runnable).
            if ($target->exists($path)) {
                return 'skipped';
            }

            if (! $source->exists($path)) {
                $this->warn("  [{$label}] #{$recordId}: source file missing ({$path})");

                return 'missing';
            }

            $stream = $source->readStream($path);
            if ($stream === null) {
                $this->error("  [{$label}] #{$recordId}: unable to open source ({$path})");

                return 'failed';
            }

            $written = $target->writeStream($path, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            if ($written === false) {
                $this->error("  [{$label}] #{$recordId}: write to target failed ({$path})");

                return 'failed';
            }

            // Verify the destination before considering it migrated.
            if (! $target->exists($path)) {
                $this->error("  [{$label}] #{$recordId}: verification failed on target ({$path})");

                return 'failed';
            }

            // Keep metadata accurate where a disk column exists.
            if ($updateColumn !== null) {
                $table = $updateColumn === 'storage_disk' ? 'announcement_attachments' : null;
                if ($table !== null) {
                    DB::table($table)->where('attachment_id', $recordId)->update([$updateColumn => $this->targetDiskName()]);
                }
            }

            $this->line("  [{$label}] #{$recordId}: migrated ({$path})");

            return 'migrated';
        } catch (\Throwable $e) {
            // Never log credentials; log only the technical message + path.
            $this->error("  [{$label}] #{$recordId}: error migrating ({$path}): {$e->getMessage()}");

            return 'failed';
        }
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }

    private function targetDiskName(): string
    {
        return (string) $this->option('target');
    }
}
