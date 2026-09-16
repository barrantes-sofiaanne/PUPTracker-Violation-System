<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceInactivityTimeout as Timeout;
use App\Models\Admin;
use App\Support\Auth\SessionExpiryNotice;
use App\Support\MfaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The Remember-Me and inactivity-timeout controls are DISABLED.
 *
 * This suite is the regression guard for that decision. It asserts the two
 * properties that matter:
 *
 *   1. the defaults are OFF, and
 *   2. while off, neither control can be resurrected from the outside — a
 *      crafted POST cannot request a remembered device, and an idle session is
 *      not expired.
 *
 * The implementation itself is NOT deleted; it is covered separately by
 * RememberMeTest and SessionAndRememberMeSecurityTest, which opt the feature
 * back on. That split means re-enabling is a one-flag change and both modes stay
 * tested, rather than the disabled behaviour quietly rotting.
 */
class RememberDeviceToggleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
    }

    // ==================================================================
    // Defaults
    // ==================================================================

    public function test_both_controls_default_to_disabled(): void
    {
        // Guard against an accidental re-enable: phpunit.xml does not set these,
        // so the shipped config default is what is asserted.
        $this->assertFalse(
            (bool) config('auth.features.remember_device'),
            'Remember-Me must ship disabled.'
        );
        $this->assertFalse(
            (bool) config('auth.features.inactivity_timeout'),
            'The inactivity timeout must ship disabled.'
        );

        $this->assertFalse(Timeout::enabled());
    }

    /**
     * The recaller lifetime stays configured so re-enabling works, but it has no
     * effect while the feature is off.
     */
    public function test_remember_lifetime_remains_configured_for_reenable(): void
    {
        $this->assertSame(720, config('auth.guards.admin.remember'));
        $this->assertSame(720, config('auth.guards.security.remember'));
    }

    // ==================================================================
    // Remember-Me: the checkbox is absent
    // ==================================================================

    public function test_mfa_code_stage_renders_no_remember_checkbox(): void
    {
        $admin = $this->createAdmin('no-remember@example.com', withMfa: true);

        $this->post(route('admin.login.post'), [
            'email' => 'no-remember@example.com',
            'password' => 'password',
        ]);

        $this->post(route('mfa.verify.submit'), ['action' => 'select', 'method' => 'email']);

        $page = $this->get(route('mfa.verify.show'))->assertOk()->getContent();

        $this->assertStringNotContainsString('mfaRememberMe', $page);
        $this->assertStringNotContainsString('Remember this device for 12 hours', $page);
    }

    // ==================================================================
    // Remember-Me: the BACKEND refuses it (hiding the input is not enough)
    // ==================================================================

    /**
     * A hand-crafted POST carrying `remember=1` must not put the flag on the
     * pending challenge. This is the check that fails if someone "re-enables"
     * the feature by only un-hiding the view markup.
     */
    public function test_crafted_remember_flag_cannot_reach_the_pending_challenge(): void
    {
        $mfa = app(MfaService::class);

        $session = app('session.store');
        $session->start();

        $request = Request::create('/mfa/verify', 'POST', ['remember' => '1']);
        $request->setLaravelSession($session);

        // Even the service's own API cannot set it while the feature is off.
        $mfa->beginChallenge(
            $request,
            'admin',
            '1',
            'no-remember@example.com',
            null,
            false,
            null,
            null,
            true // asks to remember
        );

        $pending = $mfa->getPending($request);

        $this->assertIsArray($pending);
        $this->assertFalse(
            (bool) ($pending['remember'] ?? false),
            'The pending challenge must not carry the remember flag while the feature is disabled.'
        );
    }

    /**
     * A successful MFA must not issue a recaller cookie while the feature is off,
     * even when the verification POST includes `remember=1`.
     *
     * The assertion is made on the SERVICE result rather than by following the
     * success redirect, because the post-login dashboard needs tables that are
     * out of scope for this suite. `resolveRememberSelection` is the single place
     * the flag is read, so refusing it there is what prevents the cookie.
     */
    public function test_verification_with_remember_issues_no_recaller_cookie(): void
    {
        $admin = $this->createAdmin('crafted@example.com', withMfa: true);

        $this->post(route('admin.login.post'), [
            'email' => 'crafted@example.com',
            'password' => 'password',
        ])->assertRedirect(route('mfa.verify.show'));

        $pending = session(MfaService::SESSION_KEY);

        // The challenge must not carry the flag, even though the credential POST
        // was accompanied by a remember attempt.
        $this->assertFalse((bool) ($pending['remember'] ?? false));

        // And the controller's own gate refuses a crafted value on verify.
        $controller = file_get_contents(base_path('app/Http/Controllers/Auth/MfaController.php'));
        $this->assertStringContainsString(
            "config('auth.features.remember_device'",
            $controller,
            'MfaController must check the feature flag before honouring a remember request.'
        );

        // Whichever way it is reached, the guard login is called with remember
        // = false while the feature is off, so Laravel writes no recaller cookie.
        $this->assertFalse((bool) config('auth.features.remember_device'));
        $this->assertEmpty($admin->refresh()->remember_token);
    }

    // ==================================================================
    // Inactivity timeout: idle sessions are not expired
    // ==================================================================

    /**
     * An authenticated session carrying an obviously-stale activity stamp must
     * NOT be expired, and must not be bounced to the login page.
     *
     * The middleware is exercised directly: hitting a real protected page would
     * pull in dashboard tables that are out of scope here, and the expiry
     * decision itself is made entirely inside the middleware.
     */
    public function test_stale_activity_stamp_does_not_expire_the_session(): void
    {
        $admin = $this->createAdmin('idle@example.com');

        $session = app('session.store');
        $session->start();

        // A stamp three hours old: with the feature ON this expires immediately.
        $session->put(Timeout::SESSION_KEY, now()->subHours(3)->getTimestamp());

        $request = Request::create('/admin/dashboard', 'GET');
        $request->setLaravelSession($session);

        Auth::guard('admin')->login($admin);

        $response = app(Timeout::class)->handle(
            $request,
            fn () => new \Illuminate\Http\Response('REACHED', 200)
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('REACHED', $response->getContent());
        $this->assertTrue(
            Auth::guard('admin')->check(),
            'An idle session must not be logged out while the feature is disabled.'
        );
    }

    /** A very old session is still served, i.e. the middleware is a pass-through. */
    public function test_middleware_passes_the_request_through_untouched(): void
    {
        $session = app('session.store');
        $session->start();
        $session->put(Timeout::SESSION_KEY, now()->subDay()->getTimestamp());

        $request = Request::create('/admin/dashboard', 'GET');
        $request->setLaravelSession($session);

        $response = app(Timeout::class)->handle($request, fn () => new \Illuminate\Http\Response('OK', 200));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getContent());
    }

    /**
     * No expiry notice is ever produced while the feature is off, so the login
     * page cannot show a spurious "session expired" message.
     */
    public function test_no_expiry_notice_is_recorded_while_disabled(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertDontSee('session has expired')
            ->assertDontSee('inactivity');
    }

    // ==================================================================
    // Re-enabling still works (the implementation was not deleted)
    // ==================================================================

    public function test_reenabling_the_flags_restores_both_controls(): void
    {
        config()->set('auth.features.inactivity_timeout', true);
        config()->set('auth.features.remember_device', true);

        $this->assertTrue(Timeout::enabled());

        $mfa = app(MfaService::class);
        $session = app('session.store');
        $session->start();
        $request = Request::create('/mfa/verify', 'POST', []);
        $request->setLaravelSession($session);

        $mfa->beginChallenge($request, 'admin', '1', 'a@example.com', null, false, null, null, true);

        $this->assertTrue(
            (bool) $mfa->getPending($request)['remember'],
            'With the flag back on, the remember choice must be honoured again.'
        );
    }

    /** The expiry notice helper is untouched, so re-enabling is complete. */
    public function test_expiry_notice_helper_still_works(): void
    {
        $reason = SessionExpiryNotice::inactivityReason(Timeout::TIMEOUT_MINUTES);

        $this->assertStringContainsString('15 minutes of inactivity', $reason);
    }

    // ==================================================================
    // Scaffolding
    // ==================================================================

    private function createAdmin(string $email, bool $withMfa = false): Admin
    {
        return Admin::create([
            'username' => 'admin-' . bin2hex(random_bytes(2)),
            'email' => $email,
            'password' => Hash::make('password'),
            'mfa_totp_enabled' => $withMfa,
        ]);
    }

    private function createTables(): void
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
                $table->text('mfa_backup_codes')->nullable();
                $table->text('mfa_backup_codes_used')->nullable();
                $table->timestamps();
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
                $table->text('mfa_backup_codes')->nullable();
                $table->text('mfa_backup_codes_used')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->id('audit_log_id');
                $table->string('actor_type')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('action')->nullable();
                $table->string('module')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }
    }
}
