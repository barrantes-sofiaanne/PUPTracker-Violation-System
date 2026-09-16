<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Support\Auth\LoginAttemptLimiter;
use App\Support\Auth\SessionExpiryNotice;
use App\Support\MfaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AdminLoginController extends Controller
{
    /**
     * The module key used for the login-attempt limiter and its config.
     *
     * A lockout is scoped to this module, so an Administrator lockout does not
     * block the Security login.
     */
    public const LOGIN_MODULE = 'admin';

    public function showLoginForm()
    {
        return view('auth.admin-login');
    }

    public function login(Request $request, MfaService $mfaService)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            $emailMask = $this->maskEmail((string) $request->input('email'));
            Log::info('Admin login attempt started', ['email_masked' => $emailMask]);

            // Failed credential attempts are bounded per account, using the
            // SHARED rule in LoginAttemptLimiter so the Administrator and
            // Security modules cannot drift apart.
            //
            // The route throttle (`throttle:5,1`) is a PER-IP network backstop;
            // this is the per-ACCOUNT lock the brief requires, so rotating IPs
            // cannot buy extra guesses. The limiter keys on the module plus the
            // submitted email, so an Administrator lockout never blocks the
            // Security login, and the counter lives in the server-side cache so
            // refreshing the login page cannot reset it.
            if (LoginAttemptLimiter::isLocked(self::LOGIN_MODULE, $request)) {
                Log::warning('Admin login blocked: too many failed attempts', [
                    'email_masked' => $emailMask,
                    'retry_after_seconds' => LoginAttemptLimiter::secondsRemaining(self::LOGIN_MODULE, $request),
                ]);

                AuditLog::create([
                    'actor_type' => 'system',
                    'actor_id' => null,
                    'action' => 'Login Blocked',
                    'module' => 'Authentication',
                    'description' => 'Admin login blocked after ' . LoginAttemptLimiter::policy(self::LOGIN_MODULE)['max'] . ' failed attempts.',
                    'created_at' => now(),
                ]);

                return back()
                    ->withErrors([
                        'login' => LoginAttemptLimiter::lockedMessage(self::LOGIN_MODULE, $request),
                    ])
                    ->withInput();
            }

            $admin = Admin::where('email', $request->email)->first();

            if (!$admin || !Hash::check($request->password, $admin->password)) {
                // Count the failure BEFORE responding so the threshold cannot be
                // slipped past by a concurrent request.
                LoginAttemptLimiter::recordFailure(self::LOGIN_MODULE, $request);

                Log::info('Admin login: invalid credentials', [
                    'attempts_remaining' => LoginAttemptLimiter::remaining(self::LOGIN_MODULE, $request),
                ]);

                return back()
                    ->withErrors([
                        'login' => LoginAttemptLimiter::failureMessage(self::LOGIN_MODULE, $request),
                    ])
                    ->withInput();
            }

            // A SUCCESSFUL credential check clears the failed-attempt counter.
            // "3 attempts" means three FAILURES, never three lifetime logins.
            LoginAttemptLimiter::clear(self::LOGIN_MODULE, $request);

            Log::info('Admin login: credentials verified', ['admin_id' => $admin->id]);

            if (empty($admin->email)) {
                return back()
                    ->withErrors([
                        'login' => 'Your account has no email address configured. Please contact an administrator.'
                    ])
                    ->withInput();
            }

            Log::info('Admin login: starting MFA challenge', ['admin_id' => $admin->id]);

            // Read the TOTP secret through the encrypted cast. If the stored
            // value is legacy plaintext or was encrypted with a different
            // APP_KEY, the cast throws a DecryptException here — BEFORE any
            // challenge/email work. We categorize it separately so the real
            // cause is visible in logs without ever exposing the secret.
            try {
                $totpSecret = $admin->mfa_totp_secret;
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                Log::error('Admin login: MFA secret decryption FAILED (cast read)', [
                    'admin_id' => $admin->getKey(),
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
                    'admin',
                    (string) $admin->getKey(),
                    (string) $admin->email,
                    $totpSecret,
                    (bool) $admin->mfa_totp_enabled,
                    $admin->mfa_backup_codes,
                    $admin->mfa_backup_codes_used,
                    // PART 8 — Remember Me is chosen on the MFA verification
                    // screen only, never at the credential stage, so the pending
                    // challenge always starts with it OFF. Passing `false` rather
                    // than reading the request keeps a hand-crafted POST from
                    // pre-enabling a persistent credential before MFA.
                    false
                );
                Log::info('Admin login: MFA challenge started successfully');
            } catch (\Illuminate\Contracts\Encryption\DecryptException $exception) {
                Log::error('Admin MFA challenge: decryption failure during initialization.', [
                    'admin_id' => $admin->getKey(),
                    'hint' => 'TOTP secret or backup codes not decryptable with the current APP_KEY.',
                    'exception' => get_class($exception),
                ]);

                return back()
                    ->withErrors([
                        'login' => 'Unable to initialize multi-factor verification. Please contact the administrator.'
                    ])
                    ->withInput();
            } catch (\Throwable $exception) {
                Log::error('Admin MFA challenge initialization failed (non-decryption).', [
                    'admin_id' => $admin->getKey(),
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
        } catch (\Throwable $exception) {
            Log::error('Admin login controller error - UNCAUGHT', [
                'admin_id' => $admin->id ?? null,
                'error' => $exception->getMessage(),
            ]);

            return back()
                ->withErrors([
                    'login' => 'An error occurred during login. Please try again later.'
                ])
                ->withInput();
        }
    }

    public function logout(Request $request)
    {
        // Capture the IDP access token BEFORE the session is destroyed, and only
        // when this administrator actually signed in through the IDP (the token
        // is written by AdminIdpLoginController on a successful IDP login).
        //
        // A password login never sets it, so this is a no-op there — the IDP
        // session is never touched for an account that did not use the IDP.
        $idpAccessToken = (string) $request->session()->get('admin.idp.access_token', '');

        Auth::guard('admin')->logout();

        if ($idpAccessToken !== '') {
            $this->revokeIdpSession($idpAccessToken);
        }

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    /**
     * Revoke the PUP IDP SSO session server-side.
     *
     * Uses the dedicated administrator OAuth client's credentials via
     * `services.idp.logout_path` (POST /auth/logout) with the access token as a
     * Bearer header — the same call the Student IDP logout makes.
     *
     * Deliberately best-effort: the local logout has already completed, so a
     * failure here (IDP unreachable, token already expired) is logged and
     * swallowed. The admin's PUPTracker session is gone either way, and the
     * error is never surfaced to the browser.
     */
    protected function revokeIdpSession(string $accessToken): void
    {
        $baseUrl = rtrim((string) config('services.idp.base_url', ''), '/');
        $logoutPath = (string) config('services.idp.logout_path', '/auth/logout');
        $clientId = trim((string) config('services.idp.admin_client_id', ''));
        $timeout = (int) config('services.idp.admin_timeout', config('services.idp.timeout', 15));

        if ($baseUrl === '' || $logoutPath === '') {
            return;
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->asJson()
                ->withToken($accessToken)
                ->timeout($timeout)
                ->post($logoutPath . ($clientId !== '' ? '?client_id=' . urlencode($clientId) : ''), array_filter([
                    'client_id' => $clientId,
                ], fn ($value) => $value !== null && $value !== ''));

            Log::info('Admin IDP logout request completed', [
                'status' => $response->status(),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Admin IDP logout failed', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Privacy-preserving email representation for logs (e.g. "a***@example.com").
     */
    protected function maskEmail(string $email): string
    {
        $email = trim($email);

        if ($email === '') {
            return '';
        }

        $parts = explode('@', $email, 2);
        $local = $parts[0] ?? '';
        $domain = $parts[1] ?? '';

        if ($local === '') {
            return $domain !== '' ? '*@' . $domain : '';
        }

        $localMasked = (string) $local[0] . str_repeat('*', max(1, strlen($local) - 1));

        return $domain !== '' ? $localMasked . '@' . $domain : $localMasked;
    }
}