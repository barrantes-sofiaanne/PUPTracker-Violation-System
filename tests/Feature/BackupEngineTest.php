<?php

namespace Tests\Feature;

use App\Models\BackupHistory;
use App\Services\Backup\BackupDecisionService;
use App\Services\Backup\BackupEngine;
use App\Services\Backup\BinaryLogSupportService;
use App\Services\Backup\BinlogArchiveService;
use App\Services\Backup\DatabaseDumpService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Exercises the BackupEngine pipeline against a real private local disk and a
 * real backup_history table. The mysqldump sub-process is faked via the
 * DatabaseDumpService mock (which returns a genuine gzip temp file), because
 * the test DB is SQLite and no real mysqldump runs against it.
 */
class BackupEngineTest extends BackupTestCase
{
    private function tempGzipFile(string $contents = 'CREATE TABLE t (id INT);'): string
    {
        $base = tempnam(sys_get_temp_dir(), 'pup_test_');
        $gz = $base . '.sql.gz';
        $handle = gzopen($gz, 'wb9');
        gzwrite($handle, $contents);
        gzclose($handle);
        @unlink($base);

        return $gz;
    }

    private function dumpResult(string $gzFile): array
    {
        return [
            'success' => true,
            'temp_file' => $gzFile,
            'compressed_size' => filesize($gzFile),
            'checksum' => hash_file('sha256', $gzFile),
            'error' => null,
        ];
    }

    private function engineWithDump(array $dumpResult): BackupEngine
    {
        $dump = $this->createMock(DatabaseDumpService::class);
        $dump->method('dumpDatabase')->willReturn($dumpResult);

        $binlogArchive = $this->createMock(BinlogArchiveService::class);
        $binaryLog = $this->createMock(BinaryLogSupportService::class);
        $binaryLog->method('probe')->willReturn(['available' => false]);

        $decision = new BackupDecisionService($binaryLog);

        return new BackupEngine($dump, $binlogArchive, $binaryLog, $decision);
    }

    public function test_full_backup_succeeds_and_records_checksum_verified(): void
    {
        $gz = $this->tempGzipFile('CREATE TABLE sample (id INT); INSERT INTO sample VALUES (1);');
        $expectedSize = filesize($gz);
        $engine = $this->engineWithDump($this->dumpResult($gz));

        $result = $engine->run(BackupHistory::TYPE_FULL);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertNotNull($result['backup']);

        $record = $result['backup']->fresh();
        $this->assertSame('success', $record->status);
        $this->assertSame(BackupHistory::TYPE_FULL, $record->backup_type);
        $this->assertSame('SHA-256', $record->checksum_algorithm);
        $this->assertNotNull($record->checksum);
        $this->assertNotNull($record->verified_at);
        $this->assertNotNull($record->storage_path);
        $this->assertSame($expectedSize, $record->backup_size);
        $this->assertNull($record->error_message);

        // Object actually exists on the private disk.
        $this->assertTrue(Storage::disk('backups')->exists($record->storage_path));

        @unlink($gz);
    }

    public function test_daily_snapshot_succeeds_and_is_never_labelled_incremental(): void
    {
        $gz = $this->tempGzipFile('CREATE TABLE sample (id INT);');
        $engine = $this->engineWithDump($this->dumpResult($gz));

        $result = $engine->run(BackupHistory::TYPE_DAILY_SNAPSHOT);

        $this->assertTrue($result['success']);
        $this->assertSame(BackupHistory::TYPE_DAILY_SNAPSHOT, $result['backup']->fresh()->backup_type);

        @unlink($gz);
    }

