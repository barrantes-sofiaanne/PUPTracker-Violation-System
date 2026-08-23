<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\SanctionRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminNotificationWorkflowTest extends TestCase
{
    public function test_authorized_admin_opens_exact_sanction_request_and_notification_becomes_read(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin-one@example.com');
        $request = SanctionRequest::create([
            'student_number' => '2024001',
            'violation_type_id' => 1,
            'request_date' => now(),
            'is_active' => true,
            'status' => 'Pending',
        ]);
        $notification = AdminNotification::create([
            'title' => 'Disciplinary Sanction Request',
            'message' => 'A student requested a disciplinary sanction.',
            'notification_type' => 'disciplinary_sanction_request',
            'target_type' => 'sanction_request',
            'target_id' => $request->request_id,
            'recipient_type' => 'admin',
            'recipient_id' => $admin->id,
            'is_read' => false,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.notifications.open', $notification));

        $response->assertRedirect(route('admin.sanctions.requests.show', $request));
        $this->assertDatabaseHas('admin_notifications_tbl', [
            'admin_notification_id' => $notification->admin_notification_id,
            'is_read' => true,
        ]);
    }

    public function test_admin_cannot_open_notification_assigned_to_another_admin(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin-one@example.com');
        $otherAdmin = $this->createAdmin('admin-two@example.com');
        $request = SanctionRequest::create([
            'student_number' => '2024001',
            'violation_type_id' => 1,
            'request_date' => now(),
            'is_active' => true,
            'status' => 'Pending',
        ]);
        $notification = AdminNotification::create([
            'title' => 'Disciplinary Sanction Request',
            'message' => 'A student requested a disciplinary sanction.',
            'notification_type' => 'disciplinary_sanction_request',
            'target_type' => 'sanction_request',
            'target_id' => $request->request_id,
            'recipient_type' => 'admin',
            'recipient_id' => $otherAdmin->id,
            'is_read' => false,
            'created_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.notifications.open', $notification))
            ->assertForbidden();
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

    private function createTables(): void
    {
        Schema::create('admins', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username');
            $table->string('email');
            $table->string('password');
            $table->boolean('mfa_totp_enabled')->default(false);
        });
        Schema::create('admin_notifications_tbl', function (Blueprint $table): void {
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
        });
        Schema::create('sanction_requests_tbl', function (Blueprint $table): void {
            $table->increments('request_id');
            $table->string('student_number');
            $table->unsignedInteger('violation_type_id');
            $table->dateTime('request_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status')->nullable();
            $table->unsignedInteger('approved_by_admin_id')->nullable();
            $table->dateTime('approved_at')->nullable();
        });
    }
}