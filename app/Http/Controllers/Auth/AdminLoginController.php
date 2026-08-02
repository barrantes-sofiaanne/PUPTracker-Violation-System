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
        try {
            Log::info('Admin login attempt started', ['email' => $request->input('email')]);
            
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            Log::info('Admin login validation passed');
            
            $admin = Admin::where('email', $request->email)->first();
            
            Log::info('Admin lookup result', ['found' => !is_null($admin), 'email' => $request->email]);

            if (!$admin || !Hash::check($request->password, $admin->password)) {
                Log::info('Admin login: invalid credentials');
                return back()
                    ->withErrors([
                        'login' => 'Invalid email or password.'
                    ])
                    ->withInput();
            }

            Log::info('Admin login: credentials verified', ['admin_id' => $admin->id]);

            try {
                $hasTrustedDevice = $mfaService->hasValidTrustedDevice($request, 'admin', (string) $admin->getKey());
                Log::info('Trusted device check', ['has_trusted' => $hasTrustedDevice]);
                
                if ($hasTrustedDevice) {
                    Auth::guard('admin')->login($admin);
                    $request->session()->regenerate();

                    return redirect()->route(
                        $admin->isItAdministrator()
                            ? 'admin.super-admin.dashboard'
                            : 'admin.dashboard'
                    )->with('show_login_announcement_modal', true);
                }
            } catch (\Throwable $e) {
                Log::warning('Trusted device check failed', ['error' => $e->getMessage()]);
            }

            if (empty($admin->email)) {
                return back()
                    ->withErrors([
                        'login' => 'Your account has no email address configured. Please contact an administrator.'
                    ])
                    ->withInput();
            }

            Log::info('Admin login: starting MFA challenge', ['admin_id' => $admin->id]);

            try {
                $mfaService->beginChallenge(
                    $request,
                    'admin',
                    (string) $admin->getKey(),
                    (string) $admin->email,
                    $admin->mfa_totp_secret,
                    (bool) $admin->mfa_totp_enabled
                );
                Log::info('Admin login: MFA challenge started successfully');
            } catch (\Throwable $exception) {
                Log::error('Admin MFA challenge initialization failed.', [
                    'admin_id' => $admin->getKey(),
                    'error' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ]);

                return back()
                    ->withErrors([
                        'login' => 'Unable to send verification code right now. Please try again later.'
                    ])
                    ->withInput();
            }

            return redirect()->route('mfa.verify.show')
                ->with('success', 'A verification code has been sent to your email.');
        } catch (\Throwable $exception) {
            Log::error('Admin login controller error - UNCAUGHT', [
                'email' => $request->input('email'),
                'error' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
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
        Auth::guard('admin')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}