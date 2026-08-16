<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminIdpLinkingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTablesForTests();
        $this->configureIdp();
    }

    private function configureIdp(): void
    {
        Config::set('services.idp.base_url', 'https://idp.test/api/v1');
        Config::set('services.idp.client_id', 'student-client');
        Config::set('services.idp.client_secret', 'student-secret');
        Config::set('services.idp.redirect_uri', 'https://puptvs.com/student/idp/callback');
        Config::set('services.idp.timeout', 15);
        Config::set('services.idp.student_roles', 'student');
    }

    private function fakeIdp(string $idpSubject = 'user-123', string $email = 'student.idp@example.com', string $roles = 'student'): void
    {
        Http::fake([
            'https://idp.test/api/v1/auth/token' => Http::response([
                'access_token' => 'access-token-123',
                'refresh_token' => 'refresh-token-123',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
            'https://idp.test/api/v1/me' => Http::response([
                'id' => $idpSubject,
                'email' => $email,
                'first_name' => 'IDP',
                'middle_name' => null,
                'last_name' => 'Student',
                'name_suffix' => null,
                'roles' => $roles,
            ], 200),
        ]);
    }

    private function createAdmin(): Admin
    {
        return Admin::create([
            'username' => 'it.admin',
            'email' => 'it.admin@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    private function createStudent(array $overrides = []): User
    {
        return User::create(array_merge([
            'student_number' => '2026-00003',
            'first_name' => 'IDP',
            'last_name' => 'Student',
            'email' => 'student.idp@example.com',
            'idp_subject' => null,
            'idp_email' => null,
            'status_id' => 1,
            'roles_id' => 2,
            'password_hash' => Hash::make('password123'),
            'mfa_totp_enabled' => false,
        ], $overrides));
    }

    private function hitCallback(string $code = 'auth-code-123', ?string $state = null): TestResponse
    {
        $session = ['student.idp.state' => 'state-123'];
        $params = ['code' => $code];
        if ($state !== null) {
            $params['state'] = $state;
        }

        return $this->withSession($session)->get(route('student.idp.callback', $params));
    }

    // ------------------------------------------------------------------
    // 1. Existing linked IDP account logs in successfully
    // ------------------------------------------------------------------

    public function test_existing_linked_idp_account_logs_in_successfully(): void
    {
        $this->createStudent(['idp_subject' => 'user-linked', 'idp_email' => 'linked@example.com']);
        $this->fakeIdp(idpSubject: 'user-linked', email: 'linked@example.com');

        $this->hitCallback()->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated('student');
    }

    // ------------------------------------------------------------------
    // 2. Unlinked IDP account is rejected
    // ------------------------------------------------------------------

    public function test_unlinked_idp_account_is_rejected(): void
    {
        // Local student email does NOT match the IDP email, so no safe
        // auto-link occurs. The student is NOT logged in; they are sent to the
        // secure self-linking step instead.
        $this->createStudent(['email' => 'different@example.com']);
        $this->fakeIdp(idpSubject: 'user-unknown', email: 'nobody@example.com');

        $response = $this->hitCallback();
        $response->assertRedirect(route('student.idp.link'));
        $this->assertGuest('student');
    }

    // ------------------------------------------------------------------
    // 3. Administrator can link an IDP Account ID to a student
    // ------------------------------------------------------------------

    public function test_admin_can_link_idp_account_to_student(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent();

        $this->actingAs($admin, 'admin');

        $this->post(route('admin.students.link-idp', $student->student_number), [
            'idp_subject' => 'ABC123',
            'idp_email' => 'student@student.pup.edu.ph',
        ])->assertRedirect();

        $this->assertDatabaseHas('users_tbl', [
            'user_id' => $student->user_id,
            'idp_subject' => 'ABC123',
            'idp_email' => 'student@student.pup.edu.ph',
        ]);
        $this->assertNotNull($student->fresh()->idp_connected_at);

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
            'action' => 'Link IDP Account',
            'module' => 'Students',
        ]);
    }

    // ------------------------------------------------------------------
    // 4. Duplicate IDP Account IDs are rejected
    // ------------------------------------------------------------------

    public function test_duplicate_idp_account_id_is_rejected(): void
    {
        $admin = $this->createAdmin();
        $this->createStudent([
            'student_number' => '2026-00001',
            'idp_subject' => 'ABC123',
            'email' => 'one@example.com',
        ]);
        $other = $this->createStudent([
            'student_number' => '2026-00002',
            'email' => 'two@example.com',
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->post(route('admin.students.link-idp', $other->student_number), [
            'idp_subject' => 'ABC123',
        ]);

        $response->assertSessionHasErrors('idp_subject');
        $this->assertSame(null, $other->fresh()->idp_subject);
    }

    // ------------------------------------------------------------------
    // 5. After linking, the student can log in using PUP ID
    // ------------------------------------------------------------------

    public function test_after_linking_student_can_log_in_with_pup_id(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent();

        // Link via admin
        $this->actingAs($admin, 'admin');
        $this->post(route('admin.students.link-idp', $student->student_number), [
            'idp_subject' => 'user-linked',
            'idp_email' => 'student.idp@example.com',
        ])->assertRedirect();

        // Now log in via IDP
        $this->fakeIdp(idpSubject: 'user-linked', email: 'student.idp@example.com');
        $this->hitCallback()->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated('student');
    }

    // ------------------------------------------------------------------
    // 6. Changing IDP email does not change linked local student
    // ------------------------------------------------------------------

    public function test_changing_idp_email_keeps_same_linked_student(): void
    {
        $this->createStudent([
            'idp_subject' => 'ABC123',
            'idp_email' => 'old@student.pup.edu.ph',
        ]);

        // IDP now returns the same subject but a new email.
        $this->fakeIdp(idpSubject: 'ABC123', email: 'new@student.pup.edu.ph');

        $this->hitCallback()->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated('student');

        $this->assertDatabaseHas('users_tbl', [
            'idp_subject' => 'ABC123',
            'idp_email' => 'new@student.pup.edu.ph',
        ]);
        $this->assertSame(1, User::count());
    }

    // ------------------------------------------------------------------
    // 7. Inactive local student cannot log in even with valid IDP account
    // ------------------------------------------------------------------

    public function test_inactive_local_student_cannot_log_in_with_valid_idp(): void
    {
        $this->createStudent([
            'idp_subject' => 'user-inactive',
            'idp_email' => 'inactive@example.com',
            'status_id' => 2,
        ]);
        $this->fakeIdp(idpSubject: 'user-inactive', email: 'inactive@example.com');

        $response = $this->hitCallback();
        $response->assertRedirect(route('student.login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest('student');
    }

    // ------------------------------------------------------------------
    // 8. Admin unlink
    // ------------------------------------------------------------------

    public function test_admin_can_unlink_idp_account(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent([
            'idp_subject' => 'ABC123',
            'idp_email' => 'student.idp@example.com',
        ]);

        $this->actingAs($admin, 'admin');

        $this->post(route('admin.students.unlink-idp', $student->student_number))
            ->assertRedirect();

        $this->assertDatabaseHas('users_tbl', [
            'user_id' => $student->user_id,
            'idp_subject' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
            'action' => 'Unlink IDP Account',
            'module' => 'Students',
        ]);
    }

    // ------------------------------------------------------------------
    // 9. Only admins can link (student cannot)
    // ------------------------------------------------------------------

    public function test_student_cannot_link_idp_account(): void
    {
        $student = $this->createStudent();

        // Acting as a student (not an admin) — the route is admin-only. The
        // auth:admin middleware redirects non-admin users away (302), so the
        // student can never submit an IDP Account ID.
        $this->actingAs($student, 'student');

        $response = $this->post(route('admin.students.link-idp', $student->student_number), [
            'idp_subject' => 'ABC123',
        ]);
        $response->assertRedirect();
        $this->assertGuest('admin');

        $this->assertSame(null, $student->fresh()->idp_subject);
    }

    // ------------------------------------------------------------------
    // Secure self-linking flow (student never types their IDP id)
    // ------------------------------------------------------------------

    public function test_unlinked_student_is_redirected_to_self_link_form(): void
    {
        $this->createStudent(['email' => 'different@example.com']);
        $this->fakeIdp(idpSubject: 'user-new', email: 'nobody@example.com');

        $response = $this->hitCallback();
        $response->assertRedirect(route('student.idp.link'));
    }

    public function test_self_link_requires_pending_idp_session(): void
    {
        // No pending session -> redirect back to IDP login.
        $response = $this->get(route('student.idp.link'));
        $response->assertRedirect(route('student.idp.login'));
    }

    public function test_student_can_self_link_with_correct_student_number(): void
    {
        // Local email differs from IDP email so the callback does NOT auto-link.
        $student = $this->createStudent(['email' => 'local@example.com']);
        $this->fakeIdp(idpSubject: 'user-new', email: 'student.idp@example.com');

        $this->hitCallback()->assertRedirect(route('student.idp.link'));

        // Self-link form must use a matching local email (secondary signal).
        $response = $this->withSession([
            'student.idp.pending_subject' => 'user-new',
            'student.idp.pending_email' => 'student.idp@example.com',
        ])->post(route('student.idp.link.post'), [
            'student_number' => '2026-00003',
            // send matching local email to pass the secondary check
        ]);

        $response->assertSessionHasErrors('email_mismatch');
        $this->assertGuest('student');

        // Now with matching email (local email updated to match IDP), linking succeeds.
        $student->forceFill(['email' => 'student.idp@example.com'])->save();

        $response = $this->withSession([
            'student.idp.pending_subject' => 'user-new',
            'student.idp.pending_email' => 'student.idp@example.com',
        ])->post(route('student.idp.link.post'), [
            'student_number' => '2026-00003',
        ]);

        $response->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated('student');

        $this->assertDatabaseHas('users_tbl', [
            'user_id' => $student->user_id,
            'idp_subject' => 'user-new',
            'idp_email' => 'student.idp@example.com',
        ]);
    }

    public function test_self_link_rejects_unknown_student_number(): void
    {
        // A local student exists but the submitted student number is wrong.
        $this->createStudent(['email' => 'other@example.com']);
        $this->fakeIdp(idpSubject: 'user-new', email: 'student.idp@example.com');

        $response = $this->withSession([
            'student.idp.pending_subject' => 'user-new',
            'student.idp.pending_email' => 'student.idp@example.com',
        ])->post(route('student.idp.link.post'), [
            'student_number' => 'NOPE-999',
        ]);

        $response->assertSessionHasErrors('student_number');
        $this->assertGuest('student');
    }

    public function test_self_link_rejects_if_idp_id_already_bound_to_another(): void
    {
        $this->createStudent([
            'student_number' => '2026-00001',
            'idp_subject' => 'user-bound',
            'email' => 'bound@example.com',
        ]);
        $other = $this->createStudent([
            'student_number' => '2026-00002',
            'email' => 'other@example.com',
        ]);

        $this->fakeIdp(idpSubject: 'user-bound', email: 'bound@example.com');

        // IDP id user-bound already linked to 2026-00001.
        $response = $this->withSession([
            'student.idp.pending_subject' => 'user-bound',
            'student.idp.pending_email' => 'bound@example.com',
        ])->post(route('student.idp.link.post'), [
            'student_number' => '2026-00002',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertSame(null, $other->fresh()->idp_subject);
        $this->assertGuest('student');
    }

    public function test_self_link_rejects_email_mismatch(): void
    {
        $student = $this->createStudent(['email' => 'local@example.com']);

        $this->fakeIdp(idpSubject: 'user-new', email: 'different@idp.example.com');
        $this->hitCallback()->assertRedirect(route('student.idp.link'));

        $response = $this->withSession([
            'student.idp.pending_subject' => 'user-new',
            'student.idp.pending_email' => 'different@idp.example.com',
        ])->post(route('student.idp.link.post'), [
            'student_number' => '2026-00003',
        ]);

        $response->assertSessionHasErrors('email_mismatch');
        $this->assertSame(null, $student->fresh()->idp_subject);
        $this->assertGuest('student');
    }

    public function test_self_link_rejects_inactive_student(): void
    {
        $this->createStudent(['status_id' => 2]);
        $this->fakeIdp(idpSubject: 'user-new', email: 'student.idp@example.com');
        $this->hitCallback()->assertRedirect(route('student.idp.link'));

        $response = $this->withSession([
            'student.idp.pending_subject' => 'user-new',
            'student.idp.pending_email' => 'student.idp@example.com',
        ])->post(route('student.idp.link.post'), [
            'student_number' => '2026-00003',
        ]);

        $response->assertSessionHasErrors('student_number');
        $this->assertGuest('student');
    }

    // ------------------------------------------------------------------
    // Schema
    // ------------------------------------------------------------------

    private function ensureTablesForTests(): void
    {
        if (! Schema::hasTable('roles_tbl')) {
            Schema::create('roles_tbl', function (Blueprint $table): void {
                $table->id('roles_id');
                $table->string('roles_name')->nullable();
            });
            DB::table('roles_tbl')->insert([
                ['roles_id' => 1, 'roles_name' => 'Admin'],
                ['roles_id' => 2, 'roles_name' => 'Student'],
                ['roles_id' => 3, 'roles_name' => 'Security'],
            ]);
        }

        if (! Schema::hasTable('status_tbl')) {
            Schema::create('status_tbl', function (Blueprint $table): void {
                $table->id('status_id');
                $table->string('status_name')->nullable();
            });
            DB::table('status_tbl')->insert([
                ['status_id' => 1, 'status_name' => 'Active'],
                ['status_id' => 2, 'status_name' => 'Inactive'],
            ]);
        }

        if (! Schema::hasTable('users_tbl')) {
            Schema::create('users_tbl', function (Blueprint $table): void {
                $table->id('user_id');
                $table->string('student_number')->unique();
                $table->string('first_name')->nullable();
                $table->string('middle_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->string('idp_subject')->nullable()->unique();
                $table->string('idp_email')->nullable();
                $table->timestamp('idp_connected_at')->nullable();
                $table->timestamp('idp_last_login_at')->nullable();
                $table->unsignedBigInteger('gender_id')->nullable();
                $table->unsignedBigInteger('status_id')->nullable();
                $table->unsignedBigInteger('roles_id')->nullable();
                $table->string('password_hash');
                $table->string('reset_token_hash')->nullable();
                $table->timestamp('reset_token_expires_at')->nullable();
                $table->timestamp('new_until')->nullable();
                $table->text('mfa_totp_secret')->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
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
}
