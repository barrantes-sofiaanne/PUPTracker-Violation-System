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
 * "Remember me" (30-day trusted device) for the Administrator & Security
 * login modules.
 *
 * Rules:
 *   - The checkbox appears only on the admin & security login forms.
 *   - Only when the box is ticked is a remember/recaller cookie issued (after
 *     password + MFA complete).
 *   - The cookie expires after 30 days (per-guard `auth.guards.*.remember`
 *     duration); after that the user must log in again (password + MFA).
 *   - Student logins never issue a remember cookie.
 */
class RememberMeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureAuthTablesForTests();
    }

    public function test_admin_login_form_shows_remember_me_checkbox(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Remember me for 30 days')
            ->assertSee('name="remember"', false);
    }

    public function test_security_login_form_shows_remember_me_checkbox(): void
    {
        $this->get(route('security.login'))
            ->assertOk()
            ->assertSee('Remember me for 30 days')
            ->assertSee('name="remember"', false);
    }

    public function test_admin_remember_duration_is_30_days(): void
    {
        $this->assertSame(43200, config('auth.guards.admin.remember'));
        $this->assertSame(43200, config('auth.guards.security.remember'));
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
        $this->assertTrue($pending['remember'], 'The remember flag must be carried into the pending MFA session.');
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
        $this->assertTrue($pending['remember']);
    }

    public function test_verified_admin_login_with_remember_issues_recaller_cookie(): void
    {
        $admin = $this->createAdmin('rm-admin@example.com');

        $verify = $this->postAdminEmailLogin('rm-admin@example.com', remember: true);

        $verify->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated('admin');

        $rememberCookie = $this->findRememberCookie($verify);
        $this->assertNotNull($rememberCookie, 'A remember (recaller) cookie must be set after a remember-me login.');

        // The recaller cookie must be configured to live ~30 days (43200 min).
        $this->assertGreaterThanOrEqual(time() + (43200 - 1) * 60, $rememberCookie->getExpiresTime());

        // The DB remember token must have been written and rotated.
        $admin->refresh();
        $this->assertNotEmpty($admin->remember_token);
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
     */
    private function postAdminEmailLogin(string $email, bool $remember): TestResponse
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

        return $this->withSession(['mfa.pending' => $pending])
            ->post(route('mfa.verify.submit'), [
                'method' => 'email',
                'code' => '123456',
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
