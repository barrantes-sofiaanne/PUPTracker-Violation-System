<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Support\MfaService;
use Illuminate\Http\Request;
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
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $admin = Admin::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return back()
                ->withErrors([
                    'login' => 'Invalid email or password.'
                ])
                ->withInput();
        }

        if ($mfaService->hasValidTrustedDevice($request, 'admin', (string) $admin->getKey())) {
            Auth::guard('admin')->login($admin);
            $request->session()->regenerate();

            return redirect()->route(
                $admin->isItAdministrator()
                    ? 'admin.super-admin.dashboard'
                    : 'admin.dashboard'
            )->with('show_login_announcement_modal', true);
        }

        if (empty($admin->email)) {
            return back()
                ->withErrors([
                    'login' => 'Your account has no email address configured. Please contact an administrator.'
                ])
                ->withInput();
        }

        try {
            $mfaService->beginChallenge(
                $request,
                'admin',
                (string) $admin->getKey(),
                (string) $admin->email,
                $admin->mfa_totp_secret,
                (bool) $admin->mfa_totp_enabled
            );
        } catch (\Throwable $exception) {
            Log::error('Admin MFA challenge initialization failed.', [
                'admin_id' => $admin->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return back()
                ->withErrors([
                    'login' => 'Unable to send verification code right now. Please try again later.'
                ])
                ->withInput();
        }

        return redirect()->route('mfa.verify.show')
            ->with('success', 'A verification code has been sent to your email.');
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}