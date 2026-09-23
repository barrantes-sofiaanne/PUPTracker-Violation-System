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
 * authentication + MFA (there is deliberately no separate OSS Staff MFA). This
 * controller therefore looks the submitted email up in BOTH credential stores
 * and hands the request to the matching module's real flow; the destination is
 * then decided from the account's ROLE (see MfaController), so an OSS Staff
 * member lands in the OSS Staff module and never in the Security module.
 *
 * The routes, route names and the `security` guard are unchanged — only the
 * visible branding of this page and the set of accounts it accepts are.
 */
class SecurityLoginController extends Controller
{
    /**
     * The module key used for the login-attempt limiter and its config.
     *
     * A lockout is scoped to this module, so the SHARED portal has its own
     * budget: a locked-out Security officer (or OSS Staff member) cannot block
     * the Administrator login, and vice versa.
     */
    public const LOGIN_MODULE = 'security';

    /**
     * The module key used for OSS Staff credential verification.
     *
     * The lockout ledger is scoped to the module, so an existing Administrator
     * lockout must not lock the shared portal for everyone — only the OTHER
     * account trying that email is affected.
     */
    private const ADMIN_MODULE = 'admin';

    public function showLoginForm()
    {
        return view('auth.security-login');
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

        // Failed credential attempts are bounded per account using the SAME
        // shared rule as the Administrator module. The limiter keys on the
        // module name, so a Security lockout can never block the Administrator
        // login (and vice versa). See AdminLoginController for the rationale.
        //
        // A locked shared portal is reported before any credential lookup, so a
        // locked-out user learns nothing about whether an account exists.
        if (LoginAttemptLimiter::isLocked(self::LOGIN_MODULE, $request)) {
            Log::warning('Security login blocked: too many failed attempts', [
                'retry_after_seconds' => LoginAttemptLimiter::secondsRemaining(self::LOGIN_MODULE, $request),
            ]);

            AuditLog::create([
                'actor_type' => 'system',
                'actor_id' => null,
                'action' => 'Login Blocked',
                'module' => 'Authentication',
                'description' => 'Security login blocked after ' . LoginAttemptLimiter::policy(self::LOGIN_MODULE)['max'] . ' failed attempts.',
                'created_at' => now(),
            ]);

            return back()
                ->withErrors([
                    'login' => LoginAttemptLimiter::lockedMessage(self::LOGIN_MODULE, $request),
                ])
                ->withInput();
        }

        // The submitted email decides WHICH credential store is authoritative
        // for this attempt. Security accounts and OSS Staff accounts are
        // different people in different tables, so an email is never matched in
        // both: a Security row wins, otherwise the Administrators are consulted.
        $security = Security::where('email', $request->email)->first();

       return $security !== null
    ? $this->loginSecurityAccount($request, $mfaService, $trustedDeviceService, $security)
    : $this->loginOssStaffAccount($request, $mfaService, $trustedDeviceService);
    }

    public function logout(Request $request)
    {
        Auth::guard('security')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('security.login');
    }

    /**
     * Verify a SECURITY account and start the Security MFA challenge.
     *
     * Behaviour is unchanged from before the portal became shared, except that
     * it returns early so the OSS Staff branch can run for other emails.
     */
   private function loginSecurityAccount(
    Request $request,
    MfaService $mfaService,
    MfaTrustedDeviceService $trustedDeviceService,
    Security $security
)
    {
        if (! Hash::check($request->password, $security->password)) {
            LoginAttemptLimiter::recordFailure(self::LOGIN_MODULE, $request);

            return $this->invalidCredentials($request);
        }

        // A SUCCESSFUL credential check clears the failed-attempt counter.
        LoginAttemptLimiter::clear(self::LOGIN_MODULE, $request);
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
            $this->recordSharedPortalFailure($request);

            return $this->invalidCredentials($request);
        }

        if (! Hash::check($request->password, $admin->password)) {
            $this->recordSharedPortalFailure($request);

            return $this->invalidCredentials($request);
        }

        LoginAttemptLimiter::clear(self::LOGIN_MODULE, $request);
        // These ARE this admin's real credentials, so clear the Administrator
        // module's counter too — a stale lockout armed for the Administrator
        // login must not survive a successful sign-in here.
        LoginAttemptLimiter::clear(self::ADMIN_MODULE, $request);

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
     * Record an OSS Staff credential failure in BOTH ledgers.
     *
     * An OSS Staff account's credentials ARE Administrator credentials, so a
     * failed attempt here is a failed Administrator attempt too. Counting it in
     * both places keeps the two ledgers symmetric with the success path (which
     * clears both) and stops this portal from silently DOUBLING the guesses an
     * attacker gets against an OSS Staff account.
     *
     * A rejected non-OSS-Staff request (e.g. a regular Administrator trying this
     * portal) is counted only against the shared portal, so the Administrator
     * login is never locked out by an attempt that could not have succeeded
     * there.
     */
    private function recordSharedPortalFailure(Request $request): void
    {
        LoginAttemptLimiter::recordFailure(self::LOGIN_MODULE, $request);

        if (Admin::where('email', $request->email)->first()?->isOssStaff()) {
            LoginAttemptLimiter::recordFailure(self::ADMIN_MODULE, $request);
        }
    }

    /**
     * The generic credential failure, shared by BOTH account types so a probe
     * cannot distinguish "no such account" from "wrong password".
     */
    private function invalidCredentials(Request $request)
    {
        return back()
            ->withErrors([
                'login' => LoginAttemptLimiter::failureMessage(self::LOGIN_MODULE, $request),
            ])
            ->withInput();
    }
}
