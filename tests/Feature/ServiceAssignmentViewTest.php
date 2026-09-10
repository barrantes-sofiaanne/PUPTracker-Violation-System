<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DisciplinarySanction;
use App\Models\SanctionRequest;
use App\Models\StudentSanctionRecord;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Renders the admin sanction views (which now include the conditional service
 * fields / edit modal) and verifies the new migration adds the columns.
 */
class ServiceAssignmentViewTest extends TestCase
{
    public function test_admin_sanction_request_view_renders_service_fields_for_community_service(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');
        $this->createConfirmedViolation('2024-0001', 1);

        $request = SanctionRequest::create([
            'student_number' => '2024-0001',
            'violation_type_id' => 1,
            'request_date' => now(),
            'is_active' => true,
            'status' => 'Pending',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.sanctions.requests.show', $request));

        $response->assertOk();
        $response->assertSee('designated_office');
        $response->assertSee('starting_date');
        $response->assertSee('Designated Office');
        $response->assertSee('Starting Date');
    }

    public function test_admin_sanction_request_view_hides_service_fields_for_other_types(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'One month suspension');
        $this->createConfirmedViolation('2024-0001', 1);

        $request = SanctionRequest::create([
            'student_number' => '2024-0001',
            'violation_type_id' => 1,
            'request_date' => now(),
            'is_active' => true,
            'status' => 'Pending',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.sanctions.requests.show', $request));

        $response->assertOk();
        $response->assertDontSee('name="designated_office"', false);
        $response->assertDontSee('name="starting_date"', false);
    }

    public function test_admin_sanctions_index_renders(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');

        $response = $this->actingAs($admin, 'admin')->get(route('admin.sanctions.index'));
        $response->assertOk();
    }

    public function test_migration_adds_service_assignment_columns(): void
    {
        // Run the real migration against the in-memory schema.
        Schema::create('student_sanction_records_tbl', function (Blueprint $table): void {
            $table->increments('record_id');
            $table->string('student_number')->nullable();
        });

        $migration = require base_path('database/migrations/2026_09_10_000001_add_service_assignment_to_student_sanction_records_tbl.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('student_sanction_records_tbl', 'designated_office'));
        $this->assertTrue(Schema::hasColumn('student_sanction_records_tbl', 'starting_date'));

        // Idempotent: running again must not error.
        $migration->up();
        $this->assertTrue(Schema::hasColumn('student_sanction_records_tbl', 'designated_office'));

        $migration->down();
        $this->assertFalse(Schema::hasColumn('student_sanction_records_tbl', 'designated_office'));
    }

    private function createConfirmedViolation(string $studentNumber, int $violationTypeId): void
    {
        Violation::create([
            'student_number' => $studentNumber,
            'violation_type' => $violationTypeId,
            'violation_date' => now(),
            'description' => 'Confirmed violation.',
            'recorder_type' => 'admin',
            'recorder_name' => 'Admin One',
            'status' => 'confirmed',
        ]);
    }

    private function createAdmin(string $email): Admin
    {
        return Admin::create([
            'username' => $email,
            'email' => $email,
            'password' => Hash::make('Password123!'),
            'mfa_totp_enabled' => 0,
        ]);
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

    private function createViolationType(int $id, string $type): void
    {
        ViolationType::create([
            'violation_type_id' => $id,
            'violation_type' => $type,
            'violation_category_id' => 1,
            'severity_level' => 1,
        ]);
    }

    private function createDisciplinarySanction(int $typeId, string $offense, string $sanction): void
    {
        DisciplinarySanction::create([
            'violation_type_id' => $typeId,
            'offense_level' => $offense,
            'disciplinary_sanction' => $sanction,
        ]);
    }

    private function createTables(): void
    {
        foreach ([
            'admins' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('username')->nullable();
                $table->string('email');
                $table->string('password');
                $table->boolean('mfa_totp_enabled')->default(false);
            },
            'users_tbl' => function (Blueprint $table): void {
                $table->increments('user_id');
                $table->string('student_number')->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->string('password_hash')->nullable();
                $table->unsignedInteger('roles_id')->nullable();
                $table->unsignedInteger('status_id')->nullable();
                $table->timestamps();
            },
            'roles_tbl' => function (Blueprint $table): void {
                $table->increments('roles_id');
                $table->string('roles_name');
            },
            'status_tbl' => function (Blueprint $table): void {
                $table->increments('status_id');
                $table->string('status_name');
            },
            'program_tbl' => function (Blueprint $table): void {
                $table->increments('program_id');
                $table->string('program_name')->nullable();
            },
            'year_tbl' => function (Blueprint $table): void {
                $table->increments('year_id');
                $table->string('year')->nullable();
            },
            'section_tbl' => function (Blueprint $table): void {
                $table->increments('section_id');
                $table->string('section_name')->nullable();
            },
            'student_info_tbl' => function (Blueprint $table): void {
                $table->unsignedBigInteger('user_id')->primary();
                $table->unsignedBigInteger('program_id')->nullable();
                $table->unsignedBigInteger('year_id')->nullable();
                $table->unsignedBigInteger('section_id')->nullable();
                $table->unsignedBigInteger('student_status_id')->nullable();
            },
            'violation_category_tbl' => function (Blueprint $table): void {
                $table->increments('violation_category_id');
                $table->string('category_name');
            },
            'violation_type_tbl' => function (Blueprint $table): void {
                $table->increments('violation_type_id');
                $table->string('violation_type');
                $table->unsignedBigInteger('violation_category_id')->nullable();
                $table->string('severity_level')->nullable();
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
                $table->string('status', 20)->nullable();
                $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
                $table->dateTime('reviewed_at')->nullable();
                $table->text('review_remarks')->nullable();
            },
            'disciplinary_sanctions' => function (Blueprint $table): void {
                $table->increments('disciplinary_sanction_id');
                $table->unsignedBigInteger('violation_type_id')->nullable();
                $table->string('offense_level')->nullable();
                $table->string('disciplinary_sanction')->nullable();
            },
            'sanction_requests_tbl' => function (Blueprint $table): void {
                $table->increments('request_id');
                $table->string('student_number');
                $table->unsignedInteger('violation_type_id');
                $table->dateTime('request_date')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('status')->nullable();
                $table->unsignedInteger('approved_by_admin_id')->nullable();
                $table->dateTime('approved_at')->nullable();
            },
            'student_sanction_records_tbl' => function (Blueprint $table): void {
                $table->increments('record_id');
                $table->string('student_number')->nullable();
                $table->unsignedBigInteger('violation_id')->nullable();
                $table->unsignedBigInteger('assigned_sanction_id')->nullable();
                $table->unsignedBigInteger('assigned_by_admin_id')->nullable();
                $table->string('status')->nullable();
                $table->date('deadline_date')->nullable();
                $table->string('designated_office', 255)->nullable();
                $table->date('starting_date')->nullable();
                $table->timestamp('date_assigned')->nullable();
                $table->timestamp('date_completed')->nullable();
                $table->string('proof_path', 500)->nullable();
                $table->string('proof_original_name', 255)->nullable();
                $table->timestamp('proof_submitted_at')->nullable();
                $table->text('proof_notes')->nullable();
                $table->unsignedBigInteger('completion_reviewed_by_admin_id')->nullable();
                $table->timestamp('completion_reviewed_at')->nullable();
                $table->text('completion_remarks')->nullable();
            },
            'sanction_proofs_tbl' => function (Blueprint $table): void {
                $table->increments('proof_id');
                $table->string('proof_code', 32)->nullable();
                $table->unsignedBigInteger('record_id')->nullable();
                $table->string('student_number', 50)->nullable();
                $table->string('disk_path', 500)->nullable();
                $table->string('original_name', 255)->nullable();
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->char('sha256_hash', 64)->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 20)->default('submitted');
                $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_remarks')->nullable();
                $table->timestamp('submitted_at')->nullable();
            },
            'notifications_tbl' => function (Blueprint $table): void {
                $table->increments('notification_id');
                $table->string('student_number')->nullable();
                $table->string('recipient_type', 20)->nullable();
                $table->unsignedBigInteger('recipient_id')->nullable();
                $table->string('notification_type', 50)->nullable();
                $table->string('title')->nullable();
                $table->text('message')->nullable();
                $table->string('target_type', 50)->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            },
            'admin_notifications_tbl' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('type', 50)->nullable();
                $table->string('title')->nullable();
                $table->text('message')->nullable();
                $table->string('target_type', 50)->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->unsignedBigInteger('recipient_id')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            },
            'audit_logs' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('actor_type', 20)->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('action', 100)->nullable();
                $table->string('module', 100)->nullable();
                $table->text('description')->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->timestamp('created_at')->nullable();
            },
        ] as $name => $definition) {
            if (Schema::hasTable($name)) {
                continue;
            }
            Schema::create($name, $definition);
        }
    }
}
