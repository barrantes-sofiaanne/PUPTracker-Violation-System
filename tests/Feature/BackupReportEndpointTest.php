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

    public function test_report_rejects_unsupported_backup_type(): void
    {
        $this->postJson('/admin/super-admin/backups/report', [
            'status' => 'success',
            'backup_name' => 'full_bad_type',
            'backup_type' => 'weekly',
            'token' => 'test-report-token-123',
        ])->assertStatus(422);
    }

    public function test_report_rejects_non_sha256_checksum(): void
    {
        // Checksum must be exactly 64 hex chars when supplied.
        $this->postJson('/admin/super-admin/backups/report', [
            'status' => 'success',
            'backup_name' => 'full_bad_checksum',
            'token' => 'test-report-token-123',
            'checksum' => 'not-a-real-hash',
        ])->assertStatus(422);

        // Invalid length / non-hex also rejected.
        $this->postJson('/admin/super-admin/backups/report', [
            'status' => 'success',
            'backup_name' => 'full_bad_checksum2',
            'token' => 'test-report-token-123',
            'checksum' => str_repeat('z', 64),
        ])->assertStatus(422);
    }

    public function test_report_rejects_unsupported_checksum_algorithm(): void
    {
        $this->postJson('/admin/super-admin/backups/report', [
            'status' => 'success',
            'backup_name' => 'full_bad_algo',
            'token' => 'test-report-token-123',
            'checksum_algorithm' => 'MD5',
        ])->assertStatus(422);
    }

    public function test_report_ignores_unknown_fields_and_cannot_mass_assign(): void
    {
        $this->postJson('/admin/super-admin/backups/report', [
            'status' => 'success',
            'backup_name' => 'full_unknown_field',
            'token' => 'test-report-token-123',
            // Arbitrary unknown/extra fields are not part of the validated set
            // and must never be written.
            'is_admin' => 1,
            'backup_size' => 500,
            'file_count' => 1,
        ])->assertCreated();

        $record = BackupHistory::where('backup_name', 'full_unknown_field')->first();
        $this->assertNotNull($record);
        $this->assertSame(500, (int) $record->backup_size);
    }

    public function test_report_error_message_accepted_only_when_failed(): void
    {
        // error_message is accepted on a failed report.
        $this->postJson('/admin/super-admin/backups/report', [
            'status' => 'failed',
            'backup_name' => 'full_err_ok',
            'token' => 'test-report-token-123',
            'error_message' => 'Disk full',
        ])->assertCreated();

        // A success report must not persist an error_message.
        $this->postJson('/admin/super-admin/backups/report', [
            'status' => 'success',
            'backup_name' => 'full_success_no_err',
            'token' => 'test-report-token-123',
            'error_message' => 'should not persist',
        ])->assertCreated();

        $record = BackupHistory::where('backup_name', 'full_success_no_err')->first();
        $this->assertNotNull($record);
        $this->assertNull($record->error_message);
    }

    public function test_report_endpoint_is_rate_limited(): void
    {
        // The named "backup-report" limiter allows 10 requests per minute.
        $headers = ['X-Backup-Token' => 'test-report-token-123'];
        $payload = ['status' => 'success', 'backup_name' => 'rate_limited_backup'];

        for ($i = 0; $i < 10; $i++) {
            $payload['backup_name'] = 'rate_limited_backup_' . $i;
            $this->postJson('/admin/super-admin/backups/report', $payload, $headers)->assertCreated();
        }

        // The 11th request within the minute must be throttled (429).
        $payload['backup_name'] = 'rate_limited_backup_11';
        $this->postJson('/admin/super-admin/backups/report', $payload, $headers)
            ->assertStatus(429);
    }

    public function test_mailgun_direct_test_route_is_removed(): void
    {
        $this->get('/mailgun-direct-test')->assertNotFound();
        $this->post('/mailgun-direct-test')->assertNotFound();
    }

    // ------------------------------------------------------------------
    // CSRF exemption for the machine-to-machine backup report webhook
    // ------------------------------------------------------------------

    public function test_backup_report_uri_is_registered_in_csrf_exclusions(): void
    {
        $reflection = new \ReflectionClass(
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class
        );
        $property = $reflection->getProperty('neverVerify');
        $property->setAccessible(true);

        $excluded = (array) $property->getValue();

        $this->assertContains('admin/super-admin/backups/report', $excluded);

        // Only the backup report webhook is exempt — no other backup/admin
        // route should be CSRF-exempt.
        $this->assertNotContains('admin/super-admin/backups', $excluded);
        $this->assertNotContains('admin/*', $excluded);
        $this->assertNotContains('*', $excluded);
    }

    public function test_backup_report_accepts_post_without_csrf_token_but_with_valid_backup_token(): void
    {
        // The backup report webhook is exempt from CSRF because it is a
        // machine-to-machine call. The BACKUP_REPORT_TOKEN is the authentication
        // mechanism. A request carrying the correct backup token (and NO CSRF
        // token) must be accepted.
        $response = $this->postJson('/admin/super-admin/backups/report', [
            'status' => 'success',
            'backup_name' => 'full_no_csrf',
            'backup_type' => 'full',
        ], [
            'Authorization' => 'Bearer test-report-token-123',
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('backup_history', [
            'backup_name' => 'full_no_csrf',
            'status' => 'success',
        ]);
    }

    public function test_backup_report_without_valid_backup_token_is_rejected_even_without_csrf(): void
    {
        // CSRF exemption must NOT weaken token auth: an invalid/missing backup
        // token is rejected with 401 before any record is written.
        $this->postJson('/admin/super-admin/backups/report', [
            'status' => 'success',
            'backup_name' => 'full_no_token',
        ])->assertUnauthorized();

        $this->assertDatabaseMissing('backup_history', [
            'backup_name' => 'full_no_token',
        ]);
    }

    public function test_backup_report_is_exempt_from_csrf_but_other_web_posts_are_not(): void
    {
        // Temporarily report a non-testing env so the framework's automatic
        // "running unit tests" CSRF bypass is disabled. This makes the full
        // HTTP stack enforce real CSRF verification, letting us prove that:
        //   (a) the backup report webhook is CSRF-exempt (no session token
        //       needed, backup token suffices), and
        //   (b) a normal web POST route still requires a CSRF token (419).
        $app = app();
        $originalEnv = $app->bound('env') ? $app->make('env') : 'testing';
        $app->instance('env', 'production');

        try {
            // (a) Backup report webhook: no CSRF token, valid backup token -> accepted.
            $response = $this->postJson('/admin/super-admin/backups/report', [
                'status' => 'success',
                'backup_name' => 'full_csrf_exempt',
            ], ['X-Backup-Token' => 'test-report-token-123']);

            $response->assertStatus(201);
            $this->assertDatabaseHas('backup_history', [
                'backup_name' => 'full_csrf_exempt',
                'status' => 'success',
            ]);

            // (b) Normal web POST route (no CSRF token) -> 419.
            $this->post('/admin/login', [
                'email' => 'nobody@example.com',
                'password' => 'whatever',
            ])->assertStatus(419);
        } finally {
            $app->instance('env', $originalEnv);
        }
    }

    public function test_normal_web_post_route_remains_csrf_protected_when_not_exempt(): void
    {
        // Sanity: with the unit-test CSRF bypass disabled, a NON-exempt web
        // POST without a CSRF token is rejected with 419 even when it carries
        // the backup-report header (proving the exemption is path-specific,
        // not header-based).
        $app = app();
        $originalEnv = $app->bound('env') ? $app->make('env') : 'testing';
        $app->instance('env', 'production');

        try {
            $this->withHeader('X-Backup-Token', 'test-report-token-123')
                ->post('/admin/login', [
                    'email' => 'nobody@example.com',
                    'password' => 'whatever',
                ])
                ->assertStatus(419);
        } finally {
            $app->instance('env', $originalEnv);
        }
    }
}