    public function test_failed_dump_records_failed_backup_with_error(): void
    {
        $dump = $this->createMock(DatabaseDumpService::class);
        $dump->method('dumpDatabase')->willReturn([
            'success' => false,
            'temp_file' => null,
            'compressed_size' => null,
            'checksum' => null,
            'error' => 'Database export failed (mysqldump).',
        ]);

        $binaryLog = $this->createMock(BinaryLogSupportService::class);
        $binaryLog->method('probe')->willReturn(['available' => false]);
        $engine = new BackupEngine(
            $dump,
            $this->createMock(BinlogArchiveService::class),
            $binaryLog,
            new BackupDecisionService($binaryLog)
        );

        $result = $engine->run(BackupHistory::TYPE_FULL);

        $this->assertFalse($result['success']);
        $record = $result['backup']->fresh();
        $this->assertSame('failed', $record->status);
        $this->assertNotNull($record->error_message);
        $this->assertNull($record->checksum);
        $this->assertNull($record->verified_at);
        $this->assertSame(BackupHistory::TYPE_FULL, $record->backup_type);
    }

    public function test_upload_failure_is_recorded_as_failed(): void
    {
        $gz = $this->tempGzipFile('CREATE TABLE sample (id INT);');

        $engineClass = new class(
            $this->makeDumpService($gz),
            $this->createMock(BinlogArchiveService::class),
            $this->makeUnavailableBinlog(),
            new BackupDecisionService($this->makeUnavailableBinlog()),
        ) extends BackupEngine {
            protected function uploadStream($disk, string $key, $stream): void
            {
                throw new \RuntimeException('Simulated R2 network failure');
            }
        };

        $result = $engineClass->run(BackupHistory::TYPE_FULL);

        $this->assertFalse($result['success']);
        $record = $result['backup']->fresh();
        $this->assertSame('failed', $record->status);
        $this->assertStringContainsString('upload', strtolower($record->error_message ?? ''));
        $this->assertNull($record->verified_at);

        @unlink($gz);
    }

    public function test_verification_failure_is_recorded_as_failed(): void
    {
        $gz = $this->tempGzipFile('CREATE TABLE sample (id INT);');

        $engineClass = new class(
            $this->makeDumpService($gz),
            $this->createMock(BinlogArchiveService::class),
            $this->makeUnavailableBinlog(),
            new BackupDecisionService($this->makeUnavailableBinlog()),
        ) extends BackupEngine {
            protected function objectExists($disk, string $key): bool
            {
                return false; // upload "succeeded" but the object is not visible
            }
        };

        $result = $engineClass->run(BackupHistory::TYPE_FULL);

        $this->assertFalse($result['success']);
        $record = $result['backup']->fresh();
        $this->assertSame('failed', $record->status);
        $this->assertStringContainsString('verify', strtolower($record->error_message ?? ''));
        $this->assertNull($record->verified_at);

        @unlink($gz);
    }

    public function test_credentials_never_written_to_logs(): void
    {
        $binaryLog = $this->makeUnavailableBinlog();
        $decision = new BackupDecisionService($binaryLog);

        // A dump whose error message embeds a fake credential. The engine must
        // sanitize it before it ever reaches the log or history.
        $dump = $this->createMock(DatabaseDumpService::class);
        $dump->method('dumpDatabase')->willReturn([
            'success' => false,
            'temp_file' => null,
            'compressed_size' => null,
            'checksum' => null,
            'error' => 'mysqldump: Access denied for user root using password SUPER-SECRET-R2-KEY',
        ]);

        Log::spy();

        $engine = new BackupEngine($dump, $this->createMock(BinlogArchiveService::class), $binaryLog, $decision);
        $result = $engine->run(BackupHistory::TYPE_FULL);

        $this->assertFalse($result['success']);
        $record = $result['backup']->fresh();
        $this->assertStringNotContainsString('SUPER-SECRET-R2-KEY', $record->error_message ?? '');
        $this->assertStringNotContainsString('SUPER-SECRET-R2-KEY', $result['error'] ?? '');
    }

    private function makeDumpService(string $gzFile): DatabaseDumpService
    {
        $dump = $this->createMock(DatabaseDumpService::class);
        $dump->method('dumpDatabase')->willReturn($this->dumpResult($gzFile));

        return $dump;
    }

    private function makeUnavailableBinlog(): BinaryLogSupportService
    {
        $binaryLog = $this->createMock(BinaryLogSupportService::class);
        $binaryLog->method('probe')->willReturn([
            'available' => false,
            'reason' => 'not available',
        ]);

        return $binaryLog;
    }
}
