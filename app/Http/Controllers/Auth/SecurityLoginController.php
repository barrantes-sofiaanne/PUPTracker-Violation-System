<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Security;
use App\Support\MfaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class SecurityLoginController extends Controller
{
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

        // PART 17 — 3 failed credential attempts, enforced per account
        // (see AdminLoginController for the rationale; the rule and the
        // threshold are the same for both privileged guards).
        $throttleKey = 'security-login:' . sha1(
            strtolower(trim((string) $request->input('email'))) . '|' . $request->ip()
        );

        if (RateLimiter::tooManyAttempts($throttleKey, AdminLoginController::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            Log::warning('Security login blocked: too many failed attempts', [
                'retry_after_seconds' => $seconds,
            ]);

            AuditLog::create([
                'actor_type' => 'system',
                'actor_id' => null,
                'action' => 'Login Blocked',
                'module' => 'Authentication',
                'description' => 'Security login blocked after ' . AdminLoginController::MAX_LOGIN_ATTEMPTS . ' failed attempts.',
                'created_at' => now(),
            ]);

            return back()
                ->withErrors([
                    'login' => 'Too many failed login attempts. Please try again in ' . (int) ceil($seconds / 60) . ' minute(s).',
                ])
                ->withInput();
        }

        $security = Security::where('email', $request->email)->first();

        if (!$security || !Hash::check($request->password, $security->password)) {
            RateLimiter::hit($throttleKey, AdminLoginController::LOGIN_LOCKOUT_SECONDS);

            $remaining = max(0, AdminLoginController::MAX_LOGIN_ATTEMPTS - RateLimiter::attempts($throttleKey));

            return back()
                ->withErrors([
                    'login' => $remaining > 0
                        ? 'Invalid email or password. ' . $remaining . ' attempt(s) remaining.'
                        : 'Too many failed login attempts. Please try again in ' . (int) ceil(AdminLoginController::LOGIN_LOCKOUT_SECONDS / 60) . ' minute(s).',
                ])
                ->withInput();
        }

        // PART 17 — success clears the failed-attempt counter.
        RateLimiter::clear($throttleKey);

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