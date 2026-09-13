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

        // "Remember me" may be (re)chosen on the MFA screen. The final selection
        // made here takes precedence over whatever was ticked on the login form.
        // We persist it into the pending challenge and re-render the SAME stage,
        // so toggling the checkbox stays a non-destructive navigation step: no
        // code is validated and no authentication is established.
        if ($action === 'remember') {
            $pending = $mfaService->getPending($request);

            if (!$pending) {
                return redirect()->route('home');
            }

            // Guard allow-list: only the privileged (admin/security) challenge may
            // ever hold the remember flag. This mirrors the normalization applied
            // in MfaService::beginChallenge(), so a hand-crafted request cannot
            // enable "Remember me" on a student login.
            if (in_array((string) ($pending['guard'] ?? ''), ['admin', 'security'], true)) {
                $pending['remember'] = $request->boolean('remember');
                $request->session()->put(MfaService::SESSION_KEY, $pending);
            }

            return redirect()->route('mfa.verify.show');
        }

        if ($action === 'back') {
            $mfaService->returnToMethodSelection($request);

            return redirect()->route('mfa.verify.show');
        }

        if ($action === 'select') {
            $validated = $request->validate([
                'method' => ['required', 'in:email,totp,backup'],
            ]);

            // Carry the login screen's Remember Me choice across the method
            // selection step so it is not lost before the code is entered.
            $this->applyRememberSelection($request, $mfaService);

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
        // successfully. `$remember` is the final decision made on the MFA screen
        // (falling back to the login-screen choice when the MFA screen did not
        // post one). Laravel's session guard writes its recaller cookie here, so
        // Remember Me can never run ahead of, or bypass, MFA.
        Auth::guard($guard)->login($user, $remember);
        $request->session()->regenerate();
        $mfaService->clear($request);

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
     * Resolve the "Remember me" decision that must be applied after MFA.
     *
     * The MFA checkbox is the final authority: when the verification form posts
     * an explicit `remember` value, that value wins. When the MFA screen renders
     * no checkbox (e.g. a short password-only flow) we fall back to the choice
     * captured on the login screen, which MfaService stored in `remember`.
     *
     * Only the privileged admin/security challenges can ever remember; this
     * mirrors the normalization in MfaService::beginChallenge() so a crafted
     * request cannot enable remembering on a non-privileged guard.
     */
    private function resolveRememberSelection(Request $request, array $pending): bool
    {
        if (!in_array((string) ($pending['guard'] ?? ''), ['admin', 'security'], true)) {
            return false;
        }

        if ($request->has('remember')) {
            return $request->boolean('remember');
        }

        return (bool) ($pending['remember'] ?? false);
    }

    /**
     * Periodically (once every 10 minutes) carry the login screen's Remember Me
     * choice forward through the method-selection step. Storing it every time
     * would needlessly force a session write on each MFA page render.
     */
    private function applyRememberSelection(Request $request, MfaService $mfaService): void
    {
        $pending = $mfaService->getPending($request);

        if (!$pending || !in_array((string) ($pending['guard'] ?? ''), ['admin', 'security'], true)) {
            return;
        }

        $desired = $request->has('remember') ? $request->boolean('remember') : null;

        if ($desired === null || $desired === (bool) ($pending['remember'] ?? false)) {
            return;
        }

        if (($pending['remember_refreshed_at'] ?? 0) > now()->timestamp - 600) {
            return;
        }

        $pending['remember'] = $desired;
        $pending['remember_refreshed_at'] = now()->timestamp;
        $request->session()->put(MfaService::SESSION_KEY, $pending);
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
