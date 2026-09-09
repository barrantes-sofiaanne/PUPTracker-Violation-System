<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\EvidenceSecurityEvent;
use App\Models\UserManagementHistory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Logging / Audit architecture tests.
 *
 * Verifies the separation:
 *   audit_logs .................... WHO did WHAT (business/security accountability)
 *   user_management_histories ..... WHAT changed about a USER ACCOUNT
 *   evidence_security_events_tbl .. WHAT happened to EVIDENCE
 *
 * And role-based visibility:
 *   Super Admin (IT Administrator) = system-wide oversight.
 *   Normal Admin                  = operational visibility only (server-side).
 */
class LoggingArchitectureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTablesForTests();
    }

    // ------------------------------------------------------------------
    // Audit trail role scoping
    // ------------------------------------------------------------------

    public function test_super_admin_sees_full_audit_trail_including_privileged_modules(): void
    {
        $super = $this->createAdmin('IT Administrator');
        $this->createAuditLog('admin', $super->id, 'Backup Completed', 'Backup');
        $this->createAuditLog('admin', $super->id, 'Confirm Violation', 'Violations');

        $this->actingAs($super, 'admin')
            ->get(route('admin.audit-logs'))
            ->assertOk()
            ->assertSee('Backup Completed')
            ->assertSee('Confirm Violation');
    }

    public function test_normal_admin_cannot_see_privileged_modules_in_audit_trail(): void
    {
        $super = $this->createAdmin('IT Administrator');
        $normal = $this->createAdmin('Guidance Administrator');

        $this->createAuditLog('admin', $super->id, 'Backup Completed', 'Backup');
        $this->createAuditLog('admin', $normal->id, 'Confirm Violation', 'Violations');

        // Normal admin hits the audit-log endpoint directly (bypassing UI).
        $this->actingAs($normal, 'admin')
            ->get(route('admin.audit-logs'))
            ->assertOk()
            ->assertSee('Confirm Violation')
            ->assertDontSee('Backup Completed');
    }

    public function test_normal_admin_cannot_reach_super_admin_log_pages(): void
    {
        $normal = $this->createAdmin('Guidance Administrator');

        $this->actingAs($normal, 'admin')
            ->get(route('admin.super-admin.evidence-security-events'))
            ->assertForbidden();

        $this->actingAs($normal, 'admin')
            ->get(route('admin.super-admin.system-logs'))
            ->assertForbidden();
    }

    public function test_normal_admin_cannot_export_audit_trail(): void
    {
        $normal = $this->createAdmin('Guidance Administrator');

        $this->actingAs($normal, 'admin')
            ->get(route('admin.audit-logs.export'))
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // User management history role scoping
    // ------------------------------------------------------------------

    public function test_normal_admin_only_sees_student_security_history(): void
    {
        $normal = $this->createAdmin('Guidance Administrator');

        $this->createUserHistory(100, $normal->id, 'Create Student Account');
        $this->createUserHistory(1, $normal->id, 'Update Admin Account');

        $this->actingAs($normal, 'admin')
            ->get(route('admin.user-management-history'))
            ->assertOk()
            ->assertSee('Create Student Account')
            ->assertDontSee('Update Admin Account');
    }

    // ------------------------------------------------------------------
    // Evidence security events visibility (Super Admin only page)
    // ------------------------------------------------------------------

    public function test_super_admin_can_view_evidence_security_events(): void
    {
        $super = $this->createAdmin('IT Administrator');

        DB::table('violation_tbl')->insert([
            'violation_id' => 1,
            'student_number' => '2026-00001',
        ]);
        DB::table('violation_evidence_tbl')->insert([
            'evidence_id' => 1,
            'violation_id' => 1,
            'evidence_code' => 'EVD-2026-000001',
        ]);

        EvidenceSecurityEvent::create([
            'evidence_id' => 1,
            'violation_id' => 1,
            'event_type' => EvidenceSecurityEvent::EVIDENCE_VIEWED,
            'severity' => 'info',
            'actor_type' => 'admin',
            'actor_id' => $super->id,
            'created_at' => now(),
        ]);

        $this->actingAs($super, 'admin')
            ->get(route('admin.super-admin.evidence-security-events'))
            ->assertOk()
            ->assertSee('EVIDENCE_VIEWED');
    }

    // ------------------------------------------------------------------
    // AuditLogService helpers
    // ------------------------------------------------------------------

    public function test_audit_service_marks_privileged_modules(): void
    {
        $service = app(\App\Services\AuditLogService::class);

        $this->assertTrue($service->isPrivilegedModule('Backup'));
        $this->assertTrue($service->isPrivilegedModule('Maintenance Configuration'));
        $this->assertFalse($service->isPrivilegedModule('Violations'));
        $this->assertFalse($service->isPrivilegedModule(null));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createAdmin(string $position): Admin
    {
        $admin = Admin::create([
            'username' => strtolower(str_replace(' ', '.', $position)),
            'email' => strtolower(str_replace(' ', '.', $position)) . '@example.com',
            'password' => 'password',
            'mfa_totp_enabled' => false,
        ]);

        DB::table('admin_info_tbl')->insert([
            'admin_id' => $admin->id,
            'position' => $position,
        ]);

        return $admin;
    }

    private function createAuditLog(string $actorType, ?int $actorId, string $action, string $module): void
    {
        AuditLog::create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'module' => $module,
            'description' => $action . ' performed.',
            'created_at' => now(),
        ]);
    }

    private function createUserHistory(int $userId, int $performedBy, string $action): void
    {
        UserManagementHistory::create([
            'user_id' => $userId,
            'performed_by' => $performedBy,
            'action' => $action,
            'details' => $action . ' performed.',
            'changes' => [],
            'created_at' => now(),
        ]);
    }

    private function ensureTablesForTests(): void
    {
        if (! Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $table): void {
                $table->id();
                $table->string('username')->nullable();
                $table->string('email')->unique();
                $table->string('password')->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
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

        if (! Schema::hasTable('user_management_histories')) {
            Schema::create('user_management_histories', function (Blueprint $table): void {
                $table->id('history_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('performed_by')->nullable();
                $table->string('action');
                $table->text('details')->nullable();
                $table->json('changes')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('evidence_security_events_tbl')) {
            Schema::create('evidence_security_events_tbl', function (Blueprint $table): void {
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
            });
        }

        if (! Schema::hasTable('users_tbl')) {
            Schema::create('users_tbl', function (Blueprint $table): void {
                $table->id('user_id');
                $table->string('student_number')->nullable();
                $table->string('email')->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
            });
        }

        if (! Schema::hasTable('security')) {
            Schema::create('security', function (Blueprint $table): void {
                $table->id();
                $table->string('email')->nullable();
            });
        }

        if (! Schema::hasTable('violation_tbl')) {
            Schema::create('violation_tbl', function (Blueprint $table): void {
                $table->increments('violation_id');
                $table->string('student_number')->nullable();
            });
        }

        if (! Schema::hasTable('violation_evidence_tbl')) {
            Schema::create('violation_evidence_tbl', function (Blueprint $table): void {
                $table->increments('evidence_id');
                $table->unsignedBigInteger('violation_id')->nullable();
                $table->string('evidence_code', 32)->nullable();
            });
        }
    }
}
