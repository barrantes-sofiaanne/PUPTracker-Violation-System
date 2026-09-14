<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Support\MfaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AdminLoginController extends Controller
{
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

            $admin = Admin::where('email', $request->email)->first();

            if (!$admin || !Hash::check($request->password, $admin->password)) {
                Log::info('Admin login: invalid credentials');
                return back()
                    ->withErrors([
                        'login' => 'Invalid email or password.'
                    ])
                    ->withInput();
            }

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
                    $request->boolean('remember')
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