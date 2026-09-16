<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Security;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

/**
 * "Remember me" (12-hour trusted device) for the Administrator & Security
 * login modules.
 *
 * Rules (PART 6-14 of the account/session security brief):
 *   - The checkbox appears ONLY on the selected MFA verification screen
 *     (PART 10). It must NOT appear on the credential login form (PART 8) or
 *     on the MFA method-selection step (PART 9).
 *   - Only when the box is ticked is a remember/recaller cookie issued, and
 *     only AFTER password + MFA have both succeeded (PART 12).
 *   - The cookie expires after 12 hours / 43200 minutes
 *     (per-guard `auth.guards.*.remember`), so it cannot survive overnight
 *     (PART 14/15); after that the user must log in again (password + MFA).
 *   - Student logins never issue a remember cookie (PART 11).
 */
class RememberMeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The Remember-Me feature is DISABLED by default (config/auth.php
        // `features`). This suite tests the implementation itself, so it opts
        // in explicitly — that way the control stays fully covered and the
        // default-off behaviour is asserted separately by
        // RememberDeviceToggleTest.
        config()->set('auth.features.remember_device', true);

        $this->ensureAuthTablesForTests();
    }

    public function test_admin_login_form_hides_remember_me_checkbox(): void
    {
        // PART 8 — the option must NOT appear on the credential login screen.
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertDontSee('name="remember"', false)
            ->assertDontSee('Remember this device for 12 hours');
    }

    public function test_security_login_form_hides_remember_me_checkbox(): void
    {
        // PART 8 — the option must NOT appear on the credential login screen.
        $this->get(route('security.login'))
            ->assertOk()
            ->assertDontSee('name="remember"', false)
            ->assertDontSee('Remember this device for 12 hours');
    }

    public function test_remember_duration_is_twelve_hours_in_minutes(): void
    {
        // 12 HOURS, expressed in the unit Laravel actually expects. Laravel's
        // SessionGuard::setRememberDuration() takes MINUTES (its own docblock
        // says so) and the cookie jar then multiplies by 60. The value used to
        // be 43200, which is 43200 minutes = 30 DAYS — a device stayed trusted
        // for a month instead of half a day.
        $this->assertSame(720, config('auth.guards.admin.remember'));
        $this->assertSame(720, config('auth.guards.security.remember'));

        // Guard against the regression directly: it must NOT be the old value.
        $this->assertNotSame(43200, config('auth.guards.admin.remember'));
    }

    public function test_admin_login_with_remember_stores_flag_in_pending_mfa(): void
    {
        $this->createAdmin('remember-admin@example.com');

        $this->post(route('admin.login.post'), [
            'email' => 'remember-admin@example.com',
            'password' => 'adminpass',
            'remember' => '1',
        ])->assertRedirect(route('mfa.verify.show'));

        $pending = session('mfa.pending');
        $this->assertIsArray($pending);
        // PART 8 — the credential stage can no longer pre-enable Remember Me;
        // it is chosen on the MFA verification screen only.
        $this->assertFalse((bool) $pending['remember'], 'The credential stage must never pre-enable Remember Me.');
    }

    public function test_admin_login_without_remember_stores_false_flag(): void
    {
        $this->createAdmin('no-remember-admin@example.com');

        $this->post(route('admin.login.post'), [
            'email' => 'no-remember-admin@example.com',
            'password' => 'adminpass',
        ])->assertRedirect(route('mfa.verify.show'));

        $pending = session('mfa.pending');
        $this->assertFalse($pending['remember']);
    }

    public function test_security_login_with_remember_stores_flag_in_pending_mfa(): void
    {
        Security::create([
            'email' => 'remember-security@example.com',
            'password' => Hash::make('securitypass'),
            'mfa_totp_enabled' => false,
        ]);

        $this->post(route('security.login.post'), [
            'email' => 'remember-security@example.com',
            'password' => 'securitypass',
            'remember' => '1',
        ])->assertRedirect(route('mfa.verify.show'));

        $pending = session('mfa.pending');
        // PART 8 — see the admin case above.
        $this->assertFalse((bool) $pending['remember']);
    }

    public function test_verified_admin_login_with_remember_issues_recaller_cookie(): void
    {
        $admin = $this->createAdmin('rm-admin@example.com');

        $verify = $this->postAdminEmailLogin('rm-admin@example.com', remember: true);

        $verify->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated('admin');

        $rememberCookie = $this->findRememberCookie($verify);
        $this->assertNotNull($rememberCookie, 'A remember (recaller) cookie must be set after a remember-me login.');

        // The recaller cookie must live ~12 HOURS (720 minutes), NOT 30 days.
        // Laravel treats the configured value as minutes and multiplies by 60.
        $this->assertGreaterThanOrEqual(time() + (720 - 1) * 60, $rememberCookie->getExpiresTime());
        $this->assertLessThanOrEqual(time() + (720 + 1) * 60, $rememberCookie->getExpiresTime());

        // The DB remember token must have been written and rotated.
        $admin->refresh();
        $this->assertNotEmpty($admin->remember_token);
    }

    // ------------------------------------------------------------------
    // MFA screen: the checkbox must be offered, and its FINAL state wins.
    // ------------------------------------------------------------------

    public function test_mfa_verify_screen_shows_remember_me_checkbox(): void
    {
        $this->createAdmin('mfa-checkbox@example.com');

        $this->post(route('admin.login.post'), [
            'email' => 'mfa-checkbox@example.com',
            'password' => 'adminpass',
            'remember' => '1',
        ])->assertRedirect(route('mfa.verify.show'));

        // The METHOD-SELECTION stage must NOT offer the option: nothing has been
        // verified yet, so no persistent device may be requested.
        $this->get(route('mfa.verify.show'))
            ->assertOk()
            ->assertDontSee('Remember this device for 12 hours')
            ->assertDontSee('id="mfaRememberMe"', false);

        // After choosing a method the user reaches the code-input stage, where
        // the option IS offered. It starts UNticked.
        $this->post(route('mfa.verify.submit'), ['action' => 'select', 'method' => 'email'])
            ->assertRedirect(route('mfa.verify.show'));

        $this->get(route('mfa.verify.show'))
            ->assertOk()
            ->assertSee('Remember this device for 12 hours')
            ->assertSee('id="mfaRememberMe"', false)
            ->assertDontSee('id="mfaRememberMe" checked', false);
    }

    public function test_mfa_verify_screen_does_not_show_remember_me_for_student_flow(): void
    {
        // Build a student pending challenge directly: student logins normalize
        // `remember` to false, so the MFA screen must not offer the checkbox.
        $pending = [
            'guard' => 'student',
            'identifier' => '2026-00099',
            'email' => 'student@example.com',
            'email_masked' => 's***@example.com',
            'methods' => ['email'],
            'selected_method' => 'email',
            'stage' => 'code',
            'remember' => false,
        ];

        $this->withSession(['mfa.pending' => $pending])
            ->get(route('mfa.verify.show'))
            ->assertOk()
            ->assertDontSee('mfaRememberMe', false);
    }

    public function test_remember_action_endpoint_no_longer_exists(): void
    {
        $this->createAdmin('toggle-admin@example.com');

        $this->post(route('admin.login.post'), [
            'email' => 'toggle-admin@example.com',
            'password' => 'adminpass',
        ])->assertRedirect(route('mfa.verify.show'));

        $this->assertFalse(session('mfa.pending')['remember']);

        // A dedicated "remember" action that recorded the flag WITHOUT verifying
        // a code was a side channel: it let a persistent device be requested
        // outside the verification step. The action is gone, so posting it must
        // fall through to the ordinary verify path and fail validation rather
        // than silently flipping the flag.
        $this->post(route('mfa.verify.submit'), [
            'action' => 'remember',
            'remember' => '1',
        ])->assertSessionHasErrors('code');

        $this->assertFalse(
            session('mfa.pending')['remember'],
            'The remember flag must not be settable outside the verification step.'
        );
        $this->assertGuest('admin');
    }

    /**
     * A crafted `remember` on the method-SELECTION step must not be honoured
     * either: the flag is only read from the verification POST.
     */
    public function test_remember_cannot_be_enabled_from_the_method_selection_step(): void
    {
        $this->createAdmin('select-remember@example.com');

        $this->post(route('admin.login.post'), [
            'email' => 'select-remember@example.com',
            'password' => 'adminpass',
        ])->assertRedirect(route('mfa.verify.show'));

        $this->post(route('mfa.verify.submit'), [
            'action' => 'select',
            'method' => 'email',
            'remember' => '1',
        ])->assertRedirect(route('mfa.verify.show'));

        $this->assertFalse(
            session('mfa.pending')['remember'],
            'Selecting a method must not record a remember decision.'
        );
    }

    public function test_remember_action_cannot_enable_remember_for_student_guard(): void
    {
        $pending = [
            'guard' => 'student',
            'identifier' => '2026-00100',
            'email' => 'student@example.com',
            'email_masked' => 's***@example.com',
            'methods' => ['email'],
            'selected_method' => 'email',
            'stage' => 'code',
            'remember' => false,
            'attempts' => 0,
        ];

        // The removed "remember" action must not be able to reach the pending
        // challenge at all; it falls through to verification and fails.
        $this->withSession(['mfa.pending' => $pending])
            ->post(route('mfa.verify.submit'), [
                'action' => 'remember',
                'remember' => '1',
            ])
            ->assertSessionHasErrors('code');

        $this->assertFalse(session('mfa.pending')['remember'], 'A student challenge must never hold the remember flag.');
    }

    public function test_unchecking_remember_on_mfa_screen_prevents_recaller_cookie(): void
    {
        // TEST 3 — checked on login, UNCHECKED during MFA.
        $admin = $this->createAdmin('uncheck-admin@example.com');

        $verify = $this->postAdminEmailLogin('uncheck-admin@example.com', remember: true, rememberOnMfa: false);

        $verify->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated('admin');
        $this->assertNull(
            $this->findRememberCookie($verify),
            'The final MFA-screen selection (unchecked) must prevent persistent authentication.'
        );

        $admin->refresh();
        $this->assertEmpty($admin->remember_token);
    }

    public function test_checking_remember_on_mfa_screen_creates_recaller_cookie(): void
    {
        // TEST 4 — unchecked on login, CHECKED during MFA.
        $admin = $this->createAdmin('check-late-admin@example.com');

        $verify = $this->postAdminEmailLogin('check-late-admin@example.com', remember: false, rememberOnMfa: true);

        $verify->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated('admin');
        $this->assertNotNull(
            $this->findRememberCookie($verify),
            'The final MFA-screen selection (checked) must create persistent authentication.'
        );

        $admin->refresh();
        $this->assertNotEmpty($admin->remember_token);
    }

    public function test_invalid_mfa_with_remember_does_not_authenticate_or_remember(): void
    {
        // TEST 5 — Remember Me must never survive a failed MFA.
        $admin = $this->createAdmin('invalid-mfa-admin@example.com');

        $this->post(route('admin.login.post'), [
            'email' => 'invalid-mfa-admin@example.com',
            'password' => 'adminpass',
            'remember' => '1',
        ])->assertRedirect(route('mfa.verify.show'));

        $pending = session('mfa.pending');
        $pending['selected_method'] = 'email';
        $pending['stage'] = 'code';
        $pending['code_hash'] = Hash::make('123456');
        $pending['expires_at'] = now()->addMinutes(5)->timestamp;
        $pending['attempts'] = 0;

        $response = $this->withSession(['mfa.pending' => $pending])
            ->post(route('mfa.verify.submit'), [
                'method' => 'email',
                'code' => '999999',
                'remember' => '1',
            ]);

        $response->assertSessionHasErrors('code');
        $this->assertGuest('admin');
        $this->assertNull($this->findRememberCookie($response), 'No remember cookie may be issued on a failed MFA.');

        $admin->refresh();
        $this->assertEmpty($admin->remember_token);
    }

    public function test_expired_mfa_with_remember_does_not_authenticate(): void
    {
        // TEST 6 — an expired OTP must not be redeemed by Remember Me.
        $admin = $this->createAdmin('expired-mfa-admin@example.com');

        $this->post(route('admin.login.post'), [
            'email' => 'expired-mfa-admin@example.com',
            'password' => 'adminpass',
            'remember' => '1',
        ])->assertRedirect(route('mfa.verify.show'));

        $pending = session('mfa.pending');
        $pending['selected_method'] = 'email';
        $pending['stage'] = 'code';
        $pending['code_hash'] = Hash::make('123456');
        $pending['expires_at'] = now()->subMinute()->timestamp; // already expired
        $pending['attempts'] = 0;

        $response = $this->withSession(['mfa.pending' => $pending])
            ->post(route('mfa.verify.submit'), [
                'method' => 'email',
                'code' => '123456',
                'remember' => '1',
            ]);

        $response->assertSessionHasErrors('code');
        $this->assertGuest('admin');
        $this->assertNull($this->findRememberCookie($response));

        $admin->refresh();
        $this->assertEmpty($admin->remember_token);
    }

    public function test_security_login_mfa_screen_shows_remember_me_checkbox(): void
    {
        Security::create([
            'email' => 'mfa-security-checkbox@example.com',
            'password' => Hash::make('securitypass'),
            'mfa_totp_enabled' => false,
        ]);

        $this->post(route('security.login.post'), [
            'email' => 'mfa-security-checkbox@example.com',
            'password' => 'securitypass',
            'remember' => '1',
        ])->assertRedirect(route('mfa.verify.show'));

        // Method selection: absent. Code input: present.
        $this->get(route('mfa.verify.show'))
            ->assertOk()
            ->assertDontSee('Remember this device for 12 hours');

        $this->post(route('mfa.verify.submit'), ['action' => 'select', 'method' => 'email'])
            ->assertRedirect(route('mfa.verify.show'));

        $this->get(route('mfa.verify.show'))
            ->assertOk()
            ->assertSee('Remember this device for 12 hours');
    }

    public function test_verified_admin_login_without_remember_does_not_issue_recaller_cookie(): void
    {
        $admin = $this->createAdmin('no-rm-admin@example.com');

        $verify = $this->postAdminEmailLogin('no-rm-admin@example.com', remember: false);

        $verify->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated('admin');

        $this->assertNull($this->findRememberCookie($verify), 'No remember cookie may be set when the box is unticked.');

        $admin->refresh();
        $this->assertEmpty($admin->remember_token);
    }

    public function test_returning_admin_with_valid_recaller_cookie_is_auto_authenticated(): void
    {
        $this->createAdmin('auto-admin@example.com');

        $verify = $this->postAdminEmailLogin('auto-admin@example.com', remember: true);

        $rememberCookie = $this->findRememberCookie($verify);
        $this->assertNotNull($rememberCookie);

        // Simulate the user returning later with NO live session, only the cookie.
        Auth::forgetGuards();
        $this->flushSession();

        // The encrypted recaller cookie value (as sent by a real browser) is
        // passed through un-encrypted so the app decrypts it exactly once.
        $auto = $this->withUnencryptedCookie($rememberCookie->getName(), $rememberCookie->getValue())
            ->get(route('totp.setup'));

        $auto->assertOk();
        $this->assertAuthenticated('admin');
    }

    public function test_a_recaller_cookie_with_a_stale_token_is_rejected(): void
    {
        $admin = $this->createAdmin('stale-admin@example.com');

        // The cookie embeds a token that no longer matches the DB (e.g. the
        // token was rotated by a logout or password reset).
        $staleToken = Str::random(60);
        $admin->forceFill(['remember_token' => Str::random(60)])->save();

        $recallerName = 'remember_admin_' . sha1(\Illuminate\Auth\SessionGuard::class);

        Auth::forgetGuards();
        $this->flushSession();

        $request = $this->withUnencryptedCookie(
            $recallerName,
            $admin->getKey() . '|' . $staleToken . '|' . hash('sha256', 'whatever')
        )->get(route('totp.setup'));

        // The recaller must NOT authenticate the user.
        $request->assertRedirect(route('student.login'));
        $this->assertGuest('admin');
    }

    public function test_without_recaller_cookie_user_is_guest_and_redirected_to_login(): void
    {
        $this->createAdmin('expired-admin@example.com');

        Auth::forgetGuards();
        $this->flushSession();

        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('student.login'));
        $this->assertGuest('admin');
    }

    public function test_explicit_logout_forgets_the_recaller_cookie(): void
    {
        $this->createAdmin('logout-admin@example.com');

        $verify = $this->postAdminEmailLogin('logout-admin@example.com', remember: true);
        $this->assertNotNull($this->findRememberCookie($verify));

        $logout = $this->post(route('admin.logout'));
        $logout->assertRedirect(route('admin.login'));

        // After an explicit logout the remember cookie must be cleared.
        $this->assertNull($this->findRememberCookie($logout));
        $this->assertGuest('admin');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createAdmin(string $email): Admin
    {
        return Admin::create([
            'username' => 'admin-' . bin2hex(random_bytes(2)),
            'email' => $email,
            'password' => Hash::make('adminpass'),
            'mfa_totp_enabled' => false,
        ]);
    }

    /**
     * Complete an admin email-OTP login over HTTP.
     *
     * Admin/security MFA challenges start on the method "select" stage and do
     * NOT issue an email code until a method is chosen. To drive the flow to a
     * successful verification in a test we seed the pending session with a
     * known code hash + expiry (the same approach used by the in-app flow after
     * the user selects Email OTP), then submit the code.
     *
     * `$rememberOnMfa` emulates the user changing the Remember Me checkbox on
     * the MFA screen. When omitted, the checkbox is posted in the state it had
     * on the login screen — exactly what the rendered MFA form does, since its
     * checkbox mirrors `$pending['remember']`.
     */
    private function postAdminEmailLogin(string $email, bool $remember, ?bool $rememberOnMfa = null): TestResponse
    {
        $this->post(route('admin.login.post'), [
            'email' => $email,
            'password' => 'adminpass',
            'remember' => $remember ? '1' : null,
        ])->assertRedirect(route('mfa.verify.show'));

        $pending = session('mfa.pending');
        $this->assertIsArray($pending);

        $pending['selected_method'] = 'email';
        $pending['stage'] = 'code';
        $pending['code_hash'] = Hash::make('123456');
        $pending['expires_at'] = now()->addMinutes(5)->timestamp;
        $pending['attempts'] = 0;

        $finalRemember = $rememberOnMfa ?? $remember;

        return $this->withSession(['mfa.pending' => $pending])
            ->post(route('mfa.verify.submit'), [
                'method' => 'email',
                'code' => '123456',
                'remember' => $finalRemember ? '1' : '0',
            ]);
    }

    private function findRememberCookie(TestResponse $response): ?Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if (str_starts_with($cookie->getName(), 'remember_')) {
                return $cookie;
            }
        }

        return null;
    }

    private function ensureAuthTablesForTests(): void
    {
        if (! Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $table): void {
                $table->id();
                $table->string('username')->nullable();
                $table->string('email')->unique();
                $table->string('password');
                $table->string('remember_token', 100)->nullable();
                $table->text('mfa_totp_secret')->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
            });
        }

        if (! Schema::hasTable('security')) {
            Schema::create('security', function (Blueprint $table): void {
                $table->id();
                $table->string('email')->unique();
                $table->string('password');
                $table->string('remember_token', 100)->nullable();
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
                $table->timestamp('created_at')->nullable();
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
    }
}
