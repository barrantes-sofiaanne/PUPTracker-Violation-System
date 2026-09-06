<?php

namespace Tests\Feature;

use App\Models\BackupHistory;
use App\Models\MaintenanceRecord;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reproduces the production report: "the backup page cannot read the last
 * successful backup". Seeds realistic legacy + recent rows and checks the
 * Super Admin dashboard and backup-history list render the last SUCCESSFUL
 * backup correctly.
 */
class BackupPageRegressionTest extends BackupTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The Super Admin dashboard also queries audit_logs + maintenance_records.
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

    public function test_dashboard_reads_the_last_successful_backup(): void
    {
        // A legacy MyDumper row: status success, no backup_type (pre-policy).
        BackupHistory::create([
            'backup_name' => '2026-08-26-backup',
            'backup_type' => null,
            'status' => 'success',
            'started_at' => now()->subDays(2),
            'completed_at' => now()->subDays(2)->subMinutes(1),
            'destination' => 'Cloudflare R2',
        ]);

        // The most recent SUCCESSFUL row (the one that must surface).
        $recent = BackupHistory::create([
            'backup_name' => 'full_2026-09-05_020000',
            'backup_type' => 'full',
            'status' => 'success',
            'started_at' => now()->subHours(2),
            'completed_at' => now()->subHours(2),
            'destination' => 'Cloudflare R2',
            'checksum' => str_repeat('a', 64),
            'checksum_algorithm' => 'SHA-256',
            'verified_at' => now()->subHours(2),
        ]);

        // A FAILED attempt after it must NOT hide the successful one.
        BackupHistory::create([
            'backup_name' => 'full_2026-09-07_020000',
            'backup_type' => 'full',
            'status' => 'failed',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now()->subMinutes(5),
            'destination' => 'Cloudflare R2',
            'error_message' => 'upload failed',
        ]);

        $admin = $this->createAdminWithPosition('IT Administrator');
        $this->actingAs($admin, 'admin');

        $response = $this->get(route('admin.super-admin.dashboard'));
        $response->assertOk();

        $content = $response->getContent();

        // Isolate the Database Backup widget block for precise assertions.
        $start = strpos($content, '<h5>Database Backup</h5>');
        $end = strpos($content, '<h5>Authenticator MFA</h5>');
        $this->assertNotFalse($start, 'Database Backup widget not found');
        $widget = $end === false ? substr($content, $start) : substr($content, $start, $end - $start);

        // The widget must display the last SUCCESSFUL backup's completion time.
        $this->assertStringContainsString(
            $recent->completed_at->format('M d, Y h:i A'),
            $widget,
            'Backup widget should show the last successful backup completion time.'
        );

        // The widget must NOT claim "No record yet".
        $this->assertStringNotContainsString('No record yet', $widget);

        // The stale legacy backup (2 days ago) must not be shown as the latest.
        $old = BackupHistory::where('backup_name', '2026-08-26-backup')->first();
        $this->assertStringNotContainsString(
            $old->completed_at->format('M d, Y h:i A'),
            $widget,
            'The stale legacy backup must not be presented as the latest successful backup.'
        );
    }

    public function test_backup_history_list_shows_the_last_successful_backup_first_among_successes(): void
    {
        BackupHistory::create([
            'backup_name' => 'old_success',
            'backup_type' => null,
            'status' => 'success',
            'started_at' => now()->subDays(5),
            'completed_at' => now()->subDays(5),
        ]);

        $recent = BackupHistory::create([
            'backup_name' => 'recent_success',
            'backup_type' => 'daily_snapshot',
            'status' => 'success',
            'started_at' => now()->subHours(1),
            'completed_at' => now()->subHours(1),
        ]);

        BackupHistory::create([
            'backup_name' => 'failed_newest',
            'backup_type' => 'full',
            'status' => 'failed',
            'started_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(10),
            'error_message' => 'boom',
        ]);

        $admin = $this->createAdminWithPosition('IT Administrator');
        $this->actingAs($admin, 'admin');

        $response = $this->get(route('admin.super-admin.backups.index'));
        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringContainsString('recent_success', $content);
        $this->assertStringContainsString('old_success', $content);
        $this->assertStringContainsString('failed_newest', $content);

        // The successful rows must carry the success badge.
        $this->assertStringContainsString('Success', $content);
    }
}
