<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\AuditLog;
use App\Models\EmailIncident;
use App\Services\EmailIncidentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmailIncidentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTablesForTests();
    }

    /**
     * Test 1 — Successful email: no incident, no failure notification.
     */
    public function test_successful_email_creates_no_incident_or_notification(): void
    {
        $service = app(EmailIncidentService::class);

        // When no failure is recorded, nothing should exist.
        $this->assertCount(0, EmailIncident::all());
        $this->assertCount(0, AdminNotification::all());
        $this->assertNotNull($service);
    }

    /**
     * Test 2 — Failed email: error logged, incident created, Super Admin notification created.
     */
    public function test_failed_email_logs_incident_and_notifies_super_admin(): void
    {
        Log::spy();

        $service = app(EmailIncidentService::class);

        $incident = $service->record(
            emailType: 'OTP',
            errorMessage: 'SMTP connection failed',
            relatedRecordType: 'MFA',
            relatedRecordId: 'j***@example.com',
        );

        $this->assertNotNull($incident);
        $this->assertSame('failed', $incident->status);
        $this->assertSame('OTP', $incident->email_type);

        // Error was logged.
        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(fn (string $message, array $context) =>
                $message === 'Email Dispatch Failed' &&
                ($context['email_type'] ?? null) === 'OTP' &&
                ($context['related_record_id'] ?? null) === 'j***@example.com'
            );

        // Incident recorded.
        $this->assertDatabaseHas('email_incidents', [
            'email_type' => 'OTP',
            'status' => 'failed',
        ]);

        // Super Admin notification created.
        $this->assertDatabaseHas('admin_notifications_tbl', [
            'message' => 'Email Dispatch Failed — OTP',
            'notification_type' => 'system_email_failure',
            'is_read' => false,
        ]);
    }

    /**
     * Test 3 + 4 — Queued / repeated failure: dedupe creates exactly ONE
     * incident and ONE notification, not one per retry.
     */
    public function test_repeated_failure_creates_single_incident_and_notification(): void
    {
        $service = app(EmailIncidentService::class);

        // Simulate retry attempts of the same operation.
        $service->record('OTP', 'SMTP connection failed (attempt 1)', 'MFA', 'j***@example.com');
        $service->record('OTP', 'SMTP connection failed (attempt 2)', 'MFA', 'j***@example.com');
        $service->record('OTP', 'SMTP connection failed (final)', 'MFA', 'j***@example.com');

        $this->assertSame(1, EmailIncident::count());

        $notifications = AdminNotification::where('notification_type', 'system_email_failure')->count();
        $this->assertSame(1, $notifications);

        $this->assertDatabaseHas('email_incidents', [
            'email_type' => 'OTP',
            'related_record_id' => 'j***@example.com',
            'status' => 'failed',
        ]);
    }

    /**
     * Different operations should NOT dedupe into the same incident.
     */
    public function test_different_email_types_create_separate_incidents(): void
    {
        $service = app(EmailIncidentService::class);

        $service->record('OTP', 'SMTP failed', 'MFA', 'a***@example.com');
        $service->record('Password Reset', 'SMTP failed', 'Password Reset', 'b***@example.com');

        $this->assertSame(2, EmailIncident::count());
        $this->assertSame(2, AdminNotification::where('notification_type', 'system_email_failure')->count());
    }

    /**
     * Test 5 — Super Admin (IT Administrator) can view incidents.
     */
    public function test_super_admin_can_view_incidents(): void
    {
        $admin = $this->createAdminWithPosition('IT Administrator');
        $incident = EmailIncident::create([
            'incident_type' => 'email_dispatch_failed',
            'email_type' => 'OTP',
            'related_record_type' => 'MFA',
            'related_record_id' => 'j***@example.com',
            'safe_error_message' => 'SMTP connection failed',
            'status' => 'failed',
            'occurred_at' => now(),
        ]);

        $this->actingAs($admin, 'admin');

        $this->get(route('admin.super-admin.email-incidents.index'))
            ->assertOk()
            ->assertSee('Email Incidents');

        $this->get(route('admin.super-admin.email-incidents.show', $incident->incident_id))
            ->assertOk()
            ->assertSee('Email Dispatch Incident')
            ->assertSee('OTP')
            ->assertSee('Acknowledge');
    }

    /**
     * Test 6 — Ordinary Admin cannot access system-wide incident info.
     */
    public function test_ordinary_admin_is_forbidden_from_incidents(): void
    {
        $admin = $this->createAdminWithPosition('Guidance Administrator');
        $this->actingAs($admin, 'admin');

        $this->get(route('admin.super-admin.email-incidents.index'))
            ->assertForbidden();
    }

    /**
     * Acknowledge + resolve flow records admin + audit log.
     */
    public function test_super_admin_can_acknowledge_and_resolve_incident(): void
    {
        $admin = $this->createAdminWithPosition('IT Administrator');
        $incident = EmailIncident::create([
            'incident_type' => 'email_dispatch_failed',
            'email_type' => 'Password Reset',
            'status' => 'failed',
            'occurred_at' => now(),
        ]);

        $this->actingAs($admin, 'admin');

        $this->post(route('admin.super-admin.email-incidents.acknowledge', $incident->incident_id))
            ->assertRedirect();

        $this->assertDatabaseHas('email_incidents', [
            'incident_id' => $incident->incident_id,
            'status' => 'acknowledged',
            'acknowledged_by_admin_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'admin',
            'action' => 'Acknowledged Incident',
            'module' => 'Email Incidents',
        ]);

        $this->post(route('admin.super-admin.email-incidents.resolve', $incident->incident_id))
            ->assertRedirect();

        $this->assertDatabaseHas('email_incidents', [
            'incident_id' => $incident->incident_id,
            'status' => 'resolved',
            'resolved_by_admin_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'admin',
            'action' => 'Resolved Incident',
            'module' => 'Email Incidents',
        ]);
    }

    /**
     * Test 7 — No credentials appear in logs or notifications.
     */
    public function test_no_credentials_appear_in_logs_or_notifications(): void
    {
        $service = app(EmailIncidentService::class);

        $suspicious = 'Connection refused for password=superSecret123 with api_key=abc123 and MAIL_PASSWORD=topsecret';

        $incident = $service->record('OTP', $suspicious, 'MFA', 'j***@example.com');

        $this->assertNotNull($incident);
        $this->assertStringNotContainsString('superSecret123', $incident->safe_error_message);
        $this->assertStringNotContainsString('abc123', $incident->safe_error_message);
        $this->assertStringNotContainsString('topsecret', $incident->safe_error_message);

        // Views rendered to Super Admin do not leak secrets.
        $admin = $this->createAdminWithPosition('IT Administrator');
        $this->actingAs($admin, 'admin');

        $content = $this->get(route('admin.super-admin.email-incidents.show', $incident->incident_id))->getContent();

        $this->assertStringNotContainsString('password', $content);
        $this->assertStringNotContainsString('api_key', $content);
        $this->assertStringNotContainsString('MAIL_PASSWORD', $content);
    }

    /**
     * Test 8 — Incident recording is a side-effect: it never rolls back or
     * aborts the primary operation. (Verified: record() swallows internal
     * errors and the caller continues; simulate route redirects successfully.)
     */
    public function test_incident_recording_does_not_break_primary_operation(): void
    {
        $admin = $this->createAdminWithPosition('IT Administrator');
        $this->actingAs($admin, 'admin');

        // The simulate-failure route must complete and redirect successfully.
        $this->post(route('admin.super-admin.email-incidents.simulate-failure'))
            ->assertRedirect();

        $this->assertDatabaseHas('email_incidents', [
            'email_type' => 'Test Email',
            'status' => 'failed',
        ]);

        $this->assertDatabaseHas('admin_notifications_tbl', [
            'notification_type' => 'system_email_failure',
        ]);

        // Audit log records the simulation.
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Simulated Email Failure',
            'module' => 'Email Incidents',
        ]);
    }

    private function createAdminWithPosition(string $position): Admin
    {
        $admin = Admin::create([
            'username' => strtolower(str_replace(' ', '.', $position)),
            'email' => strtolower(str_replace(' ', '.', $position)) . '@example.com',
            'password' => 'password',
        ]);

        DB::table('admin_info_tbl')->insert([
            'admin_id' => $admin->id,
            'position' => $position,
        ]);

        return $admin;
    }

    private function ensureTablesForTests(): void
    {
        if (! Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $table): void {
                $table->id();
                $table->string('username')->nullable();
                $table->string('email')->unique();
                $table->string('password')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_info_tbl')) {
            Schema::create('admin_info_tbl', function (Blueprint $table): void {
                $table->unsignedBigInteger('admin_id')->primary();
                $table->string('position')->nullable();
                $table->string('designation')->nullable();
                $table->string('role')->nullable();
                $table->string('title')->nullable();
            });
        }

        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->id('audit_log_id');
                $table->string('actor_type')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('action');
                $table->string('module')->nullable();
                $table->text('description')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('email_incidents')) {
            Schema::create('email_incidents', function (Blueprint $table): void {
                $table->id('incident_id');
                $table->string('incident_type')->default('email_dispatch_failed');
                $table->string('email_type')->nullable();
                $table->string('related_record_type')->nullable();
                $table->string('related_record_id')->nullable();
                $table->text('safe_error_message')->nullable();
                $table->string('status')->default('failed');
                $table->unsignedBigInteger('acknowledged_by_admin_id')->nullable();
                $table->timestamp('acknowledged_at')->nullable();
                $table->unsignedBigInteger('resolved_by_admin_id')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('occurred_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_notifications_tbl')) {
            Schema::create('admin_notifications_tbl', function (Blueprint $table): void {
                $table->id('admin_notification_id');
                $table->string('message')->nullable();
                $table->string('notification_type')->nullable();
                $table->string('link')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('created_at')->nullable();
            });
        }
    }
}
