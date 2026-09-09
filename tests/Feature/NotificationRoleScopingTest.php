<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\Notification;
use App\Models\Security;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Strict role-based notification scoping.
 *
 * When Security officer A records a violation for Student 1, every related
 * notification must be visible ONLY to:
 *   - Security officer A (the recorder)
 *   - Student 1
 *   - Administrators
 * and NOT to Security officer B or any other account.
 */
class NotificationRoleScopingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
    }

    public function test_security_notification_is_targeted_to_the_recording_officer_only(): void
    {
        $admin = $this->createAdmin('admin@example.com');
        $officerA = $this->createSecurity('officer-a@example.com', 'Officer', 'Alpha');
        $officerB = $this->createSecurity('officer-b@example.com', 'Officer', 'Bravo');
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');

        // Officer A records a violation for Student 1.
        $this->actingAs($officerA, 'security')
            ->postJson(route('security.violations.store'), [
                'student_number' => '2024-0001',
                'violation_type_id' => 1,
                'violation_date' => now()->format('Y-m-d H:i:s'),
                'description' => 'Caught using a phone.',
            ])
            ->assertOk();

        $violation = Violation::where('student_number', '2024-0001')->firstOrFail();

        // The violation persists the recording officer id.
        $this->assertSame($officerA->id, (int) $violation->recorded_by_security_id);

        // Admin confirms the violation -> a security notification is created
        // targeted to officer A, and the student is notified.
        $this->actingAs($admin, 'admin')
            ->post(route('admin.violations.confirm', $violation->violation_id))
            ->assertSessionHas('success');

        $securityNotification = Notification::where('recipient_type', 'security')
            ->where('notification_type', 'violation')
            ->first();

        $this->assertNotNull($securityNotification, 'A security notification should exist.');
        $this->assertSame($officerA->id, (int) $securityNotification->recipient_id, 'Notification must target the recording officer A.');

        // Student got their own notification.
        $this->assertDatabaseHas('notifications_tbl', [
            'student_number' => '2024-0001',
            'recipient_type' => 'student',
            'notification_type' => 'violation',
        ]);

        // Admin got an audit-visible notification (broadcast to all admins).
        $this->assertDatabaseHas('admin_notifications_tbl', [
            'target_type' => 'violation',
            'target_id' => $violation->violation_id,
        ]);
    }

    public function test_other_security_officer_cannot_see_recording_officers_notification(): void
    {
        $admin = $this->createAdmin('admin@example.com');
        $officerA = $this->createSecurity('officer-a@example.com', 'Officer', 'Alpha');
        $officerB = $this->createSecurity('officer-b@example.com', 'Officer', 'Bravo');
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');

        // Officer A records; admin confirms -> targeted notification to A.
        $this->actingAs($officerA, 'security')
            ->postJson(route('security.violations.store'), [
                'student_number' => '2024-0001',
                'violation_type_id' => 1,
                'violation_date' => now()->format('Y-m-d H:i:s'),
                'description' => 'Caught using a phone.',
            ])->assertOk();

        $violation = Violation::where('student_number', '2024-0001')->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.violations.confirm', $violation->violation_id));

        $notification = Notification::where('recipient_type', 'security')
            ->where('notification_type', 'violation')
            ->firstOrFail();

        // Officer A (the recorder) can see it.
        $this->actingAs($officerA, 'security')
            ->get(route('security.notifications'))
            ->assertOk()
            ->assertSee($notification->message, false);

        // Officer B cannot see it in the list...
        $this->actingAs($officerB, 'security')
            ->get(route('security.notifications'))
            ->assertOk()
            ->assertDontSee($notification->message, false);

        // ...cannot open it...
        $this->actingAs($officerB, 'security')
            ->get(route('security.notifications.open', $notification))
            ->assertForbidden();

        // ...and cannot mark it read.
        $this->actingAs($officerB, 'security')
            ->post(route('security.notifications.read', $notification))
            ->assertForbidden();
    }

    public function test_unread_count_is_scoped_to_the_recording_officer(): void
    {
        $admin = $this->createAdmin('admin@example.com');
        $officerA = $this->createSecurity('officer-a@example.com', 'Officer', 'Alpha');
        $officerB = $this->createSecurity('officer-b@example.com', 'Officer', 'Bravo');
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');

        $this->actingAs($officerA, 'security')
            ->postJson(route('security.violations.store'), [
                'student_number' => '2024-0001',
                'violation_type_id' => 1,
                'violation_date' => now()->format('Y-m-d H:i:s'),
                'description' => 'Caught using a phone.',
            ])->assertOk();

        $violation = Violation::where('student_number', '2024-0001')->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.violations.confirm', $violation->violation_id));

        // Officer A has 1 unread.
        $this->actingAs($officerA, 'security')
            ->getJson(route('security.notifications.unread-count'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        // Officer B has 0 unread.
        $this->actingAs($officerB, 'security')
            ->getJson(route('security.notifications.unread-count'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    public function test_student_only_sees_their_own_notifications(): void
    {
        $admin = $this->createAdmin('admin@example.com');
        $officerA = $this->createSecurity('officer-a@example.com', 'Officer', 'Alpha');
        $this->createStudent('2024-0001');
        $this->createStudent('2024-0002');
        $this->createViolationType(1, 'Cheating');

        // Officer A records for Student 1.
        $this->actingAs($officerA, 'security')
            ->postJson(route('security.violations.store'), [
                'student_number' => '2024-0001',
                'violation_type_id' => 1,
                'violation_date' => now()->format('Y-m-d H:i:s'),
                'description' => 'Caught using a phone.',
            ])->assertOk();

        $violation = Violation::where('student_number', '2024-0001')->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.violations.confirm', $violation->violation_id));

        $studentNotification = Notification::where('recipient_type', 'student')
            ->where('notification_type', 'violation')
            ->firstOrFail();

        // Student 1 sees it; Student 2 does not.
        $student1 = User::where('student_number', '2024-0001')->first();
        $student2 = User::where('student_number', '2024-0002')->first();

        $this->actingAs($student1, 'student')
            ->get(route('student.notifications'))
            ->assertOk()
            ->assertSee($studentNotification->message, false);

        $this->actingAs($student2, 'student')
            ->get(route('student.notifications'))
            ->assertOk()
            ->assertDontSee($studentNotification->message, false);
    }

    public function test_normal_admin_sees_the_admin_broadcast_notification(): void
    {
        $admin = $this->createAdmin('admin@example.com');
        $officerA = $this->createSecurity('officer-a@example.com', 'Officer', 'Alpha');
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');

        $this->actingAs($officerA, 'security')
            ->postJson(route('security.violations.store'), [
                'student_number' => '2024-0001',
                'violation_type_id' => 1,
                'violation_date' => now()->format('Y-m-d H:i:s'),
                'description' => 'Caught using a phone.',
            ])->assertOk();

        $this->assertDatabaseHas('admin_notifications_tbl', [
            'notification_type' => 'violation_confirmation',
        ]);

        $adminNotification = AdminNotification::where('notification_type', 'violation_confirmation')->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.notifications'))
            ->assertOk()
            ->assertSee($adminNotification->message, false);
    }

    // ------------------------------------------------------------------
    // Helpers (mirror ViolationSanctionWorkflowTest conventions)
    // ------------------------------------------------------------------

    private function createTables(): void
    {
        $tables = [
            'admins' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('username')->nullable();
                $table->string('email')->unique();
                $table->string('password');
                $table->string('remember_token', 100)->nullable();
                $table->text('mfa_totp_secret')->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
            },
            'admin_info_tbl' => function (Blueprint $table): void {
                $table->unsignedBigInteger('admin_id')->primary();
                $table->string('position')->nullable();
            },
            'security' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('email')->unique();
                $table->string('password');
                $table->string('remember_token', 100)->nullable();
                $table->text('mfa_totp_secret')->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
            },
            'security_info_tbl' => function (Blueprint $table): void {
                $table->unsignedBigInteger('security_id')->primary();
                $table->string('firstname')->nullable();
                $table->string('middlename')->nullable();
                $table->string('lastname')->nullable();
            },
            'security_info' => function (Blueprint $table): void {
                $table->unsignedBigInteger('security_id')->primary();
                $table->string('firstname')->nullable();
                $table->string('middlename')->nullable();
                $table->string('lastname')->nullable();
            },
            'roles_tbl' => function (Blueprint $table): void {
                $table->increments('roles_id');
                $table->string('roles_name');
            },
            'status_tbl' => function (Blueprint $table): void {
                $table->increments('status_id');
                $table->string('status_name');
            },
            'users_tbl' => function (Blueprint $table): void {
                $table->increments('user_id');
                $table->string('student_number')->unique();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->unsignedBigInteger('gender_id')->nullable();
                $table->unsignedBigInteger('status_id')->nullable();
                $table->unsignedBigInteger('roles_id')->nullable();
                $table->string('password_hash')->nullable();
                $table->rememberToken();
                $table->text('mfa_totp_secret')->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
                $table->timestamps();
            },
            'violation_type_tbl' => function (Blueprint $table): void {
                $table->increments('violation_type_id');
                $table->string('violation_type');
                $table->unsignedBigInteger('violation_category_id')->nullable();
            },
            'violation_tbl' => function (Blueprint $table): void {
                $table->increments('violation_id');
                $table->string('student_number')->nullable();
                $table->string('violation_type')->nullable();
                $table->dateTime('violation_date')->nullable();
                $table->text('description')->nullable();
                $table->unsignedBigInteger('recorder_id')->nullable();
                $table->string('recorder_type', 20)->nullable();
                $table->string('recorder_name', 150)->nullable();
                $table->unsignedBigInteger('recorded_by_security_id')->nullable();
                $table->string('status', 20)->nullable();
                $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
                $table->dateTime('reviewed_at')->nullable();
                $table->text('review_remarks')->nullable();
            },
            'violation_category_tbl' => function (Blueprint $table): void {
                $table->increments('violation_category_id');
                $table->string('category_name');
            },
            'notifications_tbl' => function (Blueprint $table): void {
                $table->increments('notification_id');
                $table->string('student_number')->nullable();
                $table->text('message');
                $table->string('title')->nullable();
                $table->string('notification_type')->nullable();
                $table->string('recipient_type')->nullable();
                $table->unsignedBigInteger('recipient_id')->nullable();
                $table->string('target_type')->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->string('link')->nullable();
                $table->boolean('is_read')->default(false);
                $table->dateTime('created_at')->nullable();
            },
            'admin_notifications_tbl' => function (Blueprint $table): void {
                $table->increments('admin_notification_id');
                $table->text('message');
                $table->string('title')->nullable();
                $table->string('notification_type')->nullable();
                $table->string('target_type')->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->string('recipient_type')->nullable();
                $table->unsignedBigInteger('recipient_id')->nullable();
                $table->boolean('is_read')->default(false);
                $table->dateTime('created_at')->nullable();
            },
            'audit_logs' => function (Blueprint $table): void {
                $table->increments('audit_log_id');
                $table->string('actor_type')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('action');
                $table->string('module')->nullable();
                $table->text('description')->nullable();
                $table->timestamp('created_at')->nullable();
            },
            'violation_evidence_tbl' => function (Blueprint $table): void {
                $table->increments('evidence_id');
                $table->string('evidence_code', 32)->nullable();
                $table->string('capture_session_id', 64)->nullable();
                $table->unsignedBigInteger('violation_id')->nullable();
                $table->string('disk_path', 500)->nullable();
                $table->string('original_name', 255)->nullable();
                $table->string('mime_type', 120)->nullable();
                $table->string('capture_method', 20)->nullable()->default('upload');
                $table->boolean('watermarked')->default(false);
                $table->boolean('server_watermarked')->default(false);
                $table->timestamp('captured_at')->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->char('sha256_hash', 64)->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->string('uploader_type', 20)->nullable();
                $table->unsignedBigInteger('uploader_id')->nullable();
                $table->timestamp('created_at')->nullable();
            },
            'evidence_security_events_tbl' => function (Blueprint $table): void {
                $table->increments('event_id');
                $table->string('capture_session_id', 64)->nullable();
                $table->unsignedBigInteger('violation_id')->nullable();
                $table->unsignedBigInteger('evidence_id')->nullable();
                $table->string('event_type', 80);
                $table->string('severity', 20)->default('info');
                $table->string('actor_type', 20)->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->text('meta')->nullable();
                $table->timestamp('created_at')->nullable();
            },
        ];

        foreach ($tables as $tableName => $closure) {
            if (! Schema::hasTable($tableName)) {
                Schema::create($tableName, $closure);
            }
        }

        if (DB::table('roles_tbl')->count() === 0) {
            DB::table('roles_tbl')->insert([
                ['roles_id' => 1, 'roles_name' => 'Admin'],
                ['roles_id' => 2, 'roles_name' => 'Student'],
                ['roles_id' => 3, 'roles_name' => 'Security'],
            ]);
        }

        if (DB::table('status_tbl')->count() === 0) {
            DB::table('status_tbl')->insert([
                ['status_id' => 1, 'status_name' => 'Active'],
            ]);
        }

        if (DB::table('violation_category_tbl')->count() === 0) {
            DB::table('violation_category_tbl')->insert([
                ['violation_category_id' => 1, 'category_name' => 'Major'],
            ]);
        }
    }

    private function createAdmin(string $email): Admin
    {
        return Admin::create([
            'username' => $email,
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
    }

    private function createSecurity(string $email, string $first, string $last): Security
    {
        $security = Security::create([
            'email' => $email,
            'password' => Hash::make('password'),
        ]);

        DB::table('security_info_tbl')->insert([
            'security_id' => $security->id,
            'firstname' => $first,
            'middlename' => '',
            'lastname' => $last,
        ]);

        DB::table('security_info')->insert([
            'security_id' => $security->id,
            'firstname' => $first,
            'middlename' => '',
            'lastname' => $last,
        ]);

        return $security;
    }

    private function createStudent(string $studentNumber): User
    {
        return User::create([
            'student_number' => $studentNumber,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => $studentNumber . '@example.com',
            'password_hash' => Hash::make('Password123!'),
            'roles_id' => 2,
            'status_id' => 1,
        ]);
    }

    private function createViolationType(int $id, string $name): ViolationType
    {
        return ViolationType::create([
            'violation_type_id' => $id,
            'violation_type' => $name,
            'violation_category_id' => 1,
        ]);
    }
}
