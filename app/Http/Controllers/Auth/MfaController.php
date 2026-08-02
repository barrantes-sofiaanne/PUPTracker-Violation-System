<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Security;
use App\Models\User;
use App\Support\MfaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MfaController extends Controller
{
    public function show(Request $request, MfaService $mfaService)
    {
        if (!$mfaService->hasPending($request)) {
            return redirect()->route('home');
        }

        $pending = $mfaService->getPending($request);

        return view('auth.mfa-verify', compact('pending'));
    }

    public function verify(Request $request, MfaService $mfaService): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
            'method' => ['nullable', 'in:email,totp'],
        ]);

        $result = $mfaService->verifyCode($request, $validated['code'], $validated['method'] ?? 'email');

        if (!($result['valid'] ?? false)) {
            return back()->withErrors([
                'code' => $result['message'] ?? 'Unable to verify code.',
            ])->withInput();
        }

        $pending = $result['pending'];
        $guard = (string) $pending['guard'];
        $identifier = (string) $pending['identifier'];

        $user = $this->resolveUserForGuard($guard, $identifier);

        if (!$user) {
            $mfaService->clear($request);

            return redirect()->route('home')->with('error', 'Unable to complete verification. Please login again.');
        }

        Auth::guard($guard)->login($user);
        $request->session()->regenerate();
        $mfaService->clear($request);

        return redirect()
            ->route($this->redirectRouteForGuard($guard, $user))
            ->with('show_login_announcement_modal', true);
    }

    public function resend(Request $request, MfaService $mfaService): RedirectResponse
    {
        $result = $mfaService->resend($request);

        if (!($result['ok'] ?? false)) {
            return back()->with('error', $result['message'] ?? 'Unable to resend code.');
        }

        return back()->with('success', $result['message']);
    }

    public function cancel(Request $request, MfaService $mfaService): RedirectResponse
    {
        $pending = $mfaService->getPending($request);
        $mfaService->logCancellation($request);
        $mfaService->clear($request);

        $guard = (string) ($pending['guard'] ?? 'student');

        return redirect()->route($this->loginRouteForGuard($guard));
    }

    private function resolveUserForGuard(string $guard, string $identifier): Admin|Security|User|null
    {
        return match ($guard) {
            'admin' => Admin::find($identifier),
            'security' => Security::find($identifier),
            'student' => User::where('student_number', $identifier)->first(),
            default => null,
        };
    }

    private function redirectRouteForGuard(string $guard, Admin|Security|User $user): string
    {
        return match ($guard) {
            'admin' => $user instanceof Admin && $user->isItAdministrator()
                ? 'admin.super-admin.dashboard'
                : 'admin.dashboard',
            'security' => 'security.dashboard',
            default => 'student.dashboard',
        };
    }

    private function loginRouteForGuard(string $guard): string
    {
        return match ($guard) {
            'admin' => 'admin.login',
            'security' => 'security.login',
            default => 'student.login',
        };
    }
}
