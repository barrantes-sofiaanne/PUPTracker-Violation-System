<?php

namespace App\Console\Commands;

use App\Models\SanctionProof;
use App\Models\StudentSanctionRecord;
use App\Models\ViolationEvidence;
use App\Support\Storage\EvidenceDisk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Diagnose evidence/proof storage on a deployed instance.
 *
 * Answers the question an operator actually has when a file will not open:
 * is the reference missing, is the object missing, is the wrong disk being
 * used, or is storage fine and the problem is elsewhere?
 *
 * Prints NO secrets and NO raw object keys — keys are shown as SHA-256 prefixes,
 * which is enough to correlate two records (for example "the DB key and the
 * object key are the same") without exposing bucket contents.
 */
class DiagnoseEvidenceStorage extends Command
{
    protected $signature = 'evidence:diagnose
                            {--record= : Inspect one record: evidence:<id> or proof:<id>}
                            {--sample=5 : How many recent rows to inspect per table}';

    protected $description = 'Diagnose evidence/proof storage: configured disk, connectivity, and DB-vs-object mismatches.';

    public function handle(): int
    {
        $this->line('');
        $this->info('PUPTracker — evidence/proof storage diagnosis');
        $this->line(str_repeat('=', 62));

        if (! $this->reportConfiguration()) {
            return self::FAILURE;
        }

        $this->reportConnectivity();

        $target = (string) $this->option('record');

        if ($target !== '') {
            return $this->inspectSingleRecord($target);
        }

        return $this->inspectRecentRows((int) $this->option('sample'));
    }

    /**
     * Configuration summary. Returns false when the deployment is dangerous.
     */
    private function reportConfiguration(): bool
    {
        $disk = EvidenceDisk::name();
        $driver = (string) config("filesystems.disks.{$disk}.driver", 'unknown');
        $remote = EvidenceDisk::isRemote();
        $usable = EvidenceDisk::isUsable();
        $environment = (string) app()->environment();

        $this->line('');
        $this->line('CONFIGURATION');
        $this->two('environment', $environment);
        $this->two('EVIDENCE_STORAGE_DISK', $disk);
        $this->two('driver', $driver);
        $this->two('bucket configured', $this->yesNo(
            trim((string) config("filesystems.disks.{$disk}.bucket", '')) !== ''
        ));
        $this->two('credentials configured', $this->yesNo(
            trim((string) config("filesystems.disks.{$disk}.key", '')) !== ''
            && trim((string) config("filesystems.disks.{$disk}.secret", '')) !== ''
        ));
        $this->two('endpoint configured', $this->yesNo(
            trim((string) config("filesystems.disks.{$disk}.endpoint", '')) !== ''
        ));
        $this->two('config is cached', $this->yesNo(app()->configurationIsCached()));

        if ($remote && ! $usable) {
            $this->line('');
            $this->error('  EVIDENCE_STORAGE_DISK names an S3-compatible disk, but the bucket or');
            $this->error('  credentials are missing. Uploads would fail (or, without the production');
            $this->error('  guard, silently land on ephemeral storage).');

            return false;
        }

        if (EvidenceDisk::isMisconfigured()) {
            $this->line('');
            $this->error('  PRODUCTION IS MISCONFIGURED: evidence would not persist across deploys.');

            return false;
        }

        if (! $remote && EvidenceDisk::isProduction()) {
            $this->line('');
            $this->warn('  Production is using a LOCAL disk. Files will NOT survive a redeploy,');
            $this->warn('  container replacement or restart. Set EVIDENCE_STORAGE_DISK=s3.');
        }

        return true;
    }

    /**
     * Can we actually reach the configured disk?
     */
    private function reportConnectivity(): void
    {
        $this->line('');
        $this->line('CONNECTIVITY');

        try {
            $disk = EvidenceDisk::disk();
            $probe = '.storage-probe-' . bin2hex(random_bytes(4)) . '.txt';

            if (! $disk->put($probe, 'ok')) {
                $this->two('write test', 'FAILED (put returned false)');
                $this->warn('  R2 connection unavailable — check AWS_ENDPOINT, bucket and credentials.');

                return;
            }

            $readBack = $disk->exists($probe);
            $disk->delete($probe);

            $this->two('write test', $readBack ? 'OK (written, readable, removed)' : 'PARTIAL (written but not readable)');

            if (! $readBack) {
                // A read-after-write miss is the classic symptom of a bucket
                // whose objects are not immediately visible, or of two different
                // buckets being used for write and read.
                $this->warn('  A write succeeded but the object was not readable back immediately.');
            }
        } catch (\Throwable $e) {
    $this->two('write test', 'FAILED');

    $current = $e;
    $level = 0;
while ($current !== null && $level < 5) {
        $this->error(
            '  ' . class_basename($current) . ': '
            . $this->redact($current->getMessage())
        );

        $current = $current->getPrevious();
        $level++;
    }
        }
    }

    /**
     * Inspect one explicitly named record.
     */
    private function inspectSingleRecord(string $target): int
    {
        [$kind, $id] = array_pad(explode(':', $target, 2), 2, '');

        $this->line('');
        $this->line('RECORD INSPECTION');

        if ($id === '' || ! in_array($kind, ['evidence', 'proof'], true)) {
            $this->error('  Use --record=evidence:<evidence_id> or --record=proof:<record_id>.');

            return self::FAILURE;
        }

        if ($kind === 'evidence') {
            try {
                $row = ViolationEvidence::query()->find((int) $id);
            } catch (\Throwable $e) {
                return $this->rowReadFailed('violation_evidence_tbl', $e);
            }

            if (! $row) {
                $this->error('  Evidence id ' . (int) $id . ' was not found.');

                return self::FAILURE;
            }

            return $this->reportRow('evidence', (int) $row->evidence_id, $row->disk_path);
        }

        try {
            $row = StudentSanctionRecord::query()->find((int) $id);
        } catch (\Throwable $e) {
            return $this->rowReadFailed('student_sanction_records_tbl', $e);
        }

        if (! $row) {
            $this->error('  Sanction record id ' . (int) $id . ' was not found.');

            return self::FAILURE;
        }

        return $this->reportRow('proof', (int) $row->record_id, $row->proof_path);
    }

    /**
     * Report that a record could not be read.
     *
     * A file-storage question must not be answered with an unhandled database
     * exception: the configuration and connectivity sections above already
     * printed, and those are usually the real answer.
     */
    private function rowReadFailed(string $table, \Throwable $e): int
    {
        $this->error('  Could not read ' . $table . '.');
        $this->line('  ' . $this->redact($e->getMessage()));
        $this->warn('  Check the database connection before diagnosing storage.');

        return self::FAILURE;
    }

    /**
     * Sample recent rows and summarise how many are actually retrievable.
     */
    private function inspectRecentRows(int $sample): int
    {
        $sample = max(1, min(50, $sample));
        $tally = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
        $inspected = 0;

        foreach ($this->collectSampleRows($sample) as [$kind, $id, $path]) {
            $inspected++;
            $diagnosis = EvidenceDisk::diagnose($path);
            $tally[$diagnosis['case']] = ($tally[$diagnosis['case']] ?? 0) + 1;

            if ($diagnosis['case'] !== 'D') {
                $this->line(sprintf(
                    '  [%s] %s #%d — %s',
                    $diagnosis['case'],
                    $kind,
                    $id,
                    $diagnosis['meaning']
                ));
            }
        }

        $this->line('');
        $this->line('SUMMARY (' . $inspected . ' row(s) inspected)');
        $this->two('A — DB reference missing', (string) $tally['A']);
        $this->two('B — object missing from storage', (string) $tally['B']);
        $this->two('C — storage configuration mismatch', (string) $tally['C']);
        $this->two('D — object present (retrieval problem, not storage)', (string) $tally['D']);
        $this->line('');

        if ($tally['C'] > 0) {
            $this->warn('  Case C means objects exist on a DIFFERENT disk than the one configured');
            $this->warn('  for retrieval. Fix EVIDENCE_STORAGE_DISK rather than re-uploading files.');
        }

        if ($tally['B'] > 0) {
            $this->warn('  Case B means the database points at objects that are not in storage.');
        }

        return self::SUCCESS;
    }

    /**
     * Recent evidence + proof rows to inspect.
     *
     * @return array<int, array{0:string,1:int,2:?string}>
     */
    private function collectSampleRows(int $sample): array
    {
        $rows = [];

        foreach ([
            ['violation_evidence_tbl', 'evidence', 'evidence_id'],
            ['student_sanction_records_tbl', 'proof', 'record_id'],
            ['sanction_proofs_tbl', 'proof-history', 'proof_id'],
        ] as [$table, $kind, $primaryKey]) {
            try {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $query = match ($kind) {
                    'evidence' => ViolationEvidence::query(),
                    'proof' => StudentSanctionRecord::query()->whereNotNull('proof_path'),
                    default => SanctionProof::query(),
                };

                foreach ($query->orderByDesc($primaryKey)->limit($sample)->get() as $row) {
                    $rows[] = [
                        $kind,
                        (int) $row->getKey(),
                        $kind === 'proof' ? $row->proof_path : $row->disk_path,
                    ];
                }
            } catch (\Throwable $e) {
                // The row inspection is best-effort: an unreachable database must
                // not hide the configuration and connectivity results, which are
                // usually the answer to a storage problem.
                $this->warn('  Could not read ' . $table . ': ' . $this->redact($e->getMessage()));
            }
        }

        return $rows;
    }

    /**
     * Print the diagnosis for one row.
     */
    private function reportRow(string $kind, int $id, ?string $path): int
    {
        $diagnosis = EvidenceDisk::diagnose($path);

        $this->two('kind', $kind);
        $this->two('id', (string) $id);
        $this->two('path present', $this->yesNo($diagnosis['path_present']));
        $this->two('key hash (sha256, first 16)', $this->shortHash($diagnosis['key_hash']));
        $this->two('configured disk', $diagnosis['configured_disk']);
        $this->two('driver', $diagnosis['driver']);
        $this->two('disk usable', $this->yesNo($diagnosis['disk_usable']));
        $this->two('object found', $this->yesNo($diagnosis['object_found']));
        $this->two('found on', $diagnosis['found_on'] ?? '(none)');
        $this->line('');
        $this->line('  CASE ' . $diagnosis['case'] . ' — ' . $diagnosis['meaning']);

        return $diagnosis['case'] === 'D' ? self::SUCCESS : self::FAILURE;
    }

    private function two(string $label, string $value): void
    {
        $this->line(sprintf('  %-34s %s', $label, $value));
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'yes' : 'NO';
    }

    private function shortHash(?string $hash): string
    {
        return $hash === null ? '(none)' : substr($hash, 0, 16) . '...';
    }

    /**
     * Strip anything credential-shaped from an error message before printing.
     */
    private function redact(string $message): string
    {
        $message = preg_replace('#https?://[^@\s]+@#i', 'https://***@', $message);

        return mb_substr((string) $message, 0, 200);
    }
}
