<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceInactivityTimeout as Timeout;
use App\Models\Admin;
use App\Models\Security;
use App\Support\Auth\LoginAttemptLimiter;
use App\Support\Auth\PortalLoginRedirect;
use App\Support\MfaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The SHARED Security + OSS Staff login portal (`/security/login`).
 *
 * ONE page serves BOTH account types, which remain separate models on separate
 * guards:
 *
 *   Security Personnel -> Security model -> `security` guard -> Security module
 *   OSS Staff          -> Admin model    -> `admin`    guard -> OSS Staff module
 *
 * The critical cases are the BOUNDARIES:
 *   - both account types can sign in through this page and reach their OWN MFA
 *   - a regular Admin / IT Administrator is REFUSED here (their accounts are not
 *     usable through this portal), and is NOT granted /oss-staff/*
 *   - the shared page never bypasses MFA
 *   - each guard logs out independently
 */
class SharedSecurityOssStaffLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
        $this->seedRoles();
        $this->clearLoginAttempts();
    }

    private function clearLoginAttempts(string $email = 'user@example.com'): void
    {
        foreach (['admin', 'security'] as $module) {
            LoginAttemptLimiter::clear($module, Request::create('/security/login', 'POST', ['email' => $email]));
        }
    }

    // ==================================================================
    // The shared page
    // ==================================================================

    /** ONE login page, and it names BOTH account types. */
    public function test_shared_login_page_is_branded_for_both_account_types(): void
    {
        $page = $this->get(route('security.login'))->assertOk()->getContent();

        $this->assertStringContainsString('Security and OSS Staff Portal', $page);
        $this->assertStringContainsString('Security Personnel', $page);
        $this->assertStringContainsString('OSS Staff', $page);

        // It must not present itself as the Administrator portal.
        $this->assertStringNotContainsString('Administrator Login', $page);
        $this->assertStringNotContainsString('Admin Portal', $page);
        $this->assertStringNotContainsString('Admin Assistant', $page);
    }

    /** The existing route names still work (no /oss-staff/login was created). */
    public function test_existing_security_login_routes_are_unchanged(): void
    {
        $this->assertSame(url('/security/login'), route('security.login'));
        $this->assertSame(url('/security/login'), route('security.login.post'));
        $this->assertSame(url('/security/logout'), route('security.logout'));

        // There is deliberately NO separate OSS Staff login.
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('oss-staff.login'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('oss-staff.login.post'));

        foreach (\Illuminate\Support\Facades\Route::getRoutes() as $route) {
            $this->assertStringNotContainsString(
                'oss-staff/login',
                (string) $route->uri(),
                'No separate OSS Staff credential-login route may exist.'
            );
        }
    }

    // ==================================================================
    // Security accounts
    // ==================================================================

    public function test_security_account_signs_in_through_the_shared_page_and_gets_security_mfa(): void
    {
        $this->createSecurity('officer@example.com');

        $this->post(route('security.login.post'), [
            'email' => 'officer@example.com',
            'password' => 'password',
        ])->assertRedirect(route('mfa.verify.show'));

        $pending = session(MfaService::SESSION_KEY);

        $this->assertIsArray($pending);
        $this->assertSame('security', $pending['guard'], 'A Security account must use the security guard.');
    }

    public function test_wrong_security_password_is_rejected(): void
    {
        $this->createSecurity('officer@example.com');

        $this->post(route('security.login.post'), [
            'email' => 'officer@example.com',
            'password' => 'wrong',
        ])->assertSessionHasErrors('login');

        $this->assertGuest('security');
    }

    // ==================================================================
    // OSS Staff accounts
    // ==================================================================

    public function test_oss_staff_signs_in_through_the_shared_page_and_gets_admin_mfa(): void
    {
        $ossStaff = $this->createAdmin('oss@example.com', 'OSS Staff');

        $this->post(route('security.login.post'), [
            'email' => 'oss@example.com',
            'password' => 'password',
        ])->assertRedirect(route('mfa.verify.show'));

        $pending = session(MfaService::SESSION_KEY);

        $this->assertIsArray($pending, 'OSS Staff must start the admin MFA challenge.');

        // The ADMIN guard + the admin account id: OSS Staff is NOT a Security
        // account and must never be authenticated as one.
        $this->assertSame('admin', $pending['guard']);
        $this->assertSame((string) $ossStaff->id, (string) $pending['identifier']);
    }

    /** The shared portal must NOT authenticate an OSS Staff account immediately. */
    public function test_shared_portal_does_not_bypass_mfa(): void
    {
        $this->createAdmin('oss@example.com', 'OSS Staff');

        $this->post(route('security.login.post'), [
            'email' => 'oss@example.com',
            'password' => 'password',
        ]);

        $this->assertGuest('admin');
        $this->assertGuest('security');
    }

    // ==================================================================
    // Regular Admin / IT Administrator are NOT accepted here
    // ==================================================================

    public function test_regular_admin_cannot_use_the_shared_portal(): void
    {
        $this->createAdmin('plain@example.com', 'Admin');

        $this->post(route('security.login.post'), [
            'email' => 'plain@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('login');

        $this->assertGuest('admin');
        $this->assertNull(session(MfaService::SESSION_KEY), 'A regular Admin must not start a challenge here.');
    }

    public function test_it_administrator_cannot_use_the_shared_portal(): void
    {
        $this->createAdmin('it@example.com', 'IT Administrator');

        $this->post(route('security.login.post'), [
            'email' => 'it@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('login');

        $this->assertGuest('admin');
        $this->assertNull(session(MfaService::SESSION_KEY));
    }

    /** A refused account must not be told WHY (no account enumeration). */
    public function test_refusal_message_does_not_reveal_the_account_type(): void
    {
        $this->createAdmin('plain@example.com', 'Admin');

        $this->post(route('security.login.post'), [
            'email' => 'plain@example.com',
            'password' => 'password',
        ]);

        $message = (string) session('errors')->first('login');

        $this->assertStringContainsString('Invalid email or password', $message);
        $this->assertStringNotContainsString('OSS Staff', $message);
        $this->assertStringNotContainsString('Administrator', $message);
        $this->assertStringNotContainsString('role', $message);
    }

    // ==================================================================
    // Lockout behaviour
    // ==================================================================

    /** The existing shared-portal lockout still caps failed attempts. */
    public function test_shared_portal_still_locks_out_after_repeated_failures(): void
    {
        $this->createSecurity('officer@example.com');

        for ($i = 1; $i <= 3; $i++) {
            $this->post(route('security.login.post'), [
                'email' => 'officer@example.com',
                'password' => 'wrong',
            ])->assertSessionHasErrors('login');
        }

        $this->post(route('security.login.post'), [
            'email' => 'officer@example.com',
            'password' => 'password',
        ]);

        $this->assertStringContainsString(
            'Too many failed login attempts',
            (string) session('errors')->first('login')
        );
    }

    /** A portal lockout must not block the Administrator login. */
    public function test_portal_lockout_does_not_block_the_administrator_login(): void
    {
        $this->createAdmin('plain@example.com', 'Admin', withMfa: false);

        for ($i = 1; $i <= 4; $i++) {
            $this->post(route('security.login.post'), [
                'email' => 'plain@example.com',
                'password' => 'wrong',
            ]);
        }

        // The Administrator login still works for that email.
        $this->post(route('admin.login.post'), [
            'email' => 'plain@example.com',
            'password' => 'password',
        ])->assertRedirect(route('mfa.verify.show'));
    }

    /**
     * An OSS Staff credential failure counts in BOTH ledgers.
     *
     * Their credentials ARE Administrator credentials, so a failure here is a
     * failure there too â€” otherwise this portal would silently DOUBLE the
     * guesses an attacker gets against an OSS Staff account.
     */
    public function test_oss_staff_failure_counts_against_both_ledgers(): void
    {
        $this->createAdmin('oss@example.com', 'OSS Staff');

        $request = Request::create('/security/login', 'POST', ['email' => 'oss@example.com']);

        $this->post(route('security.login.post'), [
            'email' => 'oss@example.com',
            'password' => 'wrong',
        ])->assertSessionHasErrors('login');

        $this->assertSame(1, LoginAttemptLimiter::attempts('security', $request));
        $this->assertSame(1, LoginAttemptLimiter::attempts('admin', $request));
    }

    /**
     * A NON-OSS-Staff rejection must NOT lock the Administrator login.
     *
     * A regular Administrator attempting this portal cannot succeed here, so it
     * would be wrong to burn their Administrator budget for it.
     */
    public function test_non_oss_staff_rejection_does_not_lock_the_admin_login(): void
    {
        $this->createAdmin('plain@example.com', 'Admin');

        $request = Request::create('/security/login', 'POST', ['email' => 'plain@example.com']);

        $this->post(route('security.login.post'), [
            'email' => 'plain@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('login');

        $this->assertSame(1, LoginAttemptLimiter::attempts('security', $request));
        $this->assertSame(0, LoginAttemptLimiter::attempts('admin', $request));

        // ...and the Administrator login still works.
        $this->post(route('admin.login.post'), [
            'email' => 'plain@example.com',
            'password' => 'password',
        ])->assertRedirect(route('mfa.verify.show'));
    }

    /** A successful OSS Staff sign-in clears both ledgers. */
    public function test_oss_staff_success_clears_both_ledgers(): void
    {
        $this->createAdmin('oss@example.com', 'OSS Staff');

        $this->post(route('admin.login.post'), [
            'email' => 'oss@example.com',
            'password' => 'wrong',
        ]);

        $request = Request::create('/security/login', 'POST', ['email' => 'oss@example.com']);
        $this->assertSame(1, LoginAttemptLimiter::attempts('admin', $request));

        $this->post(route('security.login.post'), [
            'email' => 'oss@example.com',
            'password' => 'password',
        ])->assertRedirect(route('mfa.verify.show'));

        $this->assertSame(0, LoginAttemptLimiter::attempts('security', $request));
        $this->assertSame(0, LoginAttemptLimiter::attempts('admin', $request));
    }

    // ==================================================================
    // Redirects + logout stay on their own guard
    // ==================================================================

    /** An OSS Staff member who cancels MFA returns to the SHARED portal. */
    public function test_oss_staff_mfa_cancel_returns_to_the_shared_portal(): void
    {
        $this->createAdmin('oss@example.com', 'OSS Staff');

        $this->post(route('security.login.post'), [
            'email' => 'oss@example.com',
            'password' => 'password',
        ])->assertRedirect(route('mfa.verify.show'));

        $pending = session(MfaService::SESSION_KEY);

        $this->withSession(['mfa.pending' => $pending])
            ->post(route('mfa.verify.cancel'))
            ->assertRedirect(route('security.login'));
    }

    /** A regular Administrator who cancels MFA returns to the Admin login. */
    public function test_regular_admin_mfa_cancel_returns_to_the_admin_login(): void
    {
        $this->createAdmin('plain@example.com', 'Admin');

        $this->post(route('admin.login.post'), [
            'email' => 'plain@example.com',
            'password' => 'password',
        ])->assertRedirect(route('mfa.verify.show'));

        $pending = session(MfaService::SESSION_KEY);

        $this->withSession(['mfa.pending' => $pending])
            ->post(route('mfa.verify.cancel'))
            ->assertRedirect(route('admin.login'));
    }

    /** The shared-portal logout must not touch the admin guard. */
    public function test_security_logout_does_not_log_out_the_admin_guard(): void
    {
        $security = $this->createSecurity('officer@example.com');

        $this->actingAs($security, 'security')
            ->post(route('security.logout'))
            ->assertRedirect(route('security.login'));

        $this->assertGuest('security');
    }

    /**
     * An OSS Staff member whose session times out returns to the SHARED portal.
     *
     * They signed in there, so bouncing them to the Administrator login (the
     * guard's own page) would be wrong â€” and their account is not usable there.
     */
    public function test_oss_staff_inactivity_redirects_to_the_shared_portal(): void
    {
        config()->set('auth.features.inactivity_timeout', true);

        $ossStaff = $this->createAdmin('oss@example.com', 'OSS Staff');

        $this->actingAs($ossStaff, 'admin')
            ->withSession([
                Timeout::SESSION_KEY => now()->subMinutes(20)->getTimestamp(),
            ])
            ->get(route('oss-staff.dashboard'))
            ->assertRedirect(route('security.login'));

        $this->assertGuest('admin');
    }

    /** A regular Administrator's timeout still returns to the Admin login. */
    public function test_regular_admin_inactivity_still_redirects_to_the_admin_login(): void
    {
        config()->set('auth.features.inactivity_timeout', true);

        $admin = $this->createAdmin('plain@example.com', 'Admin');

        $this->actingAs($admin, 'admin')
            ->withSession([
                Timeout::SESSION_KEY => now()->subMinutes(20)->getTimestamp(),
            ])
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest('admin');
    }

    // ==================================================================
    // End to end: shared page -> admin MFA -> OSS Staff dashboard
    // ==================================================================

    /** The full OSS Staff journey through the shared portal. */
    public function test_oss_staff_full_login_reaches_the_oss_staff_dashboard(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';

        $admin = Admin::create([
            'email' => 'oss@example.com',
            'password' => Hash::make('password'),
            'mfa_totp_secret' => $secret,
            'mfa_totp_enabled' => true,
            // Pre-existing backup codes: the first-MFA interstitial is a separate
            // flow and would otherwise intercept the redirect.
            'mfa_backup_codes' => json_encode([Hash::make('123456')]),
            'mfa_backup_codes_used' => json_encode([]),
        ]);

        DB::table('admin_info_tbl')->insert([
            'admin_id' => $admin->id,
            'role_id' => DB::table('roles_tbl')->where('roles_name', 'OSS Staff')->value('roles_id'),
            'Position' => 'OSS Staff',
            'firstname' => 'Oss',
            'lastname' => 'Staff',
        ]);

        // 1. Sign in on the SHARED page.
        $this->post(route('security.login.post'), [
            'email' => 'oss@example.com',
            'password' => 'password',
        ])->assertRedirect(route('mfa.verify.show'));

        $pending = session(MfaService::SESSION_KEY);
        $this->assertSame('admin', $pending['guard']);

        // 2. Complete the ADMIN MFA challenge.
        $verify = $this->withSession(['mfa.pending' => $pending])
            ->post(route('mfa.verify.submit'), [
                'method' => 'totp',
                'code' => $this->generateCurrentTotp($secret),
            ]);

        // 3. Land in the OSS Staff module, authenticated on the ADMIN guard,
        //    and NOT in the Security module.
        $verify->assertRedirect(route('oss-staff.dashboard'));
        $this->assertAuthenticated('admin');
        $this->assertGuest('security');

        // The destination must not be the Security dashboard.
        $this->assertNotSame(route('security.dashboard'), $verify->headers->get('Location'));
    }

    private function generateCurrentTotp(string $secret): string
    {
        $decodedSecret = $this->base32Decode($secret);
        $counter = (int) floor(time() / 30);
        $counterBytes = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $counterBytes, $decodedSecret, true);
        $offset = ord(substr($hash, -1)) & 0x0f;
        $segment = substr($hash, $offset, 4);
        $value = unpack('N', $segment)[1] & 0x7fffffff;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper(rtrim($secret, '='));
        $bits = '';

        foreach (str_split($secret) as $char) {
            $index = strpos($alphabet, $char);

            if ($index === false) {
                continue;
            }

            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';

        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $binary .= chr(bindec($byte));
            }
        }

        return $binary;
    }

    /** An OSS Staff member logs out through the ADMIN logout. */
    public function test_oss_staff_logout_uses_the_admin_guard(): void
    {
        $ossStaff = $this->createAdmin('oss@example.com', 'OSS Staff');

        $this->actingAs($ossStaff, 'admin')
            ->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest('admin');
    }

    // ==================================================================
    // The login-redirect resolver
    // ==================================================================

    public function test_portal_login_redirect_resolves_per_account_type(): void
    {
        $ossStaff = $this->createAdmin('oss@example.com', 'OSS Staff');
        $plain = $this->createAdmin('plain@example.com', 'Admin');
        $it = $this->createAdmin('it@example.com', 'IT Administrator');
        $security = $this->createSecurity('officer@example.com');

        $this->assertSame('security.login', PortalLoginRedirect::forAdmin($ossStaff));
        $this->assertSame('admin.login', PortalLoginRedirect::forAdmin($plain));
        $this->assertSame('admin.login', PortalLoginRedirect::forAdmin($it));

        $this->assertSame('security.login', PortalLoginRedirect::forGuard('security', $security));
        $this->assertSame('security.login', PortalLoginRedirect::forGuard('admin', $ossStaff));
        $this->assertSame('admin.login', PortalLoginRedirect::forGuard('admin', $plain));

        // Fail closed: an unknown/absent account never enters the shared portal.
        $this->assertSame('admin.login', PortalLoginRedirect::forAdmin(null));
    }

    // ==================================================================
    // Password recovery through the shared portal
    // ==================================================================

    /**
     * An OSS Staff member can request a reset from the shared portal.
     *
     * They are ADMIN accounts, so the token must be filed under the ADMIN guard
     * (that is the account they really are) even though the request came from
     * the Security portal URL.
     */
    public function test_oss_staff_can_request_a_password_reset_from_the_shared_portal(): void
    {
        $ossStaff = $this->createAdmin('oss@example.com', 'OSS Staff');

        \Illuminate\Support\Facades\Mail::fake();

        $this->post(route('password.email', ['guard' => 'security']), [
            'email' => 'oss@example.com',
        ])->assertSessionHas('status');

        $this->assertDatabaseHas('password_resets', [
            'guard' => 'admin',
            'account_id' => $ossStaff->id,
        ]);
    }

    /**
     * A regular Administrator is NOT recoverable through the shared portal.
     *
     * They sign in at `admin.login`, so this portal must not become a way to
     * trigger resets (or confirm existence) for their accounts.
     */
    public function test_regular_admin_cannot_request_a_reset_from_the_shared_portal(): void
    {
        $this->createAdmin('plain@example.com', 'Admin');

        \Illuminate\Support\Facades\Mail::fake();

        // The response stays generic (no enumeration)...
        $this->post(route('password.email', ['guard' => 'security']), [
            'email' => 'plain@example.com',
        ])->assertSessionHas('status');

        // ...but NO reset token is created for them.
        $this->assertDatabaseMissing('password_resets', [
            'account_id' => Admin::where('email', 'plain@example.com')->value('id'),
        ]);
    }

    /** A Security officer still recovers through the shared portal. */
    public function test_security_officer_can_still_request_a_password_reset(): void
    {
        $security = $this->createSecurity('officer@example.com');

        \Illuminate\Support\Facades\Mail::fake();

        $this->post(route('password.email', ['guard' => 'security']), [
            'email' => 'officer@example.com',
        ])->assertSessionHas('status');

        $this->assertDatabaseHas('password_resets', [
            'guard' => 'security',
            'account_id' => $security->id,
        ]);
    }

    /** The shared portal's recovery page names both account types. */
    public function test_shared_portal_recovery_page_is_labelled_for_both_account_types(): void
    {
        $page = $this->get(route('password.request', ['guard' => 'security']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Security and OSS Staff', $page);
    }

    // ==================================================================
    // Helpers
    // ==================================================================

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
            'lastname' => 'User',
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

    private function seedRoles(): void
    {
        DB::table('roles_tbl')->insertOrIgnore([
            ['roles_id' => 1, 'roles_name' => 'Admin'],
            ['roles_id' => 2, 'roles_name' => 'IT Administrator'],
            ['roles_id' => 3, 'roles_name' => 'OSS Staff'],
        ]);
    }

    private function createTables(): void
    {
        $tables = [
            'admins' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('username')->nullable();
                $table->string('email')->unique();
                $table->string('password');
                $table->string('remember_token', 100)->nullable();
                $table->text('mfa_totp_secret')->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
                $table->text('mfa_backup_codes')->nullable();
                $table->text('mfa_backup_codes_used')->nullable();
            },
            'admin_info_tbl' => function (Blueprint $table): void {
                $table->unsignedBigInteger('admin_id')->primary();
                $table->unsignedBigInteger('role_id')->nullable();
                $table->string('firstname')->nullable();
                $table->string('middlename')->nullable();
                $table->string('lastname')->nullable();
                $table->string('Position')->nullable();
                $table->string('designation')->nullable();
                $table->string('role')->nullable();
                $table->string('title')->nullable();
            },
            'security' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('email')->unique();
                $table->string('password');
                $table->string('remember_token', 100)->nullable();
                $table->text('mfa_totp_secret')->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
                $table->text('mfa_backup_codes')->nullable();
                $table->text('mfa_backup_codes_used')->nullable();
            },
            'roles_tbl' => function (Blueprint $table): void {
                $table->increments('roles_id');
                $table->string('roles_name');
            },
            'users_tbl' => function (Blueprint $table): void {
                $table->increments('user_id');
                $table->string('student_number')->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->string('password_hash')->nullable();
                $table->unsignedInteger('roles_id')->nullable();
                $table->unsignedInteger('status_id')->nullable();
            },
            'audit_logs' => function (Blueprint $table): void {
                $table->increments('audit_log_id');
                $table->string('actor_type')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('action');
                $table->string('module')->nullable();
                $table->text('description')->nullable();
                $table->timestamp('created_at')->nullable();
            },
            'password_resets' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('guard')->nullable();
                $table->unsignedBigInteger('account_id')->nullable();
                $table->string('token_hash')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('used_at')->nullable();
                $table->timestamp('created_at')->nullable();
            },
        ];

        foreach ($tables as $name => $definition) {
            if (! Schema::hasTable($name)) {
                Schema::create($name, $definition);
            }
        }
    }
}
