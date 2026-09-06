<?php

namespace Tests\Feature;

use App\Models\BackupHistory;
use App\Services\Backup\BackupDecisionService;
use App\Services\Backup\BinaryLogSupportService;
use Illuminate\Support\Carbon;

class BackupDecisionServiceTest extends BackupTestCase
{
    private function serviceWithProbe(array $probe): BackupDecisionService
    {
        $binaryLog = $this->createMock(BinaryLogSupportService::class);
        $binaryLog->method('probe')->willReturn($probe);

        return new BackupDecisionService($binaryLog);
    }

    private function binlogAvailableProbe(): array
    {
        return [
            'available' => true,
            'log_bin' => 'ON',
            'binlog_format' => 'ROW',
            'binary_logs' => ['binlog.000001'],
            'archive_dir_configured' => true,
            'mysqlbinlog_available' => true,
            'reason' => 'ok',
        ];
    }

    private function binlogUnavailableProbe(): array
    {
        return [
            'available' => false,
            'log_bin' => 'ON',
            'binlog_format' => 'ROW',
            'binary_logs' => null,
            'archive_dir_configured' => false,
            'mysqlbinlog_available' => false,
            'reason' => 'No readable binlog archive directory configured.',
        ];
    }

    public function test_full_backup_is_selected_when_no_successful_full_exists(): void
    {
        // Only failed / old records exist.
        BackupHistory::create([
            'backup_name' => 'snapshot_old',
            'backup_type' => BackupHistory::TYPE_DAILY_SNAPSHOT,
            'status' => BackupHistory::STATUS_SUCCESS,
            'started_at' => now()->subDays(1),
            'completed_at' => now()->subDays(1),
        ]);

        $decision = $this->serviceWithProbe($this->binlogAvailableProbe())->decide();

        $this->assertSame(BackupHistory::TYPE_FULL, $decision['type']);
        $this->assertTrue($decision['due_for_full']);
    }

    public function test_full_backup_is_selected_when_14_days_have_elapsed(): void
    {
        BackupHistory::create([
            'backup_name' => 'full_old',
            'backup_type' => BackupHistory::TYPE_FULL,
            'status' => BackupHistory::STATUS_SUCCESS,
            'started_at' => Carbon::now()->subDays(14)->subHour(),
            'completed_at' => Carbon::now()->subDays(14)->subHour(),
        ]);

        $decision = $this->serviceWithProbe($this->binlogAvailableProbe())->decide();

        $this->assertSame(BackupHistory::TYPE_FULL, $decision['type']);
    }

    public function test_incremental_is_selected_within_14_days_when_binlogs_available(): void
    {
        BackupHistory::create([
            'backup_name' => 'full_recent',
            'backup_type' => BackupHistory::TYPE_FULL,
            'status' => BackupHistory::STATUS_SUCCESS,
            'started_at' => Carbon::now()->subDays(5),
            'completed_at' => Carbon::now()->subDays(5),
        ]);

        $decision = $this->serviceWithProbe($this->binlogAvailableProbe())->decide(Carbon::now());

        $this->assertSame(BackupHistory::TYPE_INCREMENTAL, $decision['type']);
        $this->assertFalse($decision['due_for_full']);
    }

    public function test_daily_snapshot_fallback_within_14_days_when_binlogs_unavailable(): void
    {
        BackupHistory::create([
            'backup_name' => 'full_recent',
            'backup_type' => BackupHistory::TYPE_FULL,
            'status' => BackupHistory::STATUS_SUCCESS,
            'started_at' => Carbon::now()->subDays(5),
            'completed_at' => Carbon::now()->subDays(5),
        ]);

        $decision = $this->serviceWithProbe($this->binlogUnavailableProbe())->decide(Carbon::now());

        $this->assertSame(BackupHistory::TYPE_DAILY_SNAPSHOT, $decision['type']);
        // Fallback must NEVER claim incremental.
        $this->assertNotSame(BackupHistory::TYPE_INCREMENTAL, $decision['type']);
    }

    public function test_failed_full_backup_does_not_reset_the_full_interval(): void
    {
        // Last SUCCESSFUL full was 20 days ago; a FAILED full was attempted
        // yesterday. The next decision must STILL be FULL (interval not reset).
        BackupHistory::create([
            'backup_name' => 'full_success_old',
            'backup_type' => BackupHistory::TYPE_FULL,
            'status' => BackupHistory::STATUS_SUCCESS,
            'started_at' => Carbon::now()->subDays(20),
            'completed_at' => Carbon::now()->subDays(20),
        ]);

        BackupHistory::create([
            'backup_name' => 'full_failed_yesterday',
            'backup_type' => BackupHistory::TYPE_FULL,
            'status' => BackupHistory::STATUS_FAILED,
            'started_at' => Carbon::now()->subDay(),
            'completed_at' => Carbon::now()->subDay(),
            'error_message' => 'dump failed',
        ]);

        $decision = $this->serviceWithProbe($this->binlogAvailableProbe())->decide(Carbon::now());

        $this->assertSame(BackupHistory::TYPE_FULL, $decision['type']);
        $this->assertSame('full_success_old', $decision['last_successful_full']->backup_name);
    }

    public function test_elapsed_time_is_measured_from_last_successful_full_completed_at(): void
    {
        // full_a is the MOST RECENT successful full (3 days ago).
        BackupHistory::create([
            'backup_name' => 'full_a',
            'backup_type' => BackupHistory::TYPE_FULL,
            'status' => BackupHistory::STATUS_SUCCESS,
            'started_at' => Carbon::now()->subDays(3),
            'completed_at' => Carbon::now()->subDays(3),
        ]);
        BackupHistory::create([
            'backup_name' => 'full_b',
            'backup_type' => BackupHistory::TYPE_FULL,
            'status' => BackupHistory::STATUS_SUCCESS,
            'started_at' => Carbon::now()->subDays(13),
            'completed_at' => Carbon::now()->subDays(13),
        ]);

        // 3 days since the most recent successful full -> within window.
        $decision = $this->serviceWithProbe($this->binlogUnavailableProbe())->decide(Carbon::now());

        $this->assertSame(BackupHistory::TYPE_DAILY_SNAPSHOT, $decision['type']);
        $this->assertSame('full_a', $decision['last_successful_full']->backup_name);
    }
}
