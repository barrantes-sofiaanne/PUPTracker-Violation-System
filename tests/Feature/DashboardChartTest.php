<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Security;
use App\Models\Violation;
use App\Models\ViolationType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Filterable violation charts on the Security and Admin dashboards.
 *
 * Security chart must be scoped to the logged-in security officer and filter
 * by period + status. Admin chart shows all violations and filters likewise.
 */
class DashboardChartTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
    }

    public function test_security_chart_is_scoped_to_the_logged_in_officer(): void
    {
        $officerA = $this->createSecurity('officer-a@example.com');
        $officerB = $this->createSecurity('officer-b@example.com');

        // Officer A records 2 violations this week (one confirmed, one pending).
        Violation::create([
            'student_number' => '2024-0001',
            'violation_type' => 1,
            'violation_date' => now()->subDay()->format('Y-m-d H:i:s'),
            'description' => 'A1',
            'recorder_type' => 'security',
            'recorder_name' => 'Security: officer-a@example.com',
            'status' => Violation::STATUS_CONFIRMED,
        ]);
        Violation::create([
            'student_number' => '2024-0002',
            'violation_type' => 1,
            'violation_date' => now()->format('Y-m-d H:i:s'),
            'description' => 'A2',
            'recorder_type' => 'security',
            'recorder_name' => 'Security: officer-a@example.com',
            'status' => Violation::STATUS_PENDING,
        ]);

        // Officer B records 1 violation this week.
        Violation::create([
            'student_number' => '2024-0003',
            'violation_type' => 1,
            'violation_date' => now()->format('Y-m-d H:i:s'),
            'description' => 'B1',
            'recorder_type' => 'security',
            'recorder_name' => 'Security: officer-b@example.com',
            'status' => Violation::STATUS_CONFIRMED,
        ]);

        $this->actingAs($officerA, 'security');

        $response = $this->getJson(route('security.dashboard.chart-data').'?period=week');
        $response->assertOk();
        $payload = $response->json();

        $this->assertIsArray($payload['labels']);
        $this->assertIsArray($payload['values']);
        $this->assertCount(count($payload['labels']), $payload['values']);

        // Officer A should only count their own 2 violations.
        $this->assertSame(2, $payload['total']);
    }

    public function test_security_chart_filters_by_status(): void
    {
        $officerA = $this->createSecurity('officer-a@example.com');

        Violation::create([
            'student_number' => '2024-0001',
            'violation_type' => 1,
            'violation_date' => now()->format('Y-m-d H:i:s'),
            'description' => 'confirmed',
            'recorder_type' => 'security',
            'recorder_name' => 'Security: officer-a@example.com',
            'status' => Violation::STATUS_CONFIRMED,
        ]);
        Violation::create([
            'student_number' => '2024-0002',
            'violation_type' => 1,
            'violation_date' => now()->format('Y-m-d H:i:s'),
            'description' => 'pending',
            'recorder_type' => 'security',
            'recorder_name' => 'Security: officer-a@example.com',
            'status' => Violation::STATUS_PENDING,
        ]);
        Violation::create([
            'student_number' => '2024-0003',
            'violation_type' => 1,
            'violation_date' => now()->format('Y-m-d H:i:s'),
            'description' => 'denied',
            'recorder_type' => 'security',
            'recorder_name' => 'Security: officer-a@example.com',
            'status' => Violation::STATUS_DENIED,
        ]);

        $this->actingAs($officerA, 'security');

        $all = $this->getJson(route('security.dashboard.chart-data').'?period=week')->json();
        $this->assertSame(3, $all['total']);

        $confirmed = $this->getJson(route('security.dashboard.chart-data').'?period=week&status=confirmed')->json();
        $this->assertSame(1, $confirmed['total']);

        $pending = $this->getJson(route('security.dashboard.chart-data').'?period=week&status=pending')->json();
        $this->assertSame(1, $pending['total']);
    }

    public function test_admin_chart_is_filterable_by_period_and_status(): void
    {
        $admin = $this->createAdmin('admin@example.com');

        Violation::create([
            'student_number' => '2024-0001',
            'violation_type' => 1,
            'violation_date' => now()->format('Y-m-d H:i:s'),
            'description' => 'confirmed',
            'recorder_type' => 'admin',
            'recorder_name' => 'Admin',
            'status' => Violation::STATUS_CONFIRMED,
        ]);
        Violation::create([
            'student_number' => '2024-0002',
            'violation_type' => 1,
            'violation_date' => now()->format('Y-m-d H:i:s'),
            'description' => 'pending',
            'recorder_type' => 'admin',
            'recorder_name' => 'Admin',
            'status' => Violation::STATUS_PENDING,
        ]);

        $this->actingAs($admin, 'admin');

        // Admin chart is based on ALL violations (not just effective), so both
        // confirmed and pending appear in "all statuses".
        $all = $this->getJson(route('admin.dashboard.chart-data').'?period=week')->json();
        $this->assertSame(2, $all['total']);

        $confirmed = $this->getJson(route('admin.dashboard.chart-data').'?period=week&status=confirmed')->json();
        $this->assertSame(1, $confirmed['total']);

        $response = $this->getJson(route('admin.dashboard.chart-data').'?period=month');
        $response->assertOk();
        $this->assertCount(count($response->json('labels')), $response->json('values'));
    }

    public function test_security_announcements_page_is_accessible_to_security(): void
    {
        $security = $this->createSecurity('officer-a@example.com');

        if (! Schema::hasTable('announcements_tbl')) {
            Schema::create('announcements_tbl', function (Blueprint $table): void {
                $table->increments('announcement_id');
                $table->string('title');
                $table->longText('content')->nullable();
                $table->string('attachment_path')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('announcement_attachments')) {
            Schema::create('announcement_attachments', function (Blueprint $table): void {
                $table->increments('attachment_id');
                $table->unsignedBigInteger('announcement_id')->nullable();
                $table->string('original_filename')->nullable();
                $table->string('stored_filename')->nullable();
                $table->string('storage_disk')->nullable();
                $table->string('storage_path')->nullable();
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->string('file_hash')->nullable();
                $table->timestamps();
            });
        }

        $this->actingAs($security, 'security')
            ->get(route('security.announcements'))
            ->assertOk();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createTables(): void
    {
        if (! Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('username')->nullable();
                $table->string('email')->unique();
                $table->string('password');
                $table->boolean('mfa_totp_enabled')->default(false);
            });
        }

        if (! Schema::hasTable('security')) {
            Schema::create('security', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('email')->unique();
                $table->string('password');
                $table->boolean('mfa_totp_enabled')->default(false);
            });
        }

        if (! Schema::hasTable('violation_tbl')) {
            Schema::create('violation_tbl', function (Blueprint $table): void {
                $table->increments('violation_id');
                $table->string('student_number');
                $table->string('violation_type')->nullable();
                $table->dateTime('violation_date')->nullable();
                $table->text('description')->nullable();
                $table->unsignedInteger('recorder_id')->nullable();
                $table->string('recorder_type')->nullable();
                $table->string('recorder_name')->nullable();
                $table->string('status')->nullable();
            });
        }

        if (! Schema::hasTable('violation_type_tbl')) {
            Schema::create('violation_type_tbl', function (Blueprint $table): void {
                $table->increments('violation_type_id');
                $table->string('violation_type');
            });
        }
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

    private function createSecurity(string $email): Security
    {
        return Security::create([
            'email' => $email,
            'password' => Hash::make('Password123!'),
            'mfa_totp_enabled' => 0,
        ]);
    }
}
