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
        $action = (string) $request->input('action', 'verify');
        if ($action === 'back') {
            $mfaService->returnToMethodSelection($request);

            return redirect()->route('mfa.verify.show');
        }

        if ($action === 'select') {
            $validated = $request->validate([
                'method' => ['required', 'in:email,totp,backup'],
            ]);

            $result = $mfaService->selectMethod($request, $validated['method']);

            if (!($result['ok'] ?? false)) {
                return back()->withErrors([
                    'method' => $result['message'] ?? 'Unable to select verification method.',
                ])->withInput();
            }

            return redirect()->route('mfa.verify.show')->with('success', $result['message'] ?? 'Verification method selected.');
        }

        $pending = $mfaService->getPending($request);
        if (!$pending) {
            return redirect()->route('home');
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'method' => ['nullable', 'in:email,totp,backup'],
        ]);

        $selectedMethod = (string) ($pending['selected_method'] ?? '');
        $method = $validated['method'] ?? ($selectedMethod !== '' ? $selectedMethod : 'email');

        $result = $mfaService->verifyCode($request, $validated['code'], $method);

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

        $backupCodes = $mfaService->generateInitialBackupCodes($guard, $identifier);
        if (!empty($backupCodes)) {
            return redirect()
                ->route('totp.backup-codes', ['guard' => $guard])
                ->with('backup_codes', $backupCodes)
                ->with('success', 'Verification successful. Save your one-time backup codes.');
        }

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
