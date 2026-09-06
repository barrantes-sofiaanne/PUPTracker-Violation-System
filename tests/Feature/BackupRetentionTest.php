<?php

namespace Tests\Feature;

use App\Models\BackupHistory;
use App\Services\Backup\RetentionService;
use Illuminate\Support\Carbon;

class BackupRetentionTest extends BackupTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('backup.retention.full_days', 60);
        config()->set('backup.retention.incremental_days', 10);
        config()->set('backup.retention.snapshot_days', 10);
    }

    public function test_retention_does_not_delete_a_full_while_a_chain_depends_on_it(): void
    {
        // Old FULL (70 days old) that still anchors a retained incremental.
        $full = BackupHistory::create([
            'backup_name' => 'full_old',
            'backup_type' => 'full',
            'status' => 'success',
            'started_at' => Carbon::now()->subDays(70),
            'completed_at' => Carbon::now()->subDays(70),
            'checksum' => str_repeat('a', 64),
            'checksum_algorithm' => 'SHA-256',
            'verified_at' => Carbon::now()->subDays(70),
            'storage_path' => 'database-backups/full/2026/07/full_old.sql.gz',
        ]);

        BackupHistory::create([
            'backup_name' => 'incremental_recent',
            'backup_type' => 'incremental',
            'status' => 'success',
            'parent_backup_id' => $full->id,
            'started_at' => Carbon::now()->subDays(2),
            'completed_at' => Carbon::now()->subDays(2),
            'checksum' => str_repeat('b', 64),
            'checksum_algorithm' => 'SHA-256',
            'verified_at' => Carbon::now()->subDays(2),
            'storage_path' => 'database-backups/incremental/2026/09/incremental_recent.sql.gz',
        ]);

        $plan = (new RetentionService())->plan();

        $this->assertContains($full->id, $plan['protected_full_ids']);

        // The old full is listed but explicitly KEPT (its chain is in use).
        $fullCandidate = collect($plan['candidates'])->firstWhere('id', $full->id);
        $this->assertNotNull($fullCandidate);
        $this->assertSame(RetentionService::ACTION_KEEP, $fullCandidate['action']);
    }

    public function test_expired_orphan_full_is_a_delete_candidate(): void
    {
        $full = BackupHistory::create([
            'backup_name' => 'full_old_orphan',
            'backup_type' => 'full',
            'status' => 'success',
            'started_at' => Carbon::now()->subDays(70),
            'completed_at' => Carbon::now()->subDays(70),
            'checksum' => str_repeat('e', 64),
            'checksum_algorithm' => 'SHA-256',
            'verified_at' => Carbon::now()->subDays(70),
            'storage_path' => 'database-backups/full/2026/07/full_old_orphan.sql.gz',
        ]);

        $plan = (new RetentionService())->plan();

        $candidate = collect($plan['candidates'])->firstWhere('id', $full->id);
        $this->assertNotNull($candidate);
        $this->assertSame(RetentionService::ACTION_DELETE_CANDIDATE, $candidate['action']);
    }

    public function test_dry_run_does_not_delete_anything(): void
    {
        BackupHistory::create([
            'backup_name' => 'snapshot_expired',
            'backup_type' => 'daily_snapshot',
            'status' => 'success',
            'started_at' => Carbon::now()->subDays(40),
            'completed_at' => Carbon::now()->subDays(40),
            'checksum' => str_repeat('f', 64),
            'checksum_algorithm' => 'SHA-256',
            'verified_at' => Carbon::now()->subDays(40),
            'storage_path' => 'database-backups/snapshot/2026/07/snapshot_expired.sql.gz',
        ]);

        $result = (new RetentionService())->executePlan(true);

        // Dry run reports the candidate as "would delete".
        $this->assertGreaterThan(0, $result['deleted']);
        $this->assertSame(1, BackupHistory::count()); // nothing actually deleted
    }
}
