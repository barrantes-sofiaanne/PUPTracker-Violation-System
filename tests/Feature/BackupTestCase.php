<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Shared setup for backup feature tests.
 *
 * Tests run against the phpunit in-memory SQLite database. Following the
 * existing convention in this repo, tables are created manually (no
 * RefreshDatabase) and cleared between tests.
 */
abstract class BackupTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createBackupTables();

        // Force backups to a private local disk for tests (never R2/network).
        config()->set('backup.disk', 'backups');
        config()->set('filesystems.backup_disk', 'backups');
    }

    protected function tearDown(): void
    {
        foreach (['backup_history', 'audit_logs', 'admins', 'admin_info_tbl'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        parent::tearDown();
    }

    protected function createBackupTables(): void
    {
        if (! Schema::hasTable('backup_history')) {
            Schema::create('backup_history', function (Blueprint $table): void {
                $table->id();
                $table->string('backup_name')->index();
                $table->string('backup_type')->nullable()->index();
                $table->unsignedBigInteger('parent_backup_id')->nullable()->index();
                $table->string('status')->default('success');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->string('destination')->nullable();
                $table->unsignedBigInteger('backup_size')->nullable();
                $table->unsignedInteger('file_count')->nullable();
                $table->string('checksum', 64)->nullable();
                $table->string('checksum_algorithm', 32)->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->string('storage_path', 500)->nullable();
                $table->string('binlog_file_start', 255)->nullable();
                $table->unsignedBigInteger('binlog_position_start')->nullable();
                $table->string('binlog_file_end', 255)->nullable();
                $table->unsignedBigInteger('binlog_position_end')->nullable();
                $table->timestamp('binlog_timestamp_start')->nullable();
                $table->timestamp('binlog_timestamp_end')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $table): void {
                $table->id();
                $table->string('username')->nullable();
                $table->string('email')->unique();
                $table->string('password');
                $table->text('mfa_totp_secret')->nullable();
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
    }

    protected function createAdminWithPosition(string $position): Admin
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
