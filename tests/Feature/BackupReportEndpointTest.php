<?php

namespace Tests\Feature;

use App\Models\BackupHistory;

class BackupReportEndpointTest extends BackupTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Provide a report token for the endpoint under test.
        config()->set('services.backup.report_token', 'test-report-token-123');
    }

    public function test_report_requires_a_valid_token(): void
    {
        $this->postJson('/admin/super-admin/backups/report', [
            'status' => 'success',
            'backup_name' => 'full_test',
        ])->assertUnauthorized();
    }

    public function test_report_rejects_wrong_token(): void
    {
        $this->postJson('/admin/super-admin/backups/report', [
            'status' => 'success',
            'backup_name' => 'full_test',
        ], ['X-Backup-Token' => 'wrong-token'])->assertUnauthorized();
    }

    public function test_report_creates_a_backup_history_record(): void
    {
        $response = $this->postJson('/admin/super-admin/backups/report', [
            'status' => 'success',
            'backup_name' => 'full_2026-09-06_020000',
            'backup_type' => 'full',
            'backup_size' => 123456,
            'file_count' => 1,
            'checksum' => str_repeat('a', 64),
            'checksum_algorithm' => 'SHA-256',
            'verified_at' => now()->toDateTimeString(),
            'storage_path' => 'database-backups/full/2026/09/full_2026-09-06_020000.sql.gz',
        ], ['Authorization' => 'Bearer test-report-token-123']);

        $response->assertCreated();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('backup_history', [
            'backup_name' => 'full_2026-09-06_020000',
            'backup_type' => 'full',
            'status' => 'success',
            'backup_size' => 123456,
            'checksum_algorithm' => 'SHA-256',
        ]);
    }

    public function test_report_is_idempotent_for_the_same_backup_name(): void
    {
        $payload = [
            'status' => 'success',
            'backup_name' => 'full_same_name',
            'backup_type' => 'full',
        ];
        $headers = ['X-Backup-Token' => 'test-report-token-123'];

        $this->postJson('/admin/super-admin/backups/report', $payload, $headers)->assertCreated();
        $this->postJson('/admin/super-admin/backups/report', $payload, $headers)->assertOk();

        $this->assertSame(1, BackupHistory::where('backup_name', 'full_same_name')->count());
    }

    public function test_report_accepts_legacy_fields_without_a_type(): void
    {
        // Backward-compat: an old external job posting only status + backup_name.
        $response = $this->postJson('/admin/super-admin/backups/report', [
            'status' => 'failed',
            'backup_name' => '2026-08-26-backup',
            'token' => 'test-report-token-123',
            'error_message' => 'Connection timeout',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('backup_history', [
            'backup_name' => '2026-08-26-backup',
            'status' => 'failed',
            'error_message' => 'Connection timeout',
        ]);
    }

    public function test_report_requires_error_message_when_failed(): void
    {
        $this->postJson('/admin/super-admin/backups/report', [
            'status' => 'failed',
            'backup_name' => 'full_x',
            'token' => 'test-report-token-123',
        ])->assertStatus(422);
    }

    public function test_report_does_not_assume_verification_without_explicit_verified_at(): void
    {
        $this->postJson('/admin/super-admin/backups/report', [
            'status' => 'success',
            'backup_name' => 'full_no_verify',
            'token' => 'test-report-token-123',
            'checksum' => str_repeat('b', 64),
        ])->assertCreated();

        $this->assertDatabaseHas('backup_history', [
            'backup_name' => 'full_no_verify',
            'status' => 'success',
        ]);

        $record = BackupHistory::where('backup_name', 'full_no_verify')->first();
        $this->assertNull($record->verified_at);
        $this->assertFalse($record->isChecksumVerified());
    }
}
