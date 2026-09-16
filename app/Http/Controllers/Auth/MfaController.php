<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Security;
use App\Models\User;
use App\Support\Auth\SessionExpiryNotice;
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

        // NOTE: there is deliberately no standalone "remember" action. A
        // dedicated endpoint that stored the flag WITHOUT verifying a code would
        // let a crafted request request a persistent device outside the MFA
        // verification step. The flag is therefore read only from the
        // verification POST, immediately before authentication is applied.

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

        // The explicit Remember Me choice submitted with the verification form
        // must be captured BEFORE verifyCode(), because a successful
        // verification clears the pending session we would otherwise read from.
        $remember = $this->resolveRememberSelection($request, $pending);

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

        // Apply persistent authentication ONLY now — after MFA has been verified
        // successfully. An unchecked box is simply absent from the payload, so
        // `$remember` is false by default and no trusted device is created.
        // Laravel's session guard writes its recaller cookie here, so Remember Me
        // can never run ahead of, or bypass, MFA.
        Auth::guard($guard)->login($user, $remember);
        $request->session()->regenerate();
        $mfaService->clear($request);

        // Drop any pending expiry notice: it describes a PAST session, and the
        // user is authenticated again now, so it must never surface later.
        SessionExpiryNotice::clear($request);

        // Show the privacy/terms consent modal first on every login.
        $request->session()->put('show_privacy_consent_modal', true);

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

    /**
     * Resolve the "Remember me" decision applied after a SUCCESSFUL MFA.
     *
     * This is the ONLY place the flag is read, and it is called only on the
     * verification path — so a remembered device can never be created before a
     * code has actually been verified. An unchecked box does not appear in the
     * submitted payload at all, which reads as false; that is the required
     * default.
     *
     * Only the privileged admin/security challenges may ever remember. The
     * allow-list mirrors the normalization in MfaService::beginChallenge(), so a
     * crafted request cannot enable remembering on a non-privileged guard.
     *
     * The feature is currently DISABLED: this returns false unconditionally, so
     * a hand-crafted POST carrying `remember=1` cannot create a persistent
     * credential even though the checkbox is not rendered. Hiding the input in
     * the view is therefore not the only control.
     */
    private function resolveRememberSelection(Request $request, array $pending): bool
    {
        if (! (bool) config('auth.features.remember_device', false)) {
            return false;
        }

        if (!in_array((string) ($pending['guard'] ?? ''), ['admin', 'security'], true)) {
            return false;
        }

        return $request->boolean('remember');
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
