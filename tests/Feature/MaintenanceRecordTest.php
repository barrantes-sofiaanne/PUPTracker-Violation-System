<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\MaintenanceRecord;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MaintenanceRecordTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTablesForTests();
    }

    public function test_maintenance_history_requires_super_admin(): void
    {
        // Non-super admin (regular admin) is forbidden.
        $admin = $this->createAdminWithPosition('Guidance Administrator');
        $this->actingAs($admin, 'admin');

        $this->get(route('admin.super-admin.maintenance-records.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_access_history_page(): void
    {
        $admin = $this->createAdminWithPosition('IT Administrator');
        $this->actingAs($admin, 'admin');

        $this->get(route('admin.super-admin.maintenance-records.index'))
            ->assertOk()
            ->assertSee('Maintenance History');
    }

    public function test_super_admin_can_create_maintenance_record_and_audit_log(): void
    {
        $admin = $this->createAdminWithPosition('IT Administrator');
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.super-admin.maintenance-records.store'), [
            'maintenance_date' => now()->toDateString(),
            'maintenance_type' => 'Dependency Update',
            'description' => 'Quarterly composer dependency check.',
            'overall_status' => 'Completed',
            'dependency_status' => 'Up to date',
            'php_runtime_status' => 'PHP 8.2 OK',
            'server_status' => 'OS patched',
            'testing_status' => 'All tests passed',
            'checklist' => [
                'composer_dependencies' => '1',
                'laravel_framework' => '1',
                'php_version' => '1',
                'application_tests' => '1',
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('maintenance_records', [
            'maintenance_type' => 'Dependency Update',
            'overall_status' => 'Completed',
            'performed_by_admin_id' => $admin->id,
        ]);

        // Audit log was created for the maintenance record.
        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
            'action' => 'Create',
            'module' => 'Maintenance Records',
        ]);
    }

    public function test_super_admin_can_view_individual_record(): void
    {
        $admin = $this->createAdminWithPosition('IT Administrator');
        $record = $this->createRecord($admin->id);

        $this->actingAs($admin, 'admin');

        $this->get(route('admin.super-admin.maintenance-records.show', $record->maintenance_id))
            ->assertOk()
            ->assertSee('Maintenance Record')
            ->assertSee('Security Review');
    }

    public function test_super_admin_can_update_record_and_audit_log(): void
    {
        $admin = $this->createAdminWithPosition('IT Administrator');
        $record = $this->createRecord($admin->id);

        $this->actingAs($admin, 'admin');

        $this->put(route('admin.super-admin.maintenance-records.update', $record->maintenance_id), [
            'maintenance_date' => now()->toDateString(),
            'maintenance_type' => 'Framework Security Patch',
            'description' => 'Updated description.',
            'overall_status' => 'Completed with Issues',
            'checklist' => [],
        ])->assertRedirect();

        $this->assertDatabaseHas('maintenance_records', [
            'maintenance_id' => $record->maintenance_id,
            'maintenance_type' => 'Framework Security Patch',
            'overall_status' => 'Completed with Issues',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
            'action' => 'Update',
            'module' => 'Maintenance Records',
        ]);
    }

    public function test_super_admin_can_delete_record_and_audit_log(): void
    {
        $admin = $this->createAdminWithPosition('IT Administrator');
        $record = $this->createRecord($admin->id);

        $this->actingAs($admin, 'admin');

        $this->delete(route('admin.super-admin.maintenance-records.destroy', $record->maintenance_id))
            ->assertRedirect();

        $this->assertDatabaseMissing('maintenance_records', [
            'maintenance_id' => $record->maintenance_id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
            'action' => 'Delete',
            'module' => 'Maintenance Records',
        ]);
    }

    public function test_no_credentials_exposed_in_views(): void
    {
        $admin = $this->createAdminWithPosition('IT Administrator');
        $record = $this->createRecord($admin->id);

        $this->actingAs($admin, 'admin');

        $content = $this->get(route('admin.super-admin.maintenance-records.show', $record->maintenance_id))->getContent();

        $this->assertStringNotContainsString('password', $content);
        $this->assertStringNotContainsString('api_key', $content);
    }

    private function createRecord(int $adminId): MaintenanceRecord
    {
        return MaintenanceRecord::create([
            'maintenance_date' => now()->toDateString(),
            'maintenance_type' => 'Security Review',
            'description' => 'Quarterly security review.',
            'performed_by_admin_id' => $adminId,
            'overall_status' => 'Completed',
            'checklist_json' => [],
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
                $table->text('mfa_totp_secret')->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
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

        if (! Schema::hasTable('maintenance_records')) {
            Schema::create('maintenance_records', function (Blueprint $table): void {
                $table->id('maintenance_id');
                $table->date('maintenance_date');
                $table->string('maintenance_type');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('performed_by_admin_id')->nullable();
                $table->string('dependency_status')->nullable();
                $table->string('php_runtime_status')->nullable();
                $table->string('server_status')->nullable();
                $table->string('testing_status')->nullable();
                $table->string('overall_status')->default('Completed');
                $table->text('notes')->nullable();
                $table->text('updates_identified')->nullable();
                $table->text('updates_applied')->nullable();
                $table->text('tests_performed')->nullable();
                $table->text('test_results')->nullable();
                $table->text('problems_found')->nullable();
                $table->text('corrective_actions')->nullable();
                $table->json('checklist_json')->nullable();
                $table->timestamps();
            });
        }
    }
}
