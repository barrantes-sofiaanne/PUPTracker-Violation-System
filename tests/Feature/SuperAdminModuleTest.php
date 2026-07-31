<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SuperAdminModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTablesForTests();
    }

    public function test_it_administrator_can_access_super_admin_pages(): void
    {
        $admin = $this->createAdminWithPosition('IT Administrator');

        $this->actingAs($admin, 'admin');

        $this->get(route('admin.super-admin.dashboard'))
            ->assertOk();

        $this->get(route('admin.super-admin.audit-trail'))
            ->assertOk();

        $this->get(route('admin.super-admin.maintenance'))
            ->assertOk();
    }

    public function test_non_it_administrator_is_forbidden_from_super_admin_pages(): void
    {
        $admin = $this->createAdminWithPosition('Guidance Administrator');

        $this->actingAs($admin, 'admin');

        $this->get(route('admin.super-admin.dashboard'))
            ->assertForbidden();
    }

    private function ensureTablesForTests(): void
    {
        if (!Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $table): void {
                $table->id();
                $table->string('username')->nullable();
                $table->string('email')->unique();
                $table->string('password')->nullable();
            });
        }

        if (!Schema::hasTable('admin_info_tbl')) {
            Schema::create('admin_info_tbl', function (Blueprint $table): void {
                $table->unsignedBigInteger('admin_id')->primary();
                $table->string('position')->nullable();
                $table->string('designation')->nullable();
                $table->string('role')->nullable();
                $table->string('title')->nullable();
            });
        }

        if (!Schema::hasTable('audit_logs')) {
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
}
