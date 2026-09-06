<?php

namespace Tests\Feature;

use App\Models\BackupHistory;
use App\Models\MaintenanceRecord;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Simulates a production DB that has NOT run the backup-policy migration
 * (2026_09_06_000001): the backup_history table only has the original columns
 * (no backup_type / checksum / verified_at / storage_path / binlog_*).
 *
 * The Super Admin dashboard + backup history pages must not crash and must
 * still surface the last successful backup.
 */
class BackupLegacySchemaTest extends BackupTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Drop the full-width table created by BackupTestCase and rebuild it
        // with ONLY the legacy (original) schema.
        Schema::dropIfExists('backup_history');

        Schema::create('backup_history', function (Blueprint $table): void {
            $table->id();
            $table->string('backup_name')->index();
            $table->string('status')->default('success');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('destination')->nullable();
            $table->unsignedBigInteger('backup_size')->nullable();
            $table->unsignedInteger('file_count')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        // BackupTestCase's tearDown deletes from backup_history, so ensure it
        // still exists then.
        if (! Schema::hasTable('maintenance_records')) {
            Schema::create('maintenance_records', function (Blueprint $table): void {
                $table->id('maintenance_id');
                $table->date('maintenance_date');
                $table->string('maintenance_type')->nullable();
                $table->text('description')->nullable();
                $table->unsignedBigInteger('performed_by_admin_id')->nullable();
                $table->string('overall_status')->nullable();
                $table->text('checklist_json')->nullable();
                $table->timestamps();
            });
        }
        MaintenanceRecord::create([
            'maintenance_date' => now()->toDateString(),
            'maintenance_type' => 'Security Review',
            'description' => 'Quarterly security review.',
            'performed_by_admin_id' => null,
            'overall_status' => 'Completed',
            'checklist_json' => [],
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function test_dashboard_does_not_crash_without_the_new_columns(): void
    {
        BackupHistory::create([
            'backup_name' => '2026-08-26-backup',
            'status' => 'success',
            'started_at' => now()->subHours(3),
            'completed_at' => now()->subHours(3),
            'destination' => 'Cloudflare R2',
        ]);

        $admin = $this->createAdminWithPosition('IT Administrator');
        $this->actingAs($admin, 'admin');

        $response = $this->get(route('admin.super-admin.dashboard'));
        $response->assertOk();

        $content = $response->getContent();
        $start = strpos($content, '<h5>Database Backup</h5>');
        $this->assertNotFalse($start);
        $end = strpos($content, '<h5>Authenticator MFA</h5>');
        $this->assertNotFalse($end);
        $widget = substr($content, $start, $end - $start);

        // The legacy successful backup's completion time must be surfaced as
        // the last successful backup (formatted like "Aug 26, 2026 03:00 AM"),
        // NOT "No record yet".
        $record = BackupHistory::first();
        $this->assertStringContainsString($record->completed_at->format('M d, Y h:i A'), $widget);
        $this->assertStringNotContainsString('No record yet', $widget);
    }

    public function test_history_list_does_not_crash_without_the_new_columns(): void
    {
        BackupHistory::create([
            'backup_name' => '2026-08-25-backup',
            'status' => 'success',
            'started_at' => now()->subDay(),
            'completed_at' => now()->subDay(),
            'destination' => 'Cloudflare R2',
        ]);

        $admin = $this->createAdminWithPosition('IT Administrator');
        $this->actingAs($admin, 'admin');

        $this->get(route('admin.super-admin.backups.index'))
            ->assertOk()
            ->assertSee('2026-08-25-backup')
            ->assertSee('Success');
    }
}
