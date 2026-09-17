<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Security;
use App\Support\Access\SecurityAccess;
use App\Support\Auth\LoginAttemptLimiter;
use App\Support\MfaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * PART 1/2 — the OSS Staff role, and the security-operational access rule.
 * (The role was formerly named "Admin Assistant"; the live role name is OSS Staff.)
 * PART 17 — 3 failed credential attempts.
 * PART 8  — no Remember Me on the credential login screen.
 *
 * STRICT MODULE ISOLATION: the Security module belongs to Security Personnel
 * ONLY. Accepting the `admin` guard on the security routes exists solely so an
 * admin-guard account can be identified and then REFUSED — every admin role
 * (OSS Staff, plain Administrator, IT Administrator) is denied, and only a
 * Security account is admitted.
 */
class AdminAssistantAndLoginSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
        $this->clearLoginAttempts();
    }

    /**
     * Reset the module counters between tests.
     *
     * The limiter uses the cache, so a leftover entry from a previous test would
     * leak a lockout into the next one.
     */
    private function clearLoginAttempts(string $email = 'admin@example.com'): void
    {
        foreach (['admin', 'security'] as $module) {
            LoginAttemptLimiter::clear($module, Request::create('/login', 'POST', ['email' => $email]));
        }
    }

    // ==================================================================
    // PART 1 — OSS Staff role
    // ==================================================================

    /**
     * The role-creation migration and the rename migration are both idempotent.
     *
     * Start from the PRE-migration state (no Admin Assistant / OSS Staff row) so
     * the assertion describes the real deployment order: the creation migration
     * ran once historically, then the rename migration moved the row to the
     * CURRENT name. Re-running either must not add a second role.
     */
    public function test_admin_assistant_role_migration_is_idempotent(): void
    {
        $migration = require base_path('database/migrations/2026_09_16_000004_add_admin_assistant_role.php');
        $rename = require base_path('database/migrations/2026_09_17_042228_rename_admin_assistant_role_to_oss_staff.php');

        DB::table('roles_tbl')
            ->whereIn(DB::raw('LOWER(roles_name)'), ['admin assistant', 'oss staff'])
            ->delete();

        $migration->up();
        $migration->up();
        $rename->up();
        $rename->up();

        $this->assertSame(
            1,
            DB::table('roles_tbl')->where('roles_name', Admin::ROLE_OSS_STAFF)->count(),
            'Running the migrations twice must not duplicate the role.'
        );
    }

    public function test_oss_staff_role_is_recognised_case_insensitively(): void
    {
        $assistant = $this->createAdmin('asst@example.com', Admin::ROLE_OSS_STAFF);

        $this->assertTrue($assistant->isOssStaff());
        $this->assertSame(Admin::ROLE_OSS_STAFF, $assistant->roleName());
    }

    public function test_other_admin_roles_are_not_treated_as_oss_staff(): void
    {
        $plain = $this->createAdmin('plain@example.com', 'Admin');
        $it = $this->createAdmin('it@example.com', 'IT Administrator');

        $this->assertFalse($plain->isOssStaff());
        $this->assertFalse($it->isOssStaff());
    }

    /** An unknown/absent role must fail CLOSED. */
    public function test_unknown_role_is_not_oss_staff(): void
    {
        $admin = Admin::create(['email' => 'unknown@example.com', 'password' => Hash::make('x')]);

        DB::table('admin_info_tbl')->insert([
            'admin_id' => $admin->id,
            'role_id' => null,
            'role' => 'Some Fabricated Role',
            'firstname' => 'T',
            'lastname' => 'U',
        ]);

        $this->assertFalse($admin->fresh()->isOssStaff());
    }

    // ==================================================================
    // PART 1/2 — the access rule (the security-relevant part)
    //
    // STRICT MODULE ISOLATION: the Security module belongs to Security
    // Personnel ONLY. An OSS Staff account owns its OWN `/oss-staff/...`
    // module, so it must be refused here even though it authenticates on the
    // `admin` guard that these routes also accept.
    // ==================================================================

    public function test_oss_staff_is_denied_security_operational_surfaces(): void
    {
        $assistant = $this->createAdmin('asst@example.com', Admin::ROLE_OSS_STAFF);

        $this->assertFalse(
            SecurityAccess::allowed($assistant),
            'An OSS Staff account must not reach the Security module.'
        );
    }

    public function test_security_personnel_remain_allowed(): void
    {
        $security = $this->createSecurity('officer@example.com');

        $this->assertTrue(SecurityAccess::allowed($security));
    }

    /**
     * THE critical negative case: an `admin`-guard account must never reach the
     * security-operational surfaces — no matter which role it holds.
     */
    public function test_no_admin_role_reaches_security_operational_surfaces(): void
    {
        $plain = $this->createAdmin('plain@example.com', 'Admin');
        $it = $this->createAdmin('it@example.com', 'IT Administrator');
        $ossStaff = $this->createAdmin('oss@example.com', Admin::ROLE_OSS_STAFF);

        $this->assertFalse(SecurityAccess::allowed($plain), 'A plain Administrator must not reach security operations.');
        $this->assertFalse(SecurityAccess::allowed($it), 'An IT Administrator must not reach security operations.');
        $this->assertFalse(SecurityAccess::allowed($ossStaff), 'An OSS Staff account must not reach security operations.');
    }

    public function test_guest_is_denied_security_operational_surfaces(): void
    {
        $this->assertFalse(SecurityAccess::allowed(null));
    }

    /** End-to-end: the middleware must actually block a plain admin. */
    public function test_plain_admin_gets_403_from_a_security_route(): void
    {
        $plain = $this->createAdmin('plain@example.com', 'Admin');

        $this->actingAs($plain, 'admin')
            ->get(route('security.dashboard'))
            ->assertForbidden();
    }

    /**
     * ...and an OSS Staff account must ALSO be blocked (403, not a redirect:
     * the account is authenticated but simply not authorized here).
     */
    public function test_oss_staff_gets_403_from_a_security_route(): void
    {
        $assistant = $this->createAdmin('asst@example.com', Admin::ROLE_OSS_STAFF);

        $this->actingAs($assistant, 'admin')
            ->get(route('security.dashboard'))
            ->assertForbidden();
    }

    /**
     * ...and an IT Administrator must be blocked too.
     */
    public function test_it_administrator_gets_403_from_a_security_route(): void
    {
        $it = $this->createAdmin('it@example.com', 'IT Administrator');

        $this->actingAs($it, 'admin')
            ->get(route('security.dashboard'))
            ->assertForbidden();
    }

    public function test_security_personnel_still_reach_the_security_dashboard(): void
    {
        $security = $this->createSecurity('officer@example.com');

        $this->actingAs($security, 'security')
            ->get(route('security.dashboard'))
            ->assertOk();
    }

    // ==================================================================
    // PART 8 — Remember Me must not appear on credential login
    // ==================================================================

    public function test_admin_credential_login_has_no_remember_me(): void
    {
        $page = $this->get(route('admin.login'))->assertOk()->getContent();

        $this->assertStringNotContainsString('name="remember"', $page);
        $this->assertStringNotContainsString('rememberMe', $page);
        $this->assertStringNotContainsString('Remember me', $page);
    }

    public function test_security_credential_login_has_no_remember_me(): void
    {
        $page = $this->get(route('security.login'))->assertOk()->getContent();

        $this->assertStringNotContainsString('name="remember"', $page);
        $this->assertStringNotContainsString('rememberMe', $page);
        $this->assertStringNotContainsString('Remember me', $page);
    }

    /** PART 8b: the credential stage can no longer pre-enable remembering. */
    public function test_credential_login_post_cannot_pre_enable_remember(): void
    {
        $admin = $this->createAdmin('admin@example.com', 'Admin', withMfa: true);

        // A crafted POST that tries to pre-set remember at the credential stage.
        $this->post(route('admin.login.post'), [
            'email' => 'admin@example.com',
            'password' => 'password',
            'remember' => '1',
        ]);

        $pending = session(MfaService::SESSION_KEY);

        $this->assertIsArray($pending, 'MFA challenge should have started.');
        $this->assertFalse(
            (bool) ($pending['remember'] ?? false),
            'The credential stage must never pre-enable Remember Me.'
        );
    }

    /**
     * The method-SELECTION stage must not offer the option; the CODE-INPUT stage
     * must. This is the placement the brief requires WHEN the feature is on.
     *
     * The feature is disabled by default, so this test opts in explicitly. The
     * default-off behaviour (no checkbox anywhere, backend refusal) is covered
     * by RememberDeviceToggleTest.
     */
    public function test_mfa_code_stage_offers_remember_but_selection_does_not(): void
    {
        config()->set('auth.features.remember_device', true);

        $admin = $this->createAdmin('admin@example.com', 'Admin', withMfa: true);

        $this->post(route('admin.login.post'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        // Stage 1 — choose a method: the checkbox must NOT be present.
        $selection = $this->get(route('mfa.verify.show'))->assertOk()->getContent();
        $this->assertStringNotContainsString('mfaRememberMe', $selection);
        $this->assertStringNotContainsString('Remember this device for 12 hours', $selection);

        // Stage 2 — enter the code: the checkbox IS present.
        $this->post(route('mfa.verify.submit'), ['action' => 'select', 'method' => 'email']);

        $codeStage = $this->get(route('mfa.verify.show'))->assertOk()->getContent();
        $this->assertStringContainsString('mfaRememberMe', $codeStage);
        $this->assertStringContainsString('Remember this device for 12 hours', $codeStage);
    }

    public function test_remember_me_label_states_the_12_hour_window(): void
    {
        config()->set('auth.features.remember_device', true);

        $admin = $this->createAdmin('admin@example.com', 'Admin', withMfa: true);

        $this->post(route('admin.login.post'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        // Move to the code-input stage, where the label is rendered.
        $this->post(route('mfa.verify.submit'), ['action' => 'select', 'method' => 'email']);

        $page = $this->get(route('mfa.verify.show'))->assertOk()->getContent();

        // The configured window is 720 minutes = 12 hours, not 30 days.
        $this->assertStringNotContainsString('30 days', $page);
        $this->assertStringContainsString('12 hours', $page);
    }

    // ==================================================================
    // PART 17 — 3 failed attempts
    // ==================================================================

    public function test_three_failed_attempts_block_the_admin_login(): void
    {
        $this->createAdmin('admin@example.com', 'Admin');

        for ($i = 1; $i <= 3; $i++) {
            $this->post(route('admin.login.post'), [
                'email' => 'admin@example.com',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('login');
        }

        // 4th attempt: even with the CORRECT password the account is locked.
        $response = $this->post(route('admin.login.post'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertStringContainsString(
            'Too many failed login attempts',
            (string) session('errors')->first('login')
        );

        // No authenticated session was created.
        $this->assertGuest('admin');
    }

    /** A successful login resets the counter — 3 FAILURES, not 3 logins. */
    public function test_successful_login_resets_the_failed_attempt_counter(): void
    {
        $this->createAdmin('admin@example.com', 'Admin', withMfa: true);

        // Two failures...
        foreach ([1, 2] as $ignored) {
            $this->post(route('admin.login.post'), [
                'email' => 'admin@example.com',
                'password' => 'wrong',
            ]);
        }

        // ...then a success.
        $this->post(route('admin.login.post'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $request = Request::create('/login', 'POST', ['email' => 'admin@example.com']);

        $this->assertSame(
            0,
            LoginAttemptLimiter::attempts('admin', $request),
            'A successful credential check must clear the counter.'
        );
        $this->assertFalse(LoginAttemptLimiter::isLocked('admin', $request));
    }

    /** The counter is per account+IP, so failures do not leak across accounts. */
    public function test_failed_attempts_are_isolated_per_account(): void
    {
        $this->createAdmin('admin@example.com', 'Admin');
        $this->createAdmin('other@example.com', 'Admin');

        for ($i = 1; $i <= 3; $i++) {
            $this->post(route('admin.login.post'), [
                'email' => 'admin@example.com',
                'password' => 'wrong',
            ]);
        }

        // The other account is unaffected.
        $this->post(route('admin.login.post'), [
            'email' => 'other@example.com',
            'password' => 'wrong',
        ])->assertSessionHasErrors('login');

        $this->assertStringNotContainsString(
            'Too many failed login attempts',
            (string) session('errors')->first('login'),
            'A different account must not inherit the lockout.'
        );
    }

    public function test_security_login_also_caps_failed_attempts(): void
    {
        $this->createSecurity('officer@example.com');

        for ($i = 1; $i <= 3; $i++) {
            $this->post(route('security.login.post'), [
                'email' => 'officer@example.com',
                'password' => 'wrong',
            ])->assertSessionHasErrors('login');
        }

        $response = $this->post(route('security.login.post'), [
            'email' => 'officer@example.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertStringContainsString(
            'Too many failed login attempts',
            (string) session('errors')->first('login')
        );
    }

    /** A blocked attempt is recorded in the audit trail. */
    public function test_blocked_login_is_audited(): void
    {
        $this->createAdmin('admin@example.com', 'Admin');

        for ($i = 1; $i <= 4; $i++) {
            $this->post(route('admin.login.post'), [
                'email' => 'admin@example.com',
                'password' => 'wrong',
            ]);
        }

        $this->assertSame(
            1,
            AuditLog::where('action', 'Login Blocked')->where('module', 'Authentication')->count()
        );
    }

    /** No secret may leak into the lockout response. */
    public function test_lockout_message_does_not_leak_credentials(): void
    {
        $this->createAdmin('admin@example.com', 'Admin');

        for ($i = 1; $i <= 4; $i++) {
            $this->post(route('admin.login.post'), [
                'email' => 'admin@example.com',
                'password' => 'wrong-password-value',
            ]);
        }

        $message = (string) session('errors')->first('login');

        $this->assertStringNotContainsString('wrong-password-value', $message);
        $this->assertStringNotContainsString('password', strtolower($message));
        $this->assertStringNotContainsString('$2y$', $message);
    }

    // ==================================================================
    // Login lockout — module isolation, persistence, expiry
    // ==================================================================

    /**
     * A lockout on one module must NOT block a different module.
     *
     * This is the brief's explicit requirement that a lockout applies "only to
     * the affected authentication module/account".
     */
    public function test_admin_lockout_does_not_block_the_security_module(): void
    {
        $this->createAdmin('admin@example.com', 'Admin');
        $this->createSecurity('officer@example.com');

        // Exhaust the ADMIN budget.
        for ($i = 1; $i <= 3; $i++) {
            $this->post(route('admin.login.post'), [
                'email' => 'admin@example.com',
                'password' => 'wrong',
            ]);
        }

        $this->assertTrue(
            LoginAttemptLimiter::isLocked('admin', Request::create('/l', 'POST', ['email' => 'admin@example.com'])),
            'The Administrator module should be locked.'
        );

        // The Security module must still accept valid credentials.
        $this->post(route('security.login.post'), [
            'email' => 'officer@example.com',
            'password' => 'password',
        ])->assertRedirect(route('mfa.verify.show'));

        $this->assertFalse(
            LoginAttemptLimiter::isLocked('security', Request::create('/l', 'POST', ['email' => 'officer@example.com'])),
            'The Security module must not inherit the Administrator lockout.'
        );
    }

    /** A blocked module reports how long the user must wait. */
    public function test_lockout_message_states_the_wait_duration(): void
    {
        $this->createAdmin('admin@example.com', 'Admin');

        for ($i = 1; $i <= 3; $i++) {
            $this->post(route('admin.login.post'), [
                'email' => 'admin@example.com',
                'password' => 'wrong',
            ]);
        }

        $this->post(route('admin.login.post'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('login');

        $message = (string) session('errors')->first('login');

        // It must say the module is blocked and give a concrete wait.
        $this->assertStringContainsString('temporarily blocked', $message);
        $this->assertMatchesRegularExpression('/minute\(s\)|second\(s\)|hour\(s\)/', $message);
    }

    /**
     * Refreshing the login page must NOT reset the counter.
     *
     * The counter lives in the server-side cache, so a GET of the login form (the
     * "refresh") must leave it untouched.
     */
    public function test_refreshing_the_login_page_does_not_reset_the_counter(): void
    {
        $this->createAdmin('admin@example.com', 'Admin');

        for ($i = 1; $i <= 2; $i++) {
            $this->post(route('admin.login.post'), [
                'email' => 'admin@example.com',
                'password' => 'wrong',
            ]);
        }

        $request = Request::create('/l', 'POST', ['email' => 'admin@example.com']);
        $before = LoginAttemptLimiter::attempts('admin', $request);
        $this->assertSame(2, $before);

        // Refresh the login page several times.
        $this->get(route('admin.login'))->assertOk();
        $this->get(route('admin.login'))->assertOk();

        $this->assertSame(
            $before,
            LoginAttemptLimiter::attempts('admin', $request),
            'Refreshing the login page must not clear the failed-attempt counter.'
        );
    }

    /** Once the lockout window lapses, login works again. */
    public function test_login_becomes_available_after_the_lockout_window_lapses(): void
    {
        $this->createAdmin('admin@example.com', 'Admin', withMfa: true);

        for ($i = 1; $i <= 3; $i++) {
            $this->post(route('admin.login.post'), [
                'email' => 'admin@example.com',
                'password' => 'wrong',
            ]);
        }

        // Blocked with the correct password.
        $this->post(route('admin.login.post'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('login');

        // Simulate the window elapsing.
        $this->travel(16)->minutes();

        $this->post(route('admin.login.post'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertRedirect(route('mfa.verify.show'));

        $request = Request::create('/l', 'POST', ['email' => 'admin@example.com']);
        $this->assertFalse(LoginAttemptLimiter::isLocked('admin', $request));
    }

    /** Wrong password once: the module stays usable. */
    public function test_a_single_wrong_password_leaves_login_available(): void
    {
        $this->createAdmin('admin@example.com', 'Admin', withMfa: true);

        $this->post(route('admin.login.post'), [
            'email' => 'admin@example.com',
            'password' => 'wrong',
        ])->assertSessionHasErrors('login');

        $this->assertStringContainsString(
            '2 attempt(s) remaining',
            (string) session('errors')->first('login'),
            'The user should be told how many attempts remain.'
        );

        // The correct password still works.
        $this->post(route('admin.login.post'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertRedirect(route('mfa.verify.show'));
    }

    /** The configured threshold and lockout come from config, not hard-coding. */
    public function test_lockout_policy_is_configurable_per_module(): void
    {
        $this->assertSame(3, LoginAttemptLimiter::policy('admin')['max']);
        $this->assertSame(900, LoginAttemptLimiter::policy('admin')['lockout_seconds']);

        // An unknown module falls back to the shared default.
        $this->assertSame(3, LoginAttemptLimiter::policy('some-future-module')['max']);
        $this->assertSame(900, LoginAttemptLimiter::policy('some-future-module')['lockout_seconds']);
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    /**
     * @param  bool  $withMfa  give the account a TOTP secret so the login flow
     *                         proceeds to the MFA challenge.
     */
    private function createAdmin(string $email, string $roleName, bool $withMfa = false): Admin
    {
        $admin = Admin::create([
            'email' => $email,
            'password' => Hash::make('password'),
            'mfa_totp_enabled' => $withMfa,
        ]);

        DB::table('admin_info_tbl')->insert([
            'admin_id' => $admin->id,
            'role_id' => DB::table('roles_tbl')->where('roles_name', $roleName)->value('roles_id'),
            'Position' => $roleName,
            'firstname' => 'Test',
            'lastname' => 'Admin',
        ]);

        return $admin->fresh();
    }

    private function createSecurity(string $email): Security
    {
        return Security::create([
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
    }

    private function createTables(): void
    {
        foreach ([
            'roles_tbl' => function (Blueprint $t): void {
                $t->increments('roles_id');
                $t->string('roles_name');
            },
            'admins' => function (Blueprint $t): void {
                $t->increments('id');
                $t->string('username')->nullable();
                $t->string('email');
                $t->string('password');
                $t->text('mfa_totp_secret')->nullable();
                $t->boolean('mfa_totp_enabled')->default(false);
                $t->text('mfa_backup_codes')->nullable();
                $t->text('mfa_backup_codes_used')->nullable();
                $t->timestamps();
                $t->rememberToken();
            },
            'admin_info_tbl' => function (Blueprint $t): void {
                $t->increments('id');
                $t->unsignedBigInteger('admin_id')->nullable();
                $t->unsignedBigInteger('role_id')->nullable();
                $t->string('Position')->nullable();
                $t->string('designation')->nullable();
                $t->string('role')->nullable();
                $t->string('title')->nullable();
                $t->string('firstname')->nullable();
                $t->string('middlename')->nullable();
                $t->string('lastname')->nullable();
            },
            'security' => function (Blueprint $t): void {
                $t->increments('id');
                $t->string('email');
                $t->string('password');
                $t->text('mfa_totp_secret')->nullable();
                $t->boolean('mfa_totp_enabled')->default(false);
                $t->text('mfa_backup_codes')->nullable();
                $t->text('mfa_backup_codes_used')->nullable();
                $t->timestamps();
                $t->rememberToken();
            },
            'security_info_tbl' => function (Blueprint $t): void {
                $t->increments('id');
                $t->unsignedBigInteger('security_id')->nullable();
                $t->string('firstname')->nullable();
                $t->string('middlename')->nullable();
                $t->string('lastname')->nullable();
            },
            'security_info' => function (Blueprint $t): void {
                $t->unsignedBigInteger('security_id')->primary();
                $t->string('firstname')->nullable();
                $t->string('middlename')->nullable();
                $t->string('lastname')->nullable();
            },
            'audit_logs' => function (Blueprint $t): void {
                $t->increments('id');
                $t->string('action')->nullable();
                $t->string('module')->nullable();
                $t->string('actor_type')->nullable();
                $t->unsignedBigInteger('actor_id')->nullable();
                $t->text('description')->nullable();
                $t->timestamp('created_at')->nullable();
            },
            // Tables the security dashboard reads on render. Only needed so the
            // "allowed" cases can assert a real 200 instead of a fixture 500.
            'users_tbl' => function (Blueprint $t): void {
                $t->increments('user_id');
                $t->string('student_number')->nullable();
                $t->string('first_name')->nullable();
                $t->string('last_name')->nullable();
                $t->string('email')->nullable();
                $t->unsignedInteger('roles_id')->nullable();
                $t->unsignedInteger('status_id')->nullable();
                $t->timestamp('profile_completed_at')->nullable();
                $t->timestamps();
            },
            'program_tbl' => function (Blueprint $t): void {
                $t->increments('program_id');
                $t->string('program_name')->nullable();
            },
            'year_tbl' => function (Blueprint $t): void {
                $t->increments('year_id');
                $t->string('year')->nullable();
            },
            'section_tbl' => function (Blueprint $t): void {
                $t->increments('section_id');
                $t->string('section_name')->nullable();
            },
            'student_info_tbl' => function (Blueprint $t): void {
                $t->unsignedBigInteger('user_id')->primary();
                $t->unsignedBigInteger('program_id')->nullable();
                $t->unsignedBigInteger('year_id')->nullable();
                $t->unsignedBigInteger('section_id')->nullable();
                $t->unsignedBigInteger('student_status_id')->nullable();
            },
            'violation_tbl' => function (Blueprint $t): void {
                $t->increments('violation_id');
                $t->string('student_number')->nullable();
                $t->string('violation_type')->nullable();
                $t->dateTime('violation_date')->nullable();
                $t->text('description')->nullable();
                $t->string('recorder_type', 20)->nullable();
                $t->unsignedBigInteger('recorder_id')->nullable();
                $t->string('recorder_name', 150)->nullable();
                $t->unsignedBigInteger('recorded_by_security_id')->nullable();
                $t->string('status', 20)->nullable();
                $t->dateTime('reviewed_at')->nullable();
                $t->text('review_remarks')->nullable();
                $t->timestamps();
            },
            'violation_category_tbl' => function (Blueprint $t): void {
                $t->increments('violation_category_id');
                $t->string('category_name')->nullable();
            },
            'violation_type_tbl' => function (Blueprint $t): void {
                $t->increments('violation_type_id');
                $t->string('violation_type')->nullable();
                $t->unsignedBigInteger('violation_category_id')->nullable();
                $t->string('severity_level')->nullable();
            },
            'notifications_tbl' => function (Blueprint $t): void {
                $t->increments('notification_id');
                $t->string('student_number')->nullable();
                $t->string('recipient_type')->nullable();
                $t->unsignedBigInteger('recipient_id')->nullable();
                $t->string('notification_type')->nullable();
                $t->string('title')->nullable();
                $t->text('message')->nullable();
                $t->string('target_type')->nullable();
                $t->unsignedBigInteger('target_id')->nullable();
                $t->boolean('is_read')->default(false);
                $t->timestamp('created_at')->nullable();
            },
            'admin_notifications_tbl' => function (Blueprint $t): void {
                $t->increments('admin_notification_id');
                $t->text('message')->nullable();
                $t->string('title')->nullable();
                $t->string('notification_type')->nullable();
                $t->string('priority', 16)->nullable();
                $t->string('target_type')->nullable();
                $t->unsignedBigInteger('target_id')->nullable();
                $t->string('recipient_type')->nullable();
                $t->unsignedBigInteger('recipient_id')->nullable();
                $t->boolean('is_read')->default(false);
                $t->dateTime('created_at')->nullable();
            },
        ] as $name => $definition) {
            if (! Schema::hasTable($name)) {
                Schema::create($name, $definition);
            }
        }

        // Seed the roles the application recognises.
        DB::table('roles_tbl')->insertOrIgnore([
            ['roles_id' => 1, 'roles_name' => 'Admin'],
            ['roles_id' => 2, 'roles_name' => 'IT Administrator'],
        ]);

        // Apply the real migrations so the role exists exactly as in
        // production: the historical creation, then the rename that made
        // the live role name 'OSS Staff'.
        $migration = require base_path('database/migrations/2026_09_16_000004_add_admin_assistant_role.php');
        $migration->up();

        $rename = require base_path('database/migrations/2026_09_17_042228_rename_admin_assistant_role_to_oss_staff.php');
        $rename->up();
    }
}
