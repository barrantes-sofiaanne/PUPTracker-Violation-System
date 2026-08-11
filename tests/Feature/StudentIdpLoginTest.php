<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentIdpLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureAuthTablesForTests();
    }

    public function test_student_can_start_and_complete_idp_login(): void
    {
        User::create([
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
        ]);

        Config::set('services.idp.base_url', 'https://idp.test/api/v1');
        Config::set('services.idp.client_id', 'student-client');
        Config::set('services.idp.client_secret', 'student-secret');

        Http::fake([
            'https://idp.test/api/v1/auth/token' => Http::response([
                'access_token' => 'access-token-123',
                'refresh_token' => 'refresh-token-123',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
            'https://idp.test/api/v1/me' => Http::response([
                'id' => 'user-123',
                'email' => 'student.idp@example.com',
                'first_name' => 'IDP',
                'middle_name' => null,
                'last_name' => 'Student',
                'name_suffix' => null,
                'roles' => 'student',
            ], 200),
        ]);

        $start = $this->post(route('student.idp.start'), [
            'student_number' => '2026-00003',
        ]);

        $start->assertRedirect(route('student.idp.login'));

        $redirectToIdp = $this->withSession([
            'student.idp.student_number' => '2026-00003',
        ])->get(route('student.idp.login'));

        $redirectToIdp->assertRedirect();
        $this->assertStringContainsString('https://idp.test/api/v1/auth/authorize', $redirectToIdp->headers->get('Location'));
        $this->assertStringContainsString('client_id=student-client', $redirectToIdp->headers->get('Location'));

        $state = session('student.idp.state');
        $pendingStudentNumber = session('student.idp.student_number');

        $callback = $this->withSession([
            'student.idp.state' => $state,
            'student.idp.student_number' => $pendingStudentNumber,
        ])
            ->get(route('student.idp.callback', [
                'code' => 'auth-code-123',
                'state' => $state,
            ]));

        $callback->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated('student');

        $this->assertDatabaseHas('users_tbl', [
            'student_number' => '2026-00003',
            'idp_subject' => 'user-123',
            'idp_email' => 'student.idp@example.com',
        ]);

        Http::assertSentCount(2);
    }

    public function test_start_rejects_unknown_or_inactive_student(): void
    {
        $response = $this->post(route('student.idp.start'), [
            'student_number' => 'NOPE-999',
        ]);

        $response->assertRedirect(route('student.login'));
        $response->assertSessionHasErrors('login');
    }

    public function test_redirect_requires_configured_client(): void
    {
        Config::set('services.idp.client_id', '');

        $response = $this->get(route('student.idp.login'));

        $response->assertRedirect(route('student.login'));
        $response->assertSessionHasErrors('login');
    }

    public function test_callback_requires_code(): void
    {
        $response = $this->withSession([
            'student.idp.state' => 'abc',
        ])->get(route('student.idp.callback'));

        $response->assertSessionHasErrors('code');
    }

    public function test_callback_rejects_invalid_state(): void
    {
        Config::set('services.idp.base_url', 'https://idp.test/api/v1');
        Config::set('services.idp.client_id', 'student-client');
        Config::set('services.idp.client_secret', 'student-secret');

        $response = $this->withSession([
            'student.idp.state' => 'expected-state-123',
        ])->get(route('student.idp.callback', [
            'code' => 'auth-code-123',
            'state' => 'tampered-state',
        ]));

        $response->assertRedirect(route('student.login'));
        $response->assertSessionHasErrors('login');
    }

    public function test_callback_rejects_invalid_code_exchange(): void
    {
        User::create([
            'student_number' => '2026-00004',
            'first_name' => 'IDP',
            'last_name' => 'Student',
            'email' => 'student2@example.com',
            'idp_subject' => null,
            'idp_email' => null,
            'status_id' => 1,
            'roles_id' => 2,
            'password_hash' => Hash::make('password123'),
            'mfa_totp_enabled' => false,
        ]);

        Config::set('services.idp.base_url', 'https://idp.test/api/v1');
        Config::set('services.idp.client_id', 'student-client');
        Config::set('services.idp.client_secret', 'student-secret');

        Http::fake([
            'https://idp.test/api/v1/auth/token' => Http::response([
                'code' => 1000,
                'message' => 'Invalid authorization code.',
                'error' => 'invalid_grant',
            ], 401),
        ]);

        $response = $this->withSession([
            'student.idp.state' => 'state',
            'student.idp.student_number' => '2026-00004',
        ])->get(route('student.idp.callback', [
            'code' => 'bad-code',
            'state' => 'state',
        ]));

        $response->assertRedirect(route('student.login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest('student');
    }

    public function test_callback_rejects_unknown_idp_user(): void
    {
        Config::set('services.idp.base_url', 'https://idp.test/api/v1');
        Config::set('services.idp.client_id', 'student-client');
        Config::set('services.idp.client_secret', 'student-secret');

        Http::fake([
            'https://idp.test/api/v1/auth/token' => Http::response([
                'access_token' => 'access-token-999',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
            'https://idp.test/api/v1/me' => Http::response([
                'id' => 'unknown-user-999',
                'email' => 'nobody@example.com',
                'first_name' => 'Nobody',
                'last_name' => 'Nobody',
                'roles' => 'student',
            ], 200),
        ]);

        $response = $this->withSession([
            'student.idp.state' => 'state',
        ])->get(route('student.idp.callback', [
            'code' => 'auth-code-123',
            'state' => 'state',
        ]));

        $response->assertRedirect(route('student.login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest('student');
    }

    public function test_callback_rejects_inactive_local_student(): void
    {
        User::create([
            'student_number' => '2026-00005',
            'first_name' => 'IDP',
            'last_name' => 'Student',
            'email' => 'inactive.student@example.com',
            'idp_subject' => 'user-inactive',
            'idp_email' => 'inactive.student@example.com',
            'status_id' => 2,
            'roles_id' => 2,
            'password_hash' => Hash::make('password123'),
            'mfa_totp_enabled' => false,
        ]);

        Config::set('services.idp.base_url', 'https://idp.test/api/v1');
        Config::set('services.idp.client_id', 'student-client');
        Config::set('services.idp.client_secret', 'student-secret');

        Http::fake([
            'https://idp.test/api/v1/auth/token' => Http::response([
                'access_token' => 'access-token-inactive',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
            'https://idp.test/api/v1/me' => Http::response([
                'id' => 'user-inactive',
                'email' => 'inactive.student@example.com',
                'first_name' => 'IDP',
                'last_name' => 'Student',
                'roles' => 'student',
            ], 200),
        ]);

        $response = $this->withSession([
            'student.idp.state' => 'state',
        ])->get(route('student.idp.callback', [
            'code' => 'auth-code-123',
            'state' => 'state',
        ]));

        $response->assertRedirect(route('student.login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest('student');
    }

    public function test_callback_rejects_wrong_role_account(): void
    {
        User::create([
            'student_number' => '2026-00006',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin.user@example.com',
            'idp_subject' => 'user-admin',
            'idp_email' => 'admin.user@example.com',
            'status_id' => 1,
            'roles_id' => 1, // Admin role
            'password_hash' => Hash::make('password123'),
            'mfa_totp_enabled' => false,
        ]);

        Config::set('services.idp.base_url', 'https://idp.test/api/v1');
        Config::set('services.idp.client_id', 'student-client');
        Config::set('services.idp.client_secret', 'student-secret');

        Http::fake([
            'https://idp.test/api/v1/auth/token' => Http::response([
                'access_token' => 'access-token-admin',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
            'https://idp.test/api/v1/me' => Http::response([
                'id' => 'user-admin',
                'email' => 'admin.user@example.com',
                'first_name' => 'Admin',
                'last_name' => 'User',
                'roles' => 'student',
            ], 200),
        ]);

        $response = $this->withSession([
            'student.idp.state' => 'state',
        ])->get(route('student.idp.callback', [
            'code' => 'auth-code-123',
            'state' => 'state',
        ]));

        $response->assertRedirect(route('student.login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest('student');
    }

    public function test_callback_rejects_idp_account_linked_to_different_student(): void
    {
        // Local student is already bound to a DIFFERENT idp_subject.
        User::create([
            'student_number' => '2026-00007',
            'first_name' => 'IDP',
            'last_name' => 'Student',
            'email' => 'bound.student@example.com',
            'idp_subject' => 'user-bound-different',
            'idp_email' => 'bound.student@example.com',
            'status_id' => 1,
            'roles_id' => 2,
            'password_hash' => Hash::make('password123'),
            'mfa_totp_enabled' => false,
        ]);

        Config::set('services.idp.base_url', 'https://idp.test/api/v1');
        Config::set('services.idp.client_id', 'student-client');
        Config::set('services.idp.client_secret', 'student-secret');

        Http::fake([
            'https://idp.test/api/v1/auth/token' => Http::response([
                'access_token' => 'access-token-x',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
            'https://idp.test/api/v1/me' => Http::response([
                'id' => 'user-imposter',
                'email' => 'bound.student@example.com',
                'first_name' => 'IDP',
                'last_name' => 'Student',
                'roles' => 'student',
            ], 200),
        ]);

        // The email fallback would find the bound student, but its idp_subject
        // differs, so login must be rejected.
        $response = $this->withSession([
            'student.idp.state' => 'state',
        ])->get(route('student.idp.callback', [
            'code' => 'auth-code-123',
            'state' => 'state',
        ]));

        $response->assertRedirect(route('student.login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest('student');
    }

    public function test_idp_api_failure_is_handled(): void
    {
        Config::set('services.idp.base_url', 'https://idp.test/api/v1');
        Config::set('services.idp.client_id', 'student-client');
        Config::set('services.idp.client_secret', 'student-secret');

        Http::fake([
            'https://idp.test/api/v1/auth/token' => Http::response([], 500),
        ]);

        $response = $this->withSession([
            'student.idp.state' => 'state',
        ])->get(route('student.idp.callback', [
            'code' => 'auth-code-123',
            'state' => 'state',
        ]));

        $response->assertRedirect(route('student.login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest('student');
    }

    public function test_client_secret_is_not_exposed_in_rendered_views(): void
    {
        Config::set('services.idp.client_secret', 'super-secret-value');

        $this->get(route('student.login'))->assertOk();

        // Secret must never appear in rendered HTML/JS.
        $this->assertStringNotContainsString(
            'super-secret-value',
            $this->get(route('student.login'))->getContent(),
        );
    }

    public function test_logout_clears_student_session(): void
    {
        User::create([
            'student_number' => '2026-00008',
            'first_name' => 'IDP',
            'last_name' => 'Student',
            'email' => 'logout.student@example.com',
            'idp_subject' => 'user-logout',
            'idp_email' => 'logout.student@example.com',
            'status_id' => 1,
            'roles_id' => 2,
            'password_hash' => Hash::make('password123'),
            'mfa_totp_enabled' => false,
        ]);

        $user = User::where('student_number', '2026-00008')->first();
        $this->actingAs($user, 'student');

        $this->assertAuthenticated('student');

        $this->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest('student');
    }

    public function test_protected_student_route_requires_auth(): void
    {
        $this->get(route('student.dashboard'))->assertRedirect(route('student.login'));
    }

    private function ensureAuthTablesForTests(): void
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

        if (! Schema::hasTable('security')) {
            Schema::create('security', function (Blueprint $table): void {
                $table->id();
                $table->string('email')->unique();
                $table->string('password');
                $table->text('mfa_totp_secret')->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
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
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('violation_tbl')) {
            Schema::create('violation_tbl', function (Blueprint $table): void {
                $table->id('violation_id');
                $table->unsignedBigInteger('recorder_id')->nullable();
                $table->string('evidence_path')->nullable();
                $table->string('recorder_type', 20)->nullable();
                $table->string('recorder_name', 150)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('announcements_tbl')) {
            Schema::create('announcements_tbl', function (Blueprint $table): void {
                $table->id('announcement_id');
                $table->string('attachment_path')->nullable();
                $table->boolean('show_on_login')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('security_info_tbl')) {
            Schema::create('security_info_tbl', function (Blueprint $table): void {
                $table->id('security_info_id');
                $table->unsignedBigInteger('security_id')->nullable();
                $table->string('firstname', 100)->nullable();
                $table->string('middlename', 100)->nullable();
                $table->string('lastname', 100)->nullable();
                $table->timestamps();
            });
        }
    }
}