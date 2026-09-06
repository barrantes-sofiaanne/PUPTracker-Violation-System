<?php

namespace Tests\Feature;

use App\Models\BackupHistory;
use Illuminate\Support\Facades\Artisan;

/**
 * Exercises the backup Artisan commands against the in-memory DB.
 */
class BackupCommandTest extends BackupTestCase
{
    public function test_backup_status_command_runs_without_binlogs(): void
    {
        BackupHistory::create([
            'backup_name' => 'full_recent',
            'backup_type' => 'full',
            'status' => 'success',
            'started_at' => now()->subDay(),
            'completed_at' => now()->subDay(),
        ]);

        $exit = Artisan::call('backup:status');
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('available', $output);
        $this->assertStringContainsString('full_recent', $output);
        $this->assertStringContainsString('FULL', strtoupper($output));
    }

    public function test_backup_prune_runs_as_dry_run_by_default(): void
    {
        $exit = Artisan::call('backup:prune');
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Dry run', $output);
        $this->assertStringContainsString('no backups were deleted', $output);
    }

    public function test_backup_run_rejects_unknown_forced_type(): void
    {
        $exit = Artisan::call('backup:run', ['--type' => 'bogus']);

        $this->assertNotSame(0, $exit);
    }

    public function test_backup_report_command_records_a_success(): void
    {
        $exit = Artisan::call('backup:report', [
            'backupName' => 'full_cmd_2026-09-06_020000',
            '--status' => 'success',
            '--type' => 'full',
            '--checksum' => str_repeat('a', 64),
            '--size' => '2048',
        ]);

        $this->assertSame(0, $exit);
        $this->assertDatabaseHas('backup_history', [
            'backup_name' => 'full_cmd_2026-09-06_020000',
            'status' => 'success',
            'backup_type' => 'full',
        ]);
    }

    public function test_backup_report_command_is_idempotent(): void
    {
        Artisan::call('backup:report', [
            'backupName' => 'same_cmd',
            '--status' => 'failed',
            '--error' => 'boom',
        ]);
        Artisan::call('backup:report', [
            'backupName' => 'same_cmd',
            '--status' => 'success',
        ]);

        $this->assertSame(1, BackupHistory::where('backup_name', 'same_cmd')->count());
        $this->assertDatabaseHas('backup_history', [
            'backup_name' => 'same_cmd',
            'status' => 'success',
        ]);
    }
}
