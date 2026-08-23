<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Violation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    public function test_dashboard_chart_data_returns_weekly_violation_trend(): void
    {
        Schema::create('admins', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('mfa_totp_enabled')->default(false);
        });

        Schema::create('violation_tbl', function (Blueprint $table): void {
            $table->increments('violation_id');
            $table->string('student_number');
            $table->string('violation_type')->nullable();
            $table->dateTime('violation_date')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('recorder_id')->nullable();
            $table->string('recorder_type')->nullable();
            $table->string('recorder_name')->nullable();
        });

        $admin = Admin::create([
            'username' => 'chart-admin@example.com',
            'email' => 'chart-admin@example.com',
            'password' => Hash::make('Password123!'),
            'mfa_totp_enabled' => 0,
        ]);

        $this->actingAs($admin, 'admin');

        $start = now()->startOfWeek()->subWeeks(2);

        Violation::create([
            'student_number' => '2024001',
            'violation_type' => 1,
            'violation_date' => $start->copy()->addDays(1),
            'description' => 'Late arrival',
            'recorder_id' => $admin->id,
            'recorder_type' => 'admin',
            'recorder_name' => 'Admin',
        ]);

        Violation::create([
            'student_number' => '2024002',
            'violation_type' => 2,
            'violation_date' => $start->copy()->addDays(3),
            'description' => 'Uniform violation',
            'recorder_id' => $admin->id,
            'recorder_type' => 'admin',
            'recorder_name' => 'Admin',
        ]);

        Violation::create([
            'student_number' => '2024003',
            'violation_type' => 1,
            'violation_date' => $start->copy()->addWeeks(1)->addDays(2),
            'description' => 'Disruption',
            'recorder_id' => $admin->id,
            'recorder_type' => 'admin',
            'recorder_name' => 'Admin',
        ]);

        $response = $this->get(route('admin.dashboard.chart-data'));

        $response->assertOk();
        $response->assertJsonStructure([
            'labels',
            'values',
        ]);
        $payload = $response->json();

        $this->assertIsArray($payload['labels']);
        $this->assertIsArray($payload['values']);
        $this->assertGreaterThan(0, count($payload['labels']));
        $this->assertCount(count($payload['labels']), $payload['values']);
    }
}
