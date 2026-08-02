<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Security;
use App\Support\MfaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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

        $security = Security::where('email', $request->email)->first();

        if (!$security || !Hash::check($request->password, $security->password)) {
            return back()
                ->withErrors([
                    'login' => 'Invalid email or password.'
                ])
                ->withInput();
        }

        if (empty($security->email)) {
            return back()
                ->withErrors([
                    'login' => 'Your account has no email address configured. Please contact an administrator.'
                ])
                ->withInput();
        }

        try {
            $mfaService->beginChallenge(
                $request,
                'security',
                (string) $security->getKey(),
                (string) $security->email,
                $security->mfa_totp_secret,
                (bool) $security->mfa_totp_enabled,
                $security->mfa_backup_codes,
                $security->mfa_backup_codes_used
            );
        } catch (\Throwable $exception) {
            Log::error('Security MFA challenge initialization failed.', [
                'security_id' => $security->getKey(),
                'error' => $exception->getMessage(),
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