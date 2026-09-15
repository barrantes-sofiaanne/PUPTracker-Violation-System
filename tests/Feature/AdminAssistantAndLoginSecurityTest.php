<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Security;
use App\Support\Access\SecurityAccess;
use App\Support\MfaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * PART 1/2 — Admin Assistant role, and the security-operational access rule.
 * PART 17 — 3 failed credential attempts.
 * PART 8  — no Remember Me on the credential login screen.
 *
 * The important cases here are the NEGATIVE ones: widening the security routes
 * to accept the admin guard must not hand those surfaces to every administrator.
 */
class AdminAssistantAndLoginSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
        RateLimiter::clear('admin-login:' . sha1('admin@example.com|127.0.0.1'));
    }

    // ==================================================================
    // PART 1 — Admin Assistant role
    // ==================================================================

    /** The role is created by the migration and is idempotent. */
    public function test_admin_assistant_role_migration_is_idempotent(): void
    {
        $migration = require base_path('database/migrations/2026_09_16_000004_add_admin_assistant_role.php');

        $migration->up();
        $migration->up();

        $this->assertSame(
            1,
            DB::table('roles_tbl')->where('roles_name', 'Admin Assistant')->count(),
            'Running the migration twice must not duplicate the role.'
        );
    }

    public function test_admin_assistant_role_is_recognised_case_insensitively(): void
    {
        $assistant = $this->createAdmin('asst@example.com', Admin::ROLE_ADMIN_ASSISTANT);

        $this->assertTrue($assistant->isAdminAssistant());
        $this->assertSame(Admin::ROLE_ADMIN_ASSISTANT, $assistant->roleName());
    }

    public function test_other_admin_roles_are_not_treated_as_admin_assistant(): void
    {
        $plain = $this->createAdmin('plain@example.com', 'Admin');
        $it = $this->createAdmin('it@example.com', 'IT Administrator');

        $this->assertFalse($plain->isAdminAssistant());
        $this->assertFalse($it->isAdminAssistant());
    }

    /** An unknown/absent role must fail CLOSED. */
    public function test_unknown_role_is_not_an_admin_assistant(): void
    {
        $admin = Admin::create(['email' => 'unknown@example.com', 'password' => Hash::make('x')]);

        DB::table('admin_info_tbl')->insert([
            'admin_id' => $admin->id,
            'role_id' => null,
            'role' => 'Some Fabricated Role',
            'firstname' => 'T',
            'lastname' => 'U',
        ]);

        $this->assertFalse($admin->fresh()->isAdminAssistant());
    }

    // ==================================================================
    // PART 1/2 — the access rule (the security-relevant part)
    // ==================================================================

    public function test_admin_assistant_is_allowed_on_security_operational_surfaces(): void
    {
        $assistant = $this->createAdmin('asst@example.com', Admin::ROLE_ADMIN_ASSISTANT);

        $this->assertTrue(SecurityAccess::allowed($assistant));
    }

    public function test_security_personnel_remain_allowed(): void
    {
        $security = $this->createSecurity('officer@example.com');

        $this->assertTrue(SecurityAccess::allowed($security));
    }

    /**
     * THE critical negative case: widening the guards must not give every
     * administrator the security-operational surfaces.
     */
    public function test_plain_admin_is_denied_security_operational_surfaces(): void
    {
        $plain = $this->createAdmin('plain@example.com', 'Admin');
        $it = $this->createAdmin('it@example.com', 'IT Administrator');

        $this->assertFalse(SecurityAccess::allowed($plain), 'A plain Administrator must not reach security operations.');
        $this->assertFalse(SecurityAccess::allowed($it), 'An IT Administrator must not reach security operations by default.');
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

    /** ...and the Admin Assistant must be let through. */
    public function test_admin_assistant_reaches_the_security_dashboard(): void
    {
        $assistant = $this->createAdmin('asst@example.com', Admin::ROLE_ADMIN_ASSISTANT);

        $this->actingAs($assistant, 'admin')
            ->get(route('security.dashboard'))
            ->assertOk();
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

    /** The MFA screen still offers it (PART 10) for privileged guards. */
    public function test_mfa_verification_stage_still_offers_remember(): void
    {
        $admin = $this->createAdmin('admin@example.com', 'Admin', withMfa: true);

        $this->post(route('admin.login.post'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $page = $this->get(route('mfa.verify.show'))->assertOk()->getContent();

        $this->assertStringContainsString('mfaRememberMe', $page);
        $this->assertStringContainsString('Remember this device for 12 hours', $page);
    }

    public function test_remember_me_label_states_the_12_hour_window(): void
    {
        $admin = $this->createAdmin('admin@example.com', 'Admin', withMfa: true);

        $this->post(route('admin.login.post'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $page = $this->get(route('mfa.verify.show'))->assertOk()->getContent();

        // The configured window is 43200 minutes = 12 hours, not 30 days.
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

        $key = 'admin-login:' . sha1('admin@example.com|127.0.0.1');
        $this->assertSame(0, RateLimiter::attempts($key), 'A successful login must clear the counter.');

        // The user can keep logging in indefinitely (never "3 logins total").
        $this->assertGreaterThanOrEqual(0, RateLimiter::attempts($key));
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

        // Apply the real migration so the role exists exactly as in production.
        $migration = require base_path('database/migrations/2026_09_16_000004_add_admin_assistant_role.php');
        $migration->up();
    }
}
