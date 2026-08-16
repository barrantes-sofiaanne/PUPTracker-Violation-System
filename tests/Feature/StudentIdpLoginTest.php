<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StudentIdpLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureAuthTablesForTests();
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
    // 1-4. Login page + authorization redirect + secret not rendered
    // ------------------------------------------------------------------

    public function test_student_login_page_displays_continue_with_pup_id(): void
    {
        $response = $this->get(route('student.login'));
        $response->assertOk();
        $response->assertSee('Continue with PUP ID');
        $response->assertDontSee('Student Number');
    }

    public function test_student_login_no_longer_requires_student_number(): void
    {
        $content = $this->get(route('student.login'))->getContent();
        $this->assertStringNotContainsString('student_number', $content);
        $this->assertStringNotContainsString('student.idp.start', $content);
    }

    public function test_idp_authorization_redirect_uses_configured_client_id(): void
    {
        $response = $this->get(route('student.idp.login'));

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('https://idp.test/api/v1/auth/authorize', $location);
        $this->assertStringContainsString('client_id=student-client', $location);
        $this->assertStringContainsString('redirect_uri=https%3A%2F%2Fpuptvs.com%2Fstudent%2Fidp%2Fcallback', $location);
        $this->assertStringContainsString('state=', $location);
    }

    public function test_client_secret_is_never_rendered(): void
    {
        Config::set('services.idp.client_secret', 'super-secret-value');

        $this->get(route('student.login'))->assertOk();
        $this->assertStringNotContainsString('super-secret-value', $this->get(route('student.login'))->getContent());

        $response = $this->get(route('student.idp.login'));
        $this->assertStringNotContainsString('super-secret-value', (string) $response->headers->get('Location'));
    }

    // ------------------------------------------------------------------
    // 5-8. Successful exchange, /me, existing idp_subject, first-time link
    // ------------------------------------------------------------------

    public function test_successful_token_exchange_and_me(): void
    {
        $this->createStudent(['idp_subject' => 'user-123', 'idp_email' => 'student.idp@example.com']);
        $this->fakeIdp();

        $response = $this->hitCallback();

        $response->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated('student');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/auth/token'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/me'));
    }

    public function test_successful_login_using_existing_idp_subject(): void
    {
        $this->createStudent(['idp_subject' => 'user-123', 'idp_email' => 'student.idp@example.com']);
        $this->fakeIdp();

        $this->hitCallback()->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated('student');

        $this->assertDatabaseHas('users_tbl', [
            'student_number' => '2026-00003',
            'idp_subject' => 'user-123',
            'idp_email' => 'student.idp@example.com',
        ]);
    }

    public function test_first_time_safe_account_linking_by_email(): void
    {
        $this->createStudent();
        $this->fakeIdp();

        $this->hitCallback()->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated('student');

        $this->assertDatabaseHas('users_tbl', [
            'student_number' => '2026-00003',
            'idp_subject' => 'user-123',
            'idp_email' => 'student.idp@example.com',
        ]);
    }

    public function test_unknown_idp_account_id_is_rejected_without_safe_mapping(): void
    {
        $this->createStudent(['email' => 'different@example.com']);
        $this->fakeIdp(idpSubject: 'user-999', email: 'nobody@example.com');

        $response = $this->hitCallback();
        $response->assertRedirect(route('student.login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest('student');
    }

    // ------------------------------------------------------------------
    // 9-13. Inactive / non-student / role matching
    // ------------------------------------------------------------------

    public function test_inactive_local_student_is_rejected(): void
    {
        $this->createStudent(['idp_subject' => 'user-inactive', 'status_id' => 2]);
        $this->fakeIdp(idpSubject: 'user-inactive');

        $response = $this->hitCallback();
        $response->assertRedirect(route('student.login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest('student');
    }

    public function test_local_non_student_account_is_rejected(): void
    {
        $this->createStudent(['idp_subject' => 'user-admin', 'roles_id' => 1]);
        $this->fakeIdp(idpSubject: 'user-admin');

        $response = $this->hitCallback();
        $response->assertRedirect(route('student.login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest('student');
    }

    public function test_idp_non_student_role_is_rejected(): void
    {
        $this->createStudent(['idp_subject' => 'user-123']);
        $this->fakeIdp(roles: 'faculty');

        $response = $this->hitCallback();
        $response->assertRedirect(route('student.login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest('student');
    }

    public function test_exact_role_matching_accepts_capitalized_student(): void
    {
        // "Student" (capitalized) → accepted (case-insensitive whole token)
        $this->createStudent(['idp_subject' => 'user-a']);
        $this->fakeIdp(idpSubject: 'user-a', roles: 'Student');
        $this->hitCallback()->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated('student');
    }

    public function test_exact_role_matching_rejects_substring(): void
    {
        // "notstudent" → rejected (substring must NOT match "student")
        $this->createStudent([
            'idp_subject' => 'user-b',
            'email' => 'b@example.com',
            'student_number' => '2026-00004',
        ]);
        $this->fakeIdp(idpSubject: 'user-b', email: 'b@example.com', roles: 'notstudent');
        $response = $this->hitCallback();
        $response->assertRedirect(route('student.login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest('student');
    }

    // ------------------------------------------------------------------
    // 14-16. Email change + account identity stability
    // ------------------------------------------------------------------

    public function test_existing_account_id_remains_linked_even_if_email_changes(): void
    {
        $this->createStudent([
            'idp_subject' => 'ABC123',
            'idp_email' => 'old@student.pup.edu.ph',
        ]);

        $this->fakeIdp(idpSubject: 'ABC123', email: 'new@student.pup.edu.ph');

        $this->hitCallback()->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated('student');

        $this->assertDatabaseHas('users_tbl', [
            'student_number' => '2026-00003',
            'idp_subject' => 'ABC123',
            'idp_email' => 'new@student.pup.edu.ph',
        ]);
        $this->assertSame(1, User::count());
    }

    public function test_one_idp_account_cannot_be_linked_to_another_student(): void
    {
        $this->createStudent(['idp_subject' => 'user-bound-different']);
        $this->fakeIdp(idpSubject: 'user-imposter');

        $response = $this->hitCallback();
        $response->assertRedirect(route('student.login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest('student');
    }

    // ------------------------------------------------------------------
    // 17-19. Failure handling
    // ------------------------------------------------------------------

    public function test_token_exchange_api_failure_is_handled_safely(): void
    {
        Http::fake([
            'https://idp.test/api/v1/auth/token' => Http::response([], 500),
        ]);

        $response = $this->hitCallback();
        $response->assertRedirect(route('student.login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest('student');
    }

    public function test_missing_access_token_is_handled_safely(): void
    {
        Http::fake([
            'https://idp.test/api/v1/auth/token' => Http::response([
                'refresh_token' => 'x',
                'token_type' => 'Bearer',
            ], 200),
        ]);

        $response = $this->hitCallback();
        $response->assertRedirect(route('student.login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest('student');
    }

    public function test_missing_me_id_is_handled_safely(): void
    {
        $this->createStudent();
        Http::fake([
            'https://idp.test/api/v1/auth/token' => Http::response([
                'access_token' => 'access-token-123',
                'token_type' => 'Bearer',
            ], 200),
            'https://idp.test/api/v1/me' => Http::response([
                'email' => 'student.idp@example.com',
                'first_name' => 'IDP',
                'last_name' => 'Student',
                'roles' => 'student',
            ], 200),
        ]);

        $response = $this->hitCallback();
        $response->assertRedirect(route('student.login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest('student');
    }

    // ------------------------------------------------------------------
    // 20-21. Guard + session regeneration
    // ------------------------------------------------------------------

    public function test_student_guard_is_authenticated_after_success(): void
    {
        $this->createStudent(['idp_subject' => 'user-123']);
        $this->fakeIdp();

        $this->hitCallback()->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated('student');
        $this->assertEquals('2026-00003', auth('student')->user()->student_number);
    }

    public function test_session_is_regenerated_after_successful_login(): void
    {
        $this->createStudent(['idp_subject' => 'user-123']);
        $this->fakeIdp();

        // Capture the session id before the callback login.
        $this->withSession(['student.idp.state' => 'state-123']);
        $before = session()->getId();

        $response = $this->get(route('student.idp.callback', [
            'code' => 'auth-code-123',
            'state' => 'state-123',
        ]));

        $response->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated('student');

        // After login the session id should differ (session regenerated).
        $this->assertNotSame($before, session()->getId());
    }

    // ------------------------------------------------------------------
    // 22-23. Logout
    // ------------------------------------------------------------------

    public function test_logout_clears_local_authentication(): void
    {
        $this->createStudent(['idp_subject' => 'user-logout']);
        $user = User::where('student_number', '2026-00003')->first();
        $this->actingAs($user, 'student');
        $this->assertAuthenticated('student');

        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest('student');
    }

    public function test_idp_logout_does_not_expose_tokens(): void
    {
        Http::fake([
            'https://idp.test/api/v1/auth/logout' => Http::response([], 200),
        ]);

        $this->createStudent(['idp_subject' => 'user-logout']);
        $user = User::where('student_number', '2026-00003')->first();

        $this->withSession([
            'student.idp.access_token' => 'access-secret-xyz',
            'student.idp.refresh_token' => 'refresh-secret-xyz',
        ])->actingAs($user, 'student');

        $response = $this->post(route('logout'));
        $response->assertRedirect(route('home'));
        $this->assertGuest('student');

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/auth/logout')) {
                return true;
            }
            $body = $request->data();
            return ! in_array('access-secret-xyz', $body, true);
        });
    }

    // ------------------------------------------------------------------
    // 24-25. Tokens/secret not in rendered HTML, not in logs
    // ------------------------------------------------------------------

    public function test_no_access_or_refresh_token_in_rendered_html(): void
    {
        $this->createStudent(['idp_subject' => 'user-123']);
        $this->fakeIdp();

        $this->hitCallback()->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated('student');

        $this->assertStringNotContainsString('access-token-123', $this->get(route('student.login'))->getContent());
        $this->assertStringNotContainsString('refresh-token-123', $this->get(route('student.login'))->getContent());
    }

    public function test_client_secret_does_not_appear_in_logs_or_responses(): void
    {
        Config::set('services.idp.client_secret', 'super-secret-value');

        $this->fakeIdp();
        $this->createStudent(['idp_subject' => 'user-123']);

        $response = $this->hitCallback();
        $response->assertRedirect(route('student.dashboard'));

        $this->assertStringNotContainsString('super-secret-value', (string) $response->headers->get('Location'));
        $this->assertStringNotContainsString('super-secret-value', $this->get(route('student.login'))->getContent());
    }

    public function test_protected_student_route_requires_auth(): void
    {
        $this->get(route('student.dashboard'))->assertRedirect(route('student.login'));
    }

    // ------------------------------------------------------------------
    // Schema
    // ------------------------------------------------------------------

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
