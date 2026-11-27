<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Security;
use App\Support\Auth\LoginAttemptLimiter;
use App\Support\MfaService;
use App\Support\MfaTrustedDeviceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Middleware\EnforceInactivityTimeout;
/**
 * The SHARED Security + OSS Staff login.
 *
 * WHY ONE PAGE FOR TWO ACCOUNT TYPES
 * ----------------------------------
 * Security Personnel and OSS Staff sign in through the SAME page
 * (`/security/login`), but they remain DIFFERENT account types on DIFFERENT
 * guards:
 *
 *   Security Personnel -> `security` guard -> Security module
 *   OSS Staff          -> `admin`    guard -> OSS Staff module
 *
 * OSS Staff is an ADMIN account, so it MUST run the existing Administrator
 * authentication + MFA (there is deliberately no separate OSS Staff MFA). After
 * the challenge passes, MfaController routes on the account's ROLE, so an OSS
 * Staff member lands in the OSS Staff module and never in the Security module.
 *
 * STRICT MODULE SEPARATION
 * ------------------------
 * The page has TWO modes, selected with `?portal=oss` and carried through the
 * form as a hidden `portal` field:
 *
 *   portal=security (default) -> accepts SECURITY accounts only
 *   portal=oss                -> accepts OSS STAFF accounts only
 *
 * The mode is enforced SERVER-SIDE before any session is created: an OSS Staff
 * account submitting to the Security portal is rejected exactly like a wrong
 * password, and a Security account (or a plain Administrator) submitting to the
 * OSS Staff portal is rejected the same way. Hiding a link is never the control.
 *
 * The routes, route names and the `security` guard are unchanged — only the
 * visible branding of this page and the set of accounts each mode accepts are.
 */
class SecurityLoginController extends Controller
{
    /**
     * Module key, kept for audit/log wording only.
     *
     * The login-attempt counter itself is GLOBAL (one budget per client), so it
     * is no longer scoped by module — see LoginAttemptLimiter.
     */
    public const LOGIN_MODULE = 'security';

    /** The Security Personnel mode of the shared page (the default). */
    public const PORTAL_SECURITY = 'security';

    /** The OSS Staff mode of the shared page (`?portal=oss`). */
    public const PORTAL_OSS = 'oss';

    public function showLoginForm()
    {
        return view('auth.security-login');
    }

    /**
     * Which mode of the shared page this request is for.
     *
     * Reads the submitted `portal` field first, then the query string, and
     * FAILS CLOSED to the Security portal: an absent/unrecognised mode can never
     * admit an OSS Staff account, because the Security portal accepts only
     * Security accounts.
     */
    private function resolvePortal(Request $request): string
    {
        $portal = $request->input('portal', $request->query('portal'));

        return $portal === self::PORTAL_OSS
            ? self::PORTAL_OSS
            : self::PORTAL_SECURITY;
    }

    public function login(
    Request $request,
    MfaService $mfaService,
    MfaTrustedDeviceService $trustedDeviceService
)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $portal = $this->resolvePortal($request);

        // Failed credential attempts are bounded per CLIENT using the SAME
        // shared rule as every other login page. The limiter keys on the client
        // IP only, so switching between the Admin, Security and OSS Staff login
        // pages cannot reset the budget.
        //
        // A locked client is reported before any credential lookup, so a
        // locked-out user learns nothing about whether an account exists.
        if (LoginAttemptLimiter::isLocked($request)) {
            Log::warning('Security login blocked: too many failed attempts', [
                'portal' => $portal,
                'retry_after_seconds' => LoginAttemptLimiter::secondsRemaining($request),
            ]);

            AuditLog::create([
                'actor_type' => 'system',
                'actor_id' => null,
                'action' => 'Login Blocked',
                'module' => 'Authentication',
                'description' => 'Security login blocked after ' . LoginAttemptLimiter::policy()['max'] . ' failed attempts.',
                'created_at' => now(),
            ]);

            return back()
                ->withErrors([
                    'login' => LoginAttemptLimiter::lockedMessage($request),
                ])
                ->withInput();
        }

