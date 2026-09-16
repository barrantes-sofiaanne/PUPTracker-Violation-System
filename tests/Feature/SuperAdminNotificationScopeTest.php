<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\BackupHistory;
use App\Services\AdminNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * SUPER ADMIN notification separation (PART 3 / PART 4 / PART 5) and the
 * backup monitoring alerts (PART 6 / PART 7).
 *
 * The rule under test:
 *   Authentication decides WHO the user is.
 *   Role decides WHAT they may do.
 *   ACCOUNT OWNERSHIP decides WHICH notifications they receive.
 *
 * A Super Admin must NOT inherit operational/student/personnel notifications
 * simply by holding the highest role.
 */
class SuperAdminNotificationScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
    }

    // ==================================================================
    // PART 3 / PART 4 — audience separation
    // ==================================================================

    /** An operational (student-specific) notification goes to regular admins ONLY. */
    public function test_student_specific_notification_does_not_reach_the_super_admin(): void
    {
        $admin = $this->createAdmin('admin@example.com', superAdmin: false);
        $superAdmin = $this->createAdmin('it@example.com', superAdmin: true);

        // Exactly how the app writes it today (SecurityController, StudentRecord...).
        app(AdminNotificationService::class)->create(
            type: 'violation_confirmation',
            title: 'Violation Awaiting Confirmation',
            message: "Security recorded a violation for John Doe (2024-0001): Cheating.",
            targetType: 'violation',
            targetId: 1,
        );

        // Regular admin sees it.
        $this->actingAs($admin, 'admin')
            ->get(route('admin.notifications'))
            ->assertOk()
            ->assertSee('John Doe', false);

        // Super Admin MUST NOT — not in the list, and not via the badge.
        $this->actingAs($superAdmin, 'admin')
            ->get(route('admin.notifications'))
            ->assertOk()
            ->assertDontSee('John Doe', false);
    }

    /** Each operational type the app currently emits is excluded from the Super Admin. */
    public function test_every_operational_notification_type_is_excluded_from_the_super_admin(): void
    {
        $superAdmin = $this->createAdmin('it@example.com', superAdmin: true);
        $service = app(AdminNotificationService::class);

        $service->create('violation_confirmation', 't', 'OPERATIONAL-1', 'violation', 1);
        $service->create('disciplinary_sanction_request', 't', 'OPERATIONAL-2', 'sanction_request', 1);
        $service->create('completion_proof', 't', 'OPERATIONAL-3', 'sanction_record', 1);
        $service->create('sanction_overdue', 't', 'OPERATIONAL-4', 'sanction_record', 1);

        $page = $this->actingAs($superAdmin, 'admin')
            ->get(route('admin.notifications'))
            ->assertOk()
            ->getContent();

        foreach (['OPERATIONAL-1', 'OPERATIONAL-2', 'OPERATIONAL-3', 'OPERATIONAL-4'] as $needle) {
            $this->assertStringNotContainsString($needle, $page);
        }
    }

    /** A system-level alert DOES reach the Super Admin. */
    public function test_system_level_alert_reaches_the_super_admin(): void
    {
        $superAdmin = $this->createAdmin('it@example.com', superAdmin: true);

        app(AdminNotificationService::class)->createSystemAlert(
            type: 'system_backup_failure',
            title: 'Database Backup Failed',
            message: 'SYSTEM-ALERT-ONLY',
            targetType: 'backup',
            targetId: 1,
            priority: AdminNotification::PRIORITY_CRITICAL,
        );

        $this->actingAs($superAdmin, 'admin')
            ->get(route('admin.notifications'))
            ->assertOk()
            ->assertSee('SYSTEM-ALERT-ONLY', false);
    }

    /** A regular admin must not see Super Admin system alerts. */
    public function test_regular_admin_does_not_receive_super_admin_system_alerts(): void
    {
        $admin = $this->createAdmin('admin@example.com', superAdmin: false);

        app(AdminNotificationService::class)->createSystemAlert(
            type: 'system_backup_failure',
            title: 'Database Backup Failed',
            message: 'SYSTEM-INTERNAL-ALERT',
            targetType: 'backup',
            targetId: 1,
        );

        $this->actingAs($admin, 'admin')
            ->get(route('admin.notifications'))
            ->assertOk()
            ->assertDontSee('SYSTEM-INTERNAL-ALERT', false);
    }

    /**
     * Ownership is the ACCOUNT, not the role: the two inboxes are disjoint.
     */
    public function test_admin_and_super_admin_inboxes_are_disjoint(): void
    {
        $admin = $this->createAdmin('admin@example.com', superAdmin: false);
        $superAdmin = $this->createAdmin('it@example.com', superAdmin: true);
        $service = app(AdminNotificationService::class);

        $service->create('violation_confirmation', 't', 'OPERATIONAL', 'violation', 1);
        $service->createSystemAlert('system_backup_failure', 't', 'SYSTEM', 'backup', 1);

        $forAdmin = AdminNotification::query()
            ->forAudience($admin->id, false)
            ->pluck('message')->all();

        $forSuper = AdminNotification::query()
            ->forAudience($superAdmin->id, true)
            ->pluck('message')->all();

        $this->assertSame(['OPERATIONAL'], $forAdmin);
        $this->assertSame(['SYSTEM'], $forSuper);
        $this->assertEmpty(array_intersect($forAdmin, $forSuper));
    }

    /** Pre-existing rows (NULL recipient_type) keep their original audience. */
    public function test_legacy_rows_with_null_recipient_type_stay_visible_to_admins(): void
    {
        $admin = $this->createAdmin('admin@example.com', superAdmin: false);

        DB::table('admin_notifications_tbl')->insert([
            'message' => 'LEGACY-BROADCAST',
            'notification_type' => 'legacy',
            'recipient_type' => null,
            'recipient_id' => null,
            'is_read' => false,
            'created_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.notifications'))
            ->assertOk()
            ->assertSee('LEGACY-BROADCAST', false);
    }

    /** A notification addressed to one account is invisible to the other. */
    public function test_account_owned_notification_is_not_visible_to_a_different_admin(): void
    {
        $owner = $this->createAdmin('owner@example.com', superAdmin: false);
        $other = $this->createAdmin('other@example.com', superAdmin: false);

        app(AdminNotificationService::class)->create(
            type: 'violation_confirmation',
            title: 't',
            message: 'DIRECTLY-ADDRESSED',
            targetType: 'violation',
            targetId: 1,
            recipientId: $owner->id,
        );

        $this->actingAs($owner, 'admin')
            ->get(route('admin.notifications'))
            ->assertOk()
            ->assertSee('DIRECTLY-ADDRESSED', false);

        $this->actingAs($other, 'admin')
            ->get(route('admin.notifications'))
            ->assertOk()
            ->assertDontSee('DIRECTLY-ADDRESSED', false);
    }

    /**
     * IDOR: directly requesting another audience's notification id must be
     * refused, not merely hidden from the list.
     */
    public function test_super_admin_cannot_open_an_operational_notification_by_id(): void
    {
        $superAdmin = $this->createAdmin('it@example.com', superAdmin: true);

        $operational = AdminNotification::create([
            'message' => 'OPERATIONAL-SECRET',
            'notification_type' => 'violation_confirmation',
            'recipient_type' => AdminNotification::AUDIENCE_ADMIN,
            'recipient_id' => null,
            'is_read' => false,
            'created_at' => now(),
        ]);

        // Read/unread actions must refuse it.
        $this->actingAs($superAdmin, 'admin')
            ->post(route('admin.notifications.read', $operational))
            ->assertForbidden();

        $this->actingAs($superAdmin, 'admin')
            ->post(route('admin.notifications.unread', $operational))
            ->assertForbidden();

        // The row was not modified.
        $this->assertFalse((bool) $operational->fresh()->is_read);
    }

    /** The same protection applies in the other direction. */
    public function test_regular_admin_cannot_open_a_super_admin_alert_by_id(): void
    {
        $admin = $this->createAdmin('admin@example.com', superAdmin: false);

        $alert = AdminNotification::create([
            'message' => 'SYSTEM-SECRET',
            'notification_type' => 'system_backup_failure',
            'recipient_type' => AdminNotification::AUDIENCE_SUPER_ADMIN,
            'is_read' => false,
            'created_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.notifications.read', $alert))
            ->assertForbidden();

        $this->assertFalse((bool) $alert->fresh()->is_read);
    }

    /** The unread badge is audience-scoped, not a global count. */
    public function test_unread_count_is_audience_scoped(): void
    {
        $superAdmin = $this->createAdmin('it@example.com', superAdmin: true);
        $service = app(AdminNotificationService::class);

        foreach (['a', 'b', 'c'] as $tag) {
            $service->create('violation_confirmation', 't', 'op ' . $tag, 'violation', 1);
        }

        // Super Admin has no unread operational notifications...
        $count = AdminNotification::query()
            ->forAudience($superAdmin->id, true)
            ->where('is_read', false)
            ->count();

        $this->assertSame(0, $count);

        // ...but does count system alerts.
        $service->createSystemAlert('system_backup_failure', 't', 'sys', 'backup', 1);

        $count = AdminNotification::query()
            ->forAudience($superAdmin->id, true)
            ->where('is_read', false)
            ->count();

        $this->assertSame(1, $count);
    }

    // ==================================================================
    // PART 5 — priority
    // ==================================================================

    public function test_priority_is_stored_and_defaults_to_info(): void
    {
        $service = app(AdminNotificationService::class);

        $routine = $service->create('violation_confirmation', 't', 'm', 'violation', 1);
        $critical = $service->createSystemAlert('system_backup_failure', 't', 'm', 'backup', 1);

        $this->assertSame(AdminNotification::PRIORITY_INFO, $routine->priorityLevel());
        $this->assertSame(AdminNotification::PRIORITY_CRITICAL, $critical->priorityLevel());
    }

    // ==================================================================
    // PART 7 — backup failure notification
    // ==================================================================

    private function sendBackupReport(array $payload): int
    {
        $request = \Illuminate\Http\Request::create('/admin/super-admin/backups/report', 'POST', $payload);
        $request->headers->set('Authorization', 'Bearer test-backup-token');

        return app(\Illuminate\Contracts\Http\Kernel::class)->handle($request)->getStatusCode();
    }

    public function test_failed_backup_creates_a_critical_super_admin_alert(): void
    {
        Config::set('services.backup.report_token', 'test-backup-token');

        $status = $this->sendBackupReport([
            'backup_name' => 'backup_failed_1',
            'backup_type' => 'full',
            'status' => 'failed',
            'error_message' => 'mysqldump exited 1: connection lost',
        ]);

        $this->assertSame(201, $status);

        // The failure is still recorded (existing behaviour preserved).
        $this->assertDatabaseHas('backup_history', [
            'backup_name' => 'backup_failed_1',
            'status' => 'failed',
        ]);

        // ...and now also raises a CRITICAL Super Admin system alert.
        $notification = AdminNotification::where('notification_type', 'system_backup_failure')->first();

        $this->assertNotNull($notification, 'A failed backup must notify the Super Admin.');
        $this->assertSame(AdminNotification::AUDIENCE_SUPER_ADMIN, $notification->audience());
        $this->assertSame(AdminNotification::PRIORITY_CRITICAL, $notification->priorityLevel());
        $this->assertStringContainsString('FAILED', $notification->message);
        $this->assertStringContainsString('connection lost', $notification->message);
    }

    public function test_successful_verified_backup_does_not_notify(): void
    {
        Config::set('services.backup.report_token', 'test-backup-token');

        $this->sendBackupReport([
            'backup_name' => 'backup_ok_1',
            'backup_type' => 'full',
            'status' => 'success',
            'verified_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $this->assertDatabaseHas('backup_history', ['backup_name' => 'backup_ok_1', 'status' => 'success']);

        // §5: a healthy backup is a normal event — no notification noise.
        $this->assertSame(0, AdminNotification::count());
    }

    public function test_unverified_backup_raises_a_warning(): void
    {
        Config::set('services.backup.report_token', 'test-backup-token');

        $this->sendBackupReport([
            'backup_name' => 'backup_unverified_1',
            'backup_type' => 'full',
            'status' => 'success',
        ]);

        $notification = AdminNotification::where('notification_type', 'system_backup_unverified')->first();

        $this->assertNotNull($notification);
        $this->assertSame(AdminNotification::PRIORITY_WARNING, $notification->priorityLevel());
        $this->assertSame(AdminNotification::AUDIENCE_SUPER_ADMIN, $notification->audience());
    }

    /** §6: no infrastructure secret may appear in a notification. */
    public function test_backup_alert_never_exposes_secrets(): void
    {
        Config::set('services.backup.report_token', 'test-backup-token');

        $this->sendBackupReport([
            'backup_name' => 'backup_failed_2',
            'backup_type' => 'full',
            'status' => 'failed',
            'error_message' => 'connection refused',
        ]);

        $messages = AdminNotification::pluck('message')->implode(' ');

        foreach (['password', 'secret', 'R2_', 'BACKUP_REPORT_TOKEN', 'test-backup-token', 'mysql://'] as $needle) {
            $this->assertStringNotContainsString($needle, $messages);
        }
    }

    /** A notification failure must not break the backup webhook's 2xx. */
    public function test_backup_webhook_still_succeeds_when_notification_write_fails(): void
    {
        Config::set('services.backup.report_token', 'test-backup-token');

        AdminNotification::creating(function (): void {
            throw new \RuntimeException('simulated notification failure');
        });

        $status = $this->sendBackupReport([
            'backup_name' => 'backup_failed_3',
            'backup_type' => 'full',
            'status' => 'failed',
            'error_message' => 'boom',
        ]);

        // The backup record is still accepted — the external job must not retry
        // and duplicate the record because notification writing failed.
        $this->assertSame(201, $status);
        $this->assertDatabaseHas('backup_history', ['backup_name' => 'backup_failed_3']);
    }

    // ==================================================================
    // PART 3 — "backup has not reported"
    // ==================================================================

    public function test_missing_backup_report_alerts_the_super_admin(): void
    {
        Artisan::call('backup:check-reporting');

        $notification = AdminNotification::where('notification_type', 'system_backup_not_reporting')->first();

        $this->assertNotNull($notification);
        $this->assertSame(AdminNotification::AUDIENCE_SUPER_ADMIN, $notification->audience());
        $this->assertSame(AdminNotification::PRIORITY_CRITICAL, $notification->priorityLevel());
    }

    public function test_stale_backup_report_alerts_the_super_admin(): void
    {
        BackupHistory::create([
            'backup_name' => 'stale',
            'status' => 'success',
            'completed_at' => now()->subHours(48),
        ]);

        Artisan::call('backup:check-reporting');

        $this->assertSame(
            1,
            AdminNotification::where('notification_type', 'system_backup_not_reporting')->count()
        );
    }

    public function test_recent_backup_report_does_not_alert(): void
    {
        BackupHistory::create([
            'backup_name' => 'fresh',
            'status' => 'success',
            'completed_at' => now(),
        ]);

        Artisan::call('backup:check-reporting');

        $this->assertSame(0, AdminNotification::count());
    }

    /** §5: an unresolved outage must not re-alert on every run. */
    public function test_not_reporting_alert_is_not_duplicated_while_unread(): void
    {
        Artisan::call('backup:check-reporting');
        Artisan::call('backup:check-reporting');
        Artisan::call('backup:check-reporting');

        $this->assertSame(
            1,
            AdminNotification::where('notification_type', 'system_backup_not_reporting')->count()
        );
    }

    public function test_dry_run_does_not_write_a_notification(): void
    {
        Artisan::call('backup:check-reporting', ['--dry-run' => true]);

        $this->assertSame(0, AdminNotification::count());
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    /**
     * @param  bool  $superAdmin  true = IT Administrator (Super Admin) by
     *                           Position, which is what isItAdministrator() reads.
     */
    private function createAdmin(string $email, bool $superAdmin): Admin
    {
        $admin = Admin::create([
            'email' => $email,
            'password' => Hash::make('password'),
        ]);

        DB::table('admin_info_tbl')->insert([
            'admin_id' => $admin->id,
            'Position' => $superAdmin ? 'IT Administrator' : 'Administrator',
            'firstname' => $superAdmin ? 'IT' : 'Ada',
            'lastname' => 'Admin',
        ]);

        return $admin;
    }

    private function createTables(): void
    {
        foreach ([
            'admins' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('username')->nullable();
                $table->string('email');
                $table->string('password');
                $table->text('mfa_totp_secret')->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
                $table->timestamps();
            },
            'admin_info_tbl' => function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->string('Position')->nullable();
                $table->string('designation')->nullable();
                $table->string('role')->nullable();
                $table->string('title')->nullable();
                $table->string('firstname')->nullable();
                $table->string('middlename')->nullable();
                $table->string('lastname')->nullable();
            },
            'admin_notifications_tbl' => function (Blueprint $table): void {
                $table->increments('admin_notification_id');
                $table->text('message');
                $table->string('title')->nullable();
                $table->string('notification_type')->nullable();
                $table->string('priority', 16)->nullable();
                $table->string('target_type')->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->string('recipient_type')->nullable();
                $table->unsignedBigInteger('recipient_id')->nullable();
                $table->boolean('is_read')->default(false);
                $table->dateTime('created_at')->nullable();
            },
            'backup_history' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('backup_name');
                $table->string('backup_type')->nullable();
                $table->string('status');
                $table->dateTime('started_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->string('destination')->nullable();
                $table->unsignedBigInteger('backup_size')->nullable();
                $table->unsignedBigInteger('file_count')->nullable();
                $table->string('checksum')->nullable();
                $table->string('checksum_algorithm')->nullable();
                $table->dateTime('verified_at')->nullable();
                $table->string('storage_path')->nullable();
                $table->unsignedBigInteger('parent_backup_id')->nullable();
                $table->string('base_backup_name')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();
            },
            'audit_logs' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('action')->nullable();
                $table->string('module')->nullable();
                $table->string('actor_type')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->text('description')->nullable();
                $table->timestamp('created_at')->nullable();
            },
        ] as $name => $definition) {
            if (! Schema::hasTable($name)) {
                Schema::create($name, $definition);
            }
        }
    }
}
