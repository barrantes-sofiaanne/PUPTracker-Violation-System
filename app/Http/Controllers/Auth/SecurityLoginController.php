<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Security;
use App\Support\Auth\LoginAttemptLimiter;
use App\Support\MfaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class SecurityLoginController extends Controller
{
    /**
     * The module key used for the login-attempt limiter and its config.
     *
     * A lockout is scoped to this module, so a Security lockout does not block
     * the Administrator login.
     */
    public const LOGIN_MODULE = 'security';

    public function showLoginForm()
    {
        return view('auth.security-login');
    }

    public function login(Request $request, MfaService $mfaService)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Failed credential attempts are bounded per account using the SAME
        // shared rule as the Administrator module. The limiter keys on the
        // module name, so a Security lockout can never block the Administrator
        // login (and vice versa). See AdminLoginController for the rationale.
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

        $security = Security::where('email', $request->email)->first();

        if (!$security || !Hash::check($request->password, $security->password)) {
            LoginAttemptLimiter::recordFailure(self::LOGIN_MODULE, $request);

            return back()
                ->withErrors([
                    'login' => LoginAttemptLimiter::failureMessage(self::LOGIN_MODULE, $request),
                ])
                ->withInput();
        }

        // A SUCCESSFUL credential check clears the failed-attempt counter.
        LoginAttemptLimiter::clear(self::LOGIN_MODULE, $request);

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

    public function logout(Request $request)
    {
        Auth::guard('security')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('security.login');
    }
}