        return $portal === self::PORTAL_OSS
            ? $this->loginOssStaffAccount($request, $mfaService, $trustedDeviceService)
            : $this->loginSecurityAccount($request, $mfaService, $trustedDeviceService);
    }

    public function logout(Request $request)
    {
        Auth::guard('security')->logout();

        // NOTE: the MFA trusted-device record is deliberately NOT deleted here.
        // Logging out ends the authenticated session; it must not discard a
        // still-valid remembered verification, whose 12-hour window is measured
        // from the original verification (never from this logout).

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('security.login');
    }

    /**
     * Verify a SECURITY account and start the Security MFA challenge.
     *
     * SECURITY PORTAL ONLY: an email that is not a Security account is rejected
     * identically to a wrong password, so an OSS Staff member or a plain
     * Administrator cannot enter the Security module through this page.
     */
   private function loginSecurityAccount(
    Request $request,
    MfaService $mfaService,
    MfaTrustedDeviceService $trustedDeviceService
)
    {
        $security = Security::where('email', $request->email)->first();

        if (! $security instanceof Security || ! Hash::check($request->password, $security->password)) {
            LoginAttemptLimiter::recordFailure($request);

            return $this->invalidCredentials($request);
        }

        // A SUCCESSFUL credential check clears the failed-attempt counter.
        LoginAttemptLimiter::clear($request);
        if (
            (bool) config('auth.features.remember_device', false)
            && $trustedDeviceService->isTrusted('security', (int) $security->getKey())
        ) {
            Auth::guard('security')->login($security, false);
$request->session()->regenerate();
EnforceInactivityTimeout::startFreshActivityWindow($request);

            Log::info('Security login: MFA skipped for trusted device', [
                'security_id' => $security->id,
            ]);

            return redirect()
                ->route(\App\Support\Auth\AuthDestination::forGuard('security', $security))
                ->with('show_login_announcement_modal', true);
        }
        if (empty($security->email)) {
            return back()
                ->withErrors([
                    'login' => 'Your account has no email address configured. Please contact an administrator.'
                ])
                ->withInput();
        }

        // Read the TOTP secret through the encrypted cast. A DecryptException
        // here means the stored value is legacy plaintext or was encrypted with
        // a different APP_KEY — BEFORE any challenge/email work. Categorized
        // separately in logs; the secret itself is never exposed.
        try {
            $totpSecret = $security->mfa_totp_secret;
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            Log::error('Security login: MFA secret decryption FAILED (cast read)', [
                'security_id' => $security->getKey(),
                'hint' => 'Stored mfa_totp_secret is not decryptable with the current APP_KEY (legacy plaintext or key mismatch). Run mfa:diagnose-totp, then mfa:encrypt-totp-secrets once if the key is stable.',
                'exception' => get_class($e),
            ]);

            return back()
                ->withErrors([
                    'login' => 'Unable to initialize multi-factor verification. Please contact the administrator.'
                ])
                ->withInput();
        }

        try {
            $mfaService->beginChallenge(
                $request,
                'security',
                (string) $security->getKey(),
                (string) $security->email,
                $totpSecret,
                (bool) $security->mfa_totp_enabled,
                $security->mfa_backup_codes,
                $security->mfa_backup_codes_used,
                // PART 8 — Remember Me is chosen on the MFA verification screen
                // only. The pending challenge always starts OFF so a crafted POST
                // cannot pre-enable a persistent credential before MFA.
                false
            );
        } catch (\Illuminate\Contracts\Encryption\DecryptException $exception) {
            Log::error('Security MFA challenge: decryption failure during initialization.', [
                'security_id' => $security->getKey(),
                'hint' => 'TOTP secret or backup codes not decryptable with the current APP_KEY.',
                'exception' => get_class($exception),
            ]);

            return back()
                ->withErrors([
                    'login' => 'Unable to initialize multi-factor verification. Please contact the administrator.'
                ])
                ->withInput();
        } catch (\Throwable $exception) {
            Log::error('Security MFA challenge initialization failed (non-decryption).', [
                'security_id' => $security->getKey(),
                'stage' => 'challenge-init',
                'error' => $exception->getMessage(),
                'exception' => get_class($exception),
            ]);

            return back()
                ->withErrors([
                    'login' => 'Unable to send verification code right now. Please try again later.'
                ])
                ->withInput();
        }

        return redirect()->route('mfa.verify.show')
            ->with('success', 'Continue with multi-factor verification.');
    }

    /**
     * Verify an OSS STAFF account (an ADMIN account holding the OSS Staff role)
     * and start the EXISTING Administrator MFA challenge.
     *
     * The challenge is started by
     * {@see AdminLoginController::beginMfaForVerifiedAdmin()} so there is exactly
     * ONE implementation of "an admin account begins MFA". After the challenge
     * passes, MfaController routes on the account's ROLE, which sends OSS Staff
     * to `oss-staff.dashboard`.
     *
     * FAIL CLOSED: only an account that resolves to the OSS Staff role may
     * proceed. A regular Administrator or an IT Administrator who tries this
     * portal receives the same generic credential error as a wrong password —
     * their accounts are deliberately NOT usable here, because this portal is
     * not the Administrator login.
     */
   private function loginOssStaffAccount(
    Request $request,
    MfaService $mfaService,
    MfaTrustedDeviceService $trustedDeviceService
)
    {
        $admin = Admin::where('email', $request->email)->first();

        // Every rejection answers identically, so this portal cannot be used to
        // discover which emails belong to administrators.
        if (! $admin instanceof Admin || ! $admin->isOssStaff()) {
            LoginAttemptLimiter::recordFailure($request);

            return $this->invalidCredentials($request);
        }

        if (! Hash::check($request->password, $admin->password)) {
            LoginAttemptLimiter::recordFailure($request);

            return $this->invalidCredentials($request);
        }

        LoginAttemptLimiter::clear($request);

               Log::info('Shared portal: OSS Staff credentials verified', ['admin_id' => $admin->id]);

        if (
            (bool) config('auth.features.remember_device', false)
            && $trustedDeviceService->isTrusted('admin', (int) $admin->getKey())
        ) {
            Auth::guard('admin')->login($admin, false);
$request->session()->regenerate();

EnforceInactivityTimeout::startFreshActivityWindow($request);

            Log::info('Shared portal: OSS Staff MFA skipped for trusted device', [
                'admin_id' => $admin->id,
            ]);

            return redirect()
                ->route(\App\Support\Auth\AuthDestination::forGuard('admin', $admin))
                ->with('show_login_announcement_modal', true);
        }

        return app(AdminLoginController::class)
            ->beginMfaForVerifiedAdmin($request, $mfaService, $admin);
    }

    /**
     * The generic credential failure.
     *
     * Identical for every rejection so a probe cannot distinguish "no such
     * account", "wrong password" or "wrong module" from one another.
     */
    private function invalidCredentials(Request $request)
    {
        return back()
            ->withErrors([
                'login' => LoginAttemptLimiter::failureMessage($request),
            ])
            ->withInput();
    }
}
