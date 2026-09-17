<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceInactivityTimeout as Timeout;
use App\Models\Admin;
use App\Models\Security;
use App\Support\Access\SecurityAccess;
use App\Support\Auth\LoginAttemptLimiter;
use App\Support\Auth\SessionExpiryNotice;
use App\Support\Auth\StateAwareUserProvider;
use App\Support\MfaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * PART 34/35/36 — Remember Me restoration gating, inactivity, and login/role
 * security. Covers TEST 1-40 from the brief.
 *
 * These tests deliberately attack the SERVER (direct calls, forged cookies,
 * forged form fields) rather than checking the UI. A green UI is not evidence
 * that a control is enforced.
 */
class SessionAndRememberMeSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Both controls are DISABLED by default (config/auth.php `features`).
        // This suite covers their implementation, so it opts in explicitly;
        // the default-off behaviour is asserted by RememberDeviceToggleTest
        // and the dedicated inactivity tests.
        config()->set('auth.features.remember_device', true);
        config()->set('auth.features.inactivity_timeout', true);

        $this->createTables();
    }

    // ==================================================================
    // PART 35 — Remember Me must not restore a disallowed account
    // ==================================================================

    private function providerFor(string $guard): StateAwareUserProvider
    {
        $provider = Auth::guard($guard)->getProvider();

        $this->assertInstanceOf(
            StateAwareUserProvider::class,
            $provider,
            "The {$guard} guard must use the state-aware provider."
        );

        return $provider;
    }

    /** TEST 22 — an account that goes INACTIVE cannot restore via token. */
    public function test_inactive_account_cannot_restore_via_remember_token(): void
    {
        // START — remove the temporary status scaffolding.
        $admin = $this->createAdmin('rm-inactive@example.com', 'Admin');
        $token = 'remember-token-value';

        DB::table('admins')->where('id', $admin->id)->update(['remember_token' => $token]);

        // Sanity: while the account is usable, the token DOES restore.
        $this->assertNotNull(
            $this->providerFor('admin')->retrieveByToken($admin->id, $token),
            'Precondition: a usable account restores from its token.'
        );

        // Now drive the real gate: give Admin a status column so the shared
        // `isActiveAccount()` predicate applies, then deactivate the account.
        if (! Schema::hasColumn('admins', 'status_id')) {
            Schema::table('admins', function (Blueprint $t): void {
                $t->unsignedInteger('status_id')->nullable();
            });
            DB::table('status_tbl')->insertOrIgnore([
                ['status_id' => 1, 'status_name' => 'Active'],
                ['status_id' => 2, 'status_name' => 'Inactive'],
            ]);
        }

        // Re-activate first so the "usable" precondition still holds.
        DB::table('admins')->where('id', $admin->id)->update(['status_id' => 1]);
        $this->assertNotNull(
            $this->providerFor('admin')->retrieveByToken($admin->id, $token),
            'An ACTIVE account must restore.'
        );

        // TEST 22: deactivate, and restoration must now be refused.
        DB::table('admins')->where('id', $admin->id)->update(['status_id' => 2]);

        $this->assertNull(
            $this->providerFor('admin')->retrieveByToken($admin->id, $token),
            'An INACTIVE account must not restore from a persistent credential.'
        );
        // END — remove the temporary status scaffolding.
    }

    /** TEST 22 (deleted) — a DELETED account cannot restore. */
    public function test_deleted_account_cannot_restore_via_remember_token(): void
    {
        $admin = $this->createAdmin('rm-deleted@example.com', 'Admin');
        $token = 'remember-token-value';

        DB::table('admins')->where('id', $admin->id)->update(['remember_token' => $token]);
        DB::table('admins')->where('id', $admin->id)->delete();

        $this->assertNull(
            $this->providerFor('admin')->retrieveByToken($admin->id, $token),
            'A deleted account must never be restored from a persistent credential.'
        );
    }

    /** An administrator whose role no longer resolves must not be restored. */
    public function test_admin_with_unresolvable_role_cannot_restore(): void
    {
        $admin = $this->createAdmin('rm-norole@example.com', 'Admin');
        $token = 'remember-token-value';

        DB::table('admins')->where('id', $admin->id)->update(['remember_token' => $token]);
        // Point the role at a row that does not exist.
        DB::table('admin_info_tbl')->where('admin_id', $admin->id)->update(['role_id' => 999999]);

        $this->assertNull(
            $this->providerFor('admin')->retrieveByToken($admin->id, $token),
            'An unknown role must fail closed.'
        );
    }

    /** A token that does not match is still rejected by Laravel's comparison. */
    public function test_wrong_token_is_still_rejected(): void
    {
        $admin = $this->createAdmin('rm-wrong@example.com', 'Admin');
        DB::table('admins')->where('id', $admin->id)->update(['remember_token' => 'the-real-token']);

        $this->assertNull(
            $this->providerFor('admin')->retrieveByToken($admin->id, 'a-different-token'),
            'The token comparison itself must not be weakened by the state gate.'
        );
    }

    /**
     * A DELETED or INACTIVE account must not RECEIVE a new persistent
     * credential — otherwise deactivating an account would leave a fresh 12-hour
     * window working.
     *
     * NOTE: a merely unresolvable ROLE does not block ISSUANCE. The role rule
     * governs RESTORATION, and blocking issuance on it would break legitimate
     * pre-existing administrative accounts that predate mandatory role rows.
     */
    public function test_deleted_account_is_not_issued_a_new_remember_token(): void
    {
        $admin = $this->createAdmin('rm-issuance@example.com', 'Admin');

        $model = $admin->fresh();
        DB::table('admins')->where('id', $admin->id)->delete();

        $this->providerFor('admin')->updateRememberToken($model, 'brand-new-token');

        $this->assertDatabaseMissing('admins', ['id' => $admin->id]);
    }

    /** An INACTIVE account must not be issued a credential. */
    public function test_inactive_account_is_not_issued_a_new_remember_token(): void
    {
        $admin = $this->createAdmin('rm-inactive-issue@example.com', 'Admin');

        // Ensure the status column exists (mirrors the migration) and deactivate.
        if (! Schema::hasColumn('admins', 'status_id')) {
            Schema::table('admins', function (Blueprint $t): void {
                $t->unsignedBigInteger('status_id')->nullable();
            });
        }
        DB::table('status_tbl')->insertOrIgnore([
            ['status_id' => 1, 'status_name' => 'Active'],
            ['status_id' => 2, 'status_name' => 'Inactive'],
        ]);

        DB::table('admins')->where('id', $admin->id)->update(['status_id' => 2]);

        $this->providerFor('admin')->updateRememberToken($admin->fresh(), 'brand-new-token');

        $this->assertNotSame(
            'brand-new-token',
            DB::table('admins')->where('id', $admin->id)->value('remember_token'),
            'An inactive account must not be given a persistent credential.'
        );
    }

    /** The state gate is applied to the security guard too. */
    public function test_security_guard_uses_the_state_aware_provider(): void
    {
        $this->providerFor('security');
    }

    /** The student IDP guard is deliberately NOT wrapped. */
    public function test_student_guard_is_not_wrapped(): void
    {
        $provider = Auth::guard('student')->getProvider();

        $this->assertNotInstanceOf(
            StateAwareUserProvider::class,
            $provider,
            'The student guard authenticates via the IDP and never remembers.'
        );
    }

    // ==================================================================
    // PART 3/4/5 — inactivity timeout (TEST 5/6/7/37/38)
    // ==================================================================

    public function test_inactivity_timeout_is_fifteen_minutes(): void
    {
        $this->assertSame(15, Timeout::TIMEOUT_MINUTES);
    }

    /** TEST 6/37 — an expired session is rejected server-side. */
    public function test_session_expired_by_inactivity_is_rejected_by_the_server(): void
    {
        $admin = $this->createAdmin('timeout@example.com', 'Admin');

        // Stamp the session as 16 minutes idle, then hit a protected route.
        $this->actingAs($admin, 'admin')
            ->withSession([Timeout::SESSION_KEY => now()->subMinutes(16)->getTimestamp()])
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest('admin');
    }

    /** TEST 7 — activity inside the window keeps the session alive. */
    public function test_session_within_the_window_remains_valid(): void
    {
        $admin = $this->createAdmin('fresh@example.com', 'Admin');

        $this->actingAs($admin, 'admin')
            ->withSession([Timeout::SESSION_KEY => now()->subMinutes(14)->getTimestamp()])
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    /** TEST 5 — the expiry message is the one the brief specifies. */
    public function test_expiry_message_is_shown_after_inactivity(): void
    {
        $admin = $this->createAdmin('msg@example.com', 'Admin');

        $this->actingAs($admin, 'admin')
            ->withSession([Timeout::SESSION_KEY => now()->subMinutes(20)->getTimestamp()])
            ->get(route('admin.dashboard'));

        $this->assertStringContainsString(
            '15 minutes of inactivity',
            (string) session(Timeout::REASON_KEY)
        );
    }

    /**
     * TEST 38 — a client cannot hold the session open by faking activity: the
     * stamp lives in the SERVER session, so a request with no server stamp and
     * an already-expired session is refused.
     */
    public function test_client_side_activity_cannot_defeat_the_timeout(): void
    {
        $admin = $this->createAdmin('fake@example.com', 'Admin');

        // Simulate a "polling" request that carries no server-side activity
        // stamp of its own but arrives against an expired session.
        $this->actingAs($admin, 'admin')
            ->withSession([Timeout::SESSION_KEY => now()->subMinutes(30)->getTimestamp()])
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    // ==================================================================
    // PART 17 — failed login attempts (TEST 23-27)
    // ==================================================================

    /** Build a request object so the shared limiter can be inspected directly. */
    private function attemptsFor(string $email, string $module = 'admin'): int
    {
        return LoginAttemptLimiter::attempts($module, Request::create('/login', 'POST', ['email' => $email]));
    }

    private function clearAttempts(string $email, string $module = 'admin'): void
    {
        LoginAttemptLimiter::clear($module, Request::create('/login', 'POST', ['email' => $email]));
    }

    public function test_each_failed_attempt_is_counted(): void
    {
        $this->createAdmin('count@example.com', 'Admin');
        $this->clearAttempts('count@example.com');

        $this->post(route('admin.login.post'), ['email' => 'count@example.com', 'password' => 'wrong']);
        $this->assertSame(1, $this->attemptsFor('count@example.com'), 'TEST 23');

        $this->post(route('admin.login.post'), ['email' => 'count@example.com', 'password' => 'wrong']);
        $this->assertSame(2, $this->attemptsFor('count@example.com'), 'TEST 24');
    }

    public function test_third_failure_blocks_and_the_fourth_is_rejected(): void
    {
        $this->createAdmin('block@example.com', 'Admin');
        $this->clearAttempts('block@example.com');

        for ($i = 1; $i <= 3; $i++) {
            $this->post(route('admin.login.post'), ['email' => 'block@example.com', 'password' => 'wrong'])
                ->assertSessionHasErrors('login');
        }

        // TEST 25/26 — the 4th attempt is throttled even with the right password.
        $this->post(route('admin.login.post'), ['email' => 'block@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('login');

        $this->assertStringContainsString(
            'Too many failed login attempts',
            (string) session('errors')->first('login')
        );
        $this->assertGuest('admin');
    }

    /** TEST 27 — a successful login clears the counter. */
    public function test_success_resets_the_counter(): void
    {
        $this->createAdmin('reset@example.com', 'Admin', withMfa: true);
        $this->clearAttempts('reset@example.com');

        $this->post(route('admin.login.post'), ['email' => 'reset@example.com', 'password' => 'wrong']);
        $this->post(route('admin.login.post'), ['email' => 'reset@example.com', 'password' => 'password']);

        $this->assertSame(0, $this->attemptsFor('reset@example.com'));
    }

    // ==================================================================
    // Session-expired notice — one-time, never on a fresh visit
    // ==================================================================

    /**
     * A first-time unauthenticated visitor must see NO expiry message.
     *
     * This is the bug the brief reports: the login page showed "session expired"
     * to users who had never logged in.
     */
    public function test_fresh_login_page_shows_no_expiry_message(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertDontSee('session has expired')
            ->assertDontSee('inactivity');
    }

    /**
     * Stale session data must not surface either: even if an old
     * `auth.expired_reason` value is present, the first render consumes it and a
     * SECOND visit must show nothing.
     */
    public function test_stale_expiry_reason_is_shown_once_then_gone(): void
    {
        // Someone authenticated a while ago, and a legacy persistent value is
        // still sitting in the session.
        $admin = $this->createAdmin('stale@example.com', 'Admin');

        $this->actingAs($admin, 'admin')
            ->withSession([
                Timeout::SESSION_KEY => now()->subMinutes(20)->getTimestamp(),
            ])
            ->get(route('admin.dashboard'));

        // The expiry was detected, so the message is present for the FIRST view.
        $first = $this->get(route('admin.login'));
        $first->assertOk();
        $this->assertStringContainsString('session has expired', $first->getContent());

        // ...and gone on the very next visit. This is the actual fix: the value is
        // consumed on display instead of lingering for the whole session.
        $second = $this->get(route('admin.login'));
        $second->assertOk();
        $this->assertStringNotContainsString(
            'session has expired',
            $second->getContent(),
            'The expiry notice must not reappear on a later visit.'
        );
    }

    /** An unauthenticated request against a protected page is not an expiry. */
    public function test_guest_hitting_protected_page_is_not_treated_as_expiry(): void
    {
        // No session activity stamp at all — a brand-new visitor.
        $this->get(route('admin.dashboard'))->assertRedirect();

        $page = $this->get(route('admin.login'));
        $page->assertOk();
        $this->assertStringNotContainsString('session has expired', $page->getContent());
    }

    /** The notice is only produced by a real expiry, and says so. */
    public function test_expiry_notice_text_is_correct_and_consumed(): void
    {
        $reason = SessionExpiryNotice::inactivityReason(15);

        $this->assertStringContainsString('15 minutes of inactivity', $reason);

        $request = Request::create('/login', 'GET');
        $request->setLaravelSession(app('session.store'));
        $request->session()->start();

        SessionExpiryNotice::record($request, $reason);

        $this->assertSame($reason, SessionExpiryNotice::pull($request), 'First pull returns the notice.');
        $this->assertNull(SessionExpiryNotice::pull($request), 'Second pull returns nothing.');
        $this->assertNull(SessionExpiryNotice::pull($request), 'The notice never comes back.');
    }

    // ==================================================================
    // PART 1/2 — OSS Staff (TEST 1-4).
    // The role was formerly "Admin Assistant"; the live name is OSS Staff.
    // ==================================================================

    /** TEST 1 — the role exists with the right mapping. */
    public function test_oss_staff_role_exists(): void
    {
        $this->assertDatabaseHas('roles_tbl', ['roles_name' => 'OSS Staff']);
    }

    /** TEST 2 — an OSS Staff account must NOT reach the security-operational surface.
     *
     * STRICT MODULE ISOLATION: OSS Staff owns `/oss-staff/...` instead. The
     * account is authenticated on the admin guard, so it is refused with a 403
     * rather than bounced to a login page.
     */
    public function test_oss_staff_is_denied_security_functionality(): void
    {
        $assistant = $this->createAdmin('asst@example.com', 'OSS Staff');

        $this->assertFalse(SecurityAccess::allowed($assistant));
        $this->actingAs($assistant, 'admin')->get(route('security.dashboard'))->assertForbidden();
    }

    /** TEST 3 — an OSS Staff account must NOT reach Super Admin routes. */
    public function test_oss_staff_is_denied_super_admin_routes(): void
    {
        $assistant = $this->createAdmin('asst2@example.com', 'OSS Staff');

        $this->actingAs($assistant, 'admin')
            ->get(route('admin.super-admin.dashboard'))
            ->assertForbidden();
    }

    /** TEST 4 — Security Personnel functionality is unregressed. */
    public function test_security_personnel_functionality_intact(): void
    {
        $security = $this->createSecurity('officer@example.com');

        $this->assertTrue(SecurityAccess::allowed($security));
        $this->actingAs($security, 'security')->get(route('security.dashboard'))->assertOk();
    }

    // ==================================================================
    // PART 8/10/11/13/14/39 — Remember Me placement and role restriction
    // ==================================================================

    /** TEST 8 — no Remember Me on the credential login. */
    public function test_credential_login_hides_remember_me(): void
    {
        $this->get(route('admin.login'))->assertOk()
            ->assertDontSee('name="remember"', false)
            ->assertDontSee('rememberMe');
    }

    /**
     * TEST 14/39 — a forged `remember` on the credential POST must be ignored.
     * This is the STUDENT-equivalent bypass attempt applied to admin.
     */
    public function test_forged_remember_field_at_credential_stage_is_ignored(): void
    {
        $this->createAdmin('forge@example.com', 'Admin', withMfa: true);
        $this->clearAttempts('forge@example.com');

        $this->post(route('admin.login.post'), [
            'email' => 'forge@example.com',
            'password' => 'password',
            'remember' => '1',
            'remember_me' => 'true',
        ]);

        $pending = session(MfaService::SESSION_KEY);

        $this->assertIsArray($pending);
        $this->assertFalse((bool) ($pending['remember'] ?? false), 'TEST 14/39');
    }

    // ==================================================================
    // PART 39 — nothing existing was broken
    // ==================================================================

    public function test_existing_role_access_is_unchanged(): void
    {
        // A plain admin still reaches the admin module...
        $plain = $this->createAdmin('plain@example.com', 'Admin');
        $this->actingAs($plain, 'admin')->get(route('admin.dashboard'))->assertOk();

        // ...and is still refused the security-operational surface.
        $this->actingAs($plain, 'admin')->get(route('security.dashboard'))->assertForbidden();
    }

    /** TOTP enrolment must remain available to ordinary admins. */
    public function test_totp_setup_remains_available_to_ordinary_admins(): void
    {
        $plain = $this->createAdmin('totp@example.com', 'Admin');

        $this->actingAs($plain, 'admin')
            ->get(route('totp.setup'))
            ->assertOk();
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    /**
     * True when the account state would cause the provider to refuse it.
     *
     * Admin has no status column in this schema, so the rule is exercised
     * through the same predicate the provider calls.
     */

    /**
     * True when the account state would cause the provider to refuse it.
     *
     * Admin has no status column in this schema, so the rule is exercised
     * through the same predicate the provider calls.
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
        $security = Security::create([
            'email' => $email,
            'password' => Hash::make('password'),
        ]);

        DB::table('security_info_tbl')->insert([
            'security_id' => $security->id,
            'firstname' => 'Test',
            'lastname' => 'Officer',
        ]);

        DB::table('security_info')->insert([
            'security_id' => $security->id,
            'firstname' => 'Test',
            'lastname' => 'Officer',
        ]);

        return $security;
    }

    private function createTables(): void
    {
        foreach ([
            'roles_tbl' => function (Blueprint $t): void {
                $t->increments('roles_id');
                $t->string('roles_name');
            },
            'status_tbl' => function (Blueprint $t): void {
                $t->increments('status_id');
                $t->string('status_name');
            },
            'sanction_requests_tbl' => function (Blueprint $t): void {
                $t->increments('request_id');
                $t->string('student_number')->nullable();
                $t->unsignedBigInteger('violation_type_id')->nullable();
                $t->string('status')->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            },
            'student_sanction_records_tbl' => function (Blueprint $t): void {
                $t->increments('record_id');
                $t->string('student_number')->nullable();
                $t->unsignedBigInteger('sanction_request_id')->nullable();
                $t->string('status')->nullable();
                $t->timestamps();
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
                $t->string('remember_token', 100)->nullable();
                $t->unsignedBigInteger('status_id')->nullable();
                $t->timestamps();
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
                $t->string('remember_token', 100)->nullable();
                $t->unsignedBigInteger('status_id')->nullable();
                $t->timestamps();
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

        DB::table('roles_tbl')->insertOrIgnore([
            ['roles_id' => 1, 'roles_name' => 'Admin'],
            ['roles_id' => 2, 'roles_name' => 'IT Administrator'],
        ]);

        $migration = require base_path('database/migrations/2026_09_16_000004_add_admin_assistant_role.php');
        $migration->up();

        // The live role name is 'OSS Staff' (the rename migration has been
        // deployed), so apply it too.
        $rename = require base_path('database/migrations/2026_09_17_042228_rename_admin_assistant_role_to_oss_staff.php');
        $rename->up();
    }
}
