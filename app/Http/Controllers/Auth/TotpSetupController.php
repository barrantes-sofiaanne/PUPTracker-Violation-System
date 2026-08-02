<?php

namespace App\Http\Controllers\Auth;

use App\Support\MfaSchema;
use App\Support\MfaService;
use App\Support\TotpService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class TotpSetupController extends Controller
{
    public function __construct(
        private TotpService $totpService,
        private MfaService $mfaService,
    ) {}

    /**
     * Show TOTP setup page
     */
    public function show(Request $request): View|RedirectResponse
    {
        $guard = $request->query('guard', 'admin');
        $allowedGuards = ['admin', 'security'];

        if (!in_array($guard, $allowedGuards, true)) {
            return redirect()->back()->withErrors(['Invalid guard']);
        }

        $user = auth()->guard($guard)->user();

        if (!$user) {
            return redirect()->route($this->loginRouteForGuard($guard));
        }

        if ($user->mfa_totp_enabled ?? false) {
            return redirect()->route($this->dashboardRouteForGuard($guard, $user))->with('info', 'TOTP is already enabled on your account');
        }

        // Generate new secret if not in session
        $secret = session("totp_setup_secret_{$guard}");

        if (!$secret) {
            $secret = $this->totpService->generateSecret();
            session(["totp_setup_secret_{$guard}" => $secret]);
        }

        $qrCode = $this->totpService->getQRCode(
            $user->email,
            'PUPTracker Admin',
            $secret
        );

        return view('auth.totp-setup', [
            'guard' => $guard,
            'qrCode' => $qrCode,
            'secret' => $secret,
            'manualUrl' => $this->totpService->getOtpAuthUrl($user->email, 'PUPTracker Admin', $secret),
        ]);
    }

    /**
     * Verify and enable TOTP
     */
    public function verify(Request $request): RedirectResponse
    {
        $guard = $request->input('guard', 'admin');
        $allowedGuards = ['admin', 'security'];

        if (!in_array($guard, $allowedGuards, true)) {
            return redirect()->back()->withErrors(['Invalid guard']);
        }

        $user = auth()->guard($guard)->user();

        if (!$user) {
            return redirect()->route($this->loginRouteForGuard($guard));
        }

        /** @var Model&Authenticatable $user */

        $request->validate([
            'totp_code' => 'required|string|size:6',
        ], [
            'totp_code.required' => 'Verification code is required',
            'totp_code.size' => 'Code must be 6 digits',
        ]);

        $secret = session("totp_setup_secret_{$guard}");

        if (!$secret) {
            return redirect()->back()->withErrors(['Session expired. Please try again.']);
        }

        // Verify the code
        if (!$this->totpService->verify($secret, $request->input('totp_code'))) {
            Log::warning('TOTP verification failed during setup', [
                'guard' => $guard,
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return redirect()->back()->withErrors(['Invalid verification code. Please try again.']);
        }

        // Save to database
        $user->mfa_totp_secret = $secret;
        $user->mfa_totp_enabled = true;

        $backupCodes = [];
        $supportsBackupCodes = MfaSchema::supportsBackupCodes($user);

        if ($supportsBackupCodes) {
            $backupCodes = $this->totpService->generateBackupCodes();
            $hashedCodes = $this->totpService->hashBackupCodes($backupCodes);

            $user->mfa_backup_codes = json_encode($hashedCodes);
            $user->mfa_backup_codes_used = json_encode([]);
        }

        try {
            $user->save();
        } catch (QueryException $exception) {
            if (!$supportsBackupCodes || !MfaSchema::isMissingColumnException($exception)) {
                throw $exception;
            }

            MfaSchema::forgetBackupCodeAttributes($user);
            $supportsBackupCodes = false;
            $backupCodes = [];

            $user->save();
        }

        session()->forget("totp_setup_secret_{$guard}");

        Log::info('TOTP enabled successfully', [
            'guard' => $guard,
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        if (!$supportsBackupCodes) {
            Log::warning('TOTP enabled without backup codes because MFA backup-code columns are missing', [
                'guard' => $guard,
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return redirect()->route($this->dashboardRouteForGuard($guard, $user))
                ->with('warning', 'TOTP was enabled, but backup codes are unavailable until the MFA backup-code migration is run.');
        }

        return redirect()->route('totp.backup-codes', ['guard' => $guard])
            ->with('backup_codes', $backupCodes)
            ->with('success', 'TOTP enabled successfully! Save your backup codes now.');
    }

    /**
     * Display backup codes
     */
    public function showBackupCodes(Request $request): View|RedirectResponse
    {
        $guard = $request->query('guard', 'admin');
        $backupCodes = session('backup_codes');
        $user = auth()->guard($guard)->user();

        if (!$backupCodes) {
            return redirect()->route($this->dashboardRouteForGuard($guard, $user))->with('info', 'No backup codes in session');
        }

        return view('auth.totp-backup-codes', [
            'guard' => $guard,
            'backupCodes' => $backupCodes,
        ]);
    }

    /**
     * Confirm backup codes saved
     */
    public function confirmBackupCodes(Request $request): RedirectResponse
    {
        $guard = $request->input('guard', 'admin');
        $user = auth()->guard($guard)->user();

        session()->forget('backup_codes');

        Log::info('TOTP backup codes acknowledged', [
            'guard' => $guard,
            'user_id' => auth()->guard($guard)->id(),
        ]);

        return redirect()->route($this->dashboardRouteForGuard($guard, $user))
            ->with('success', 'TOTP setup complete! You can now use your authenticator app to log in.');
    }

    /**
     * Disable TOTP
     */
    public function disable(Request $request): RedirectResponse
    {
        $guard = $request->input('guard', 'admin');
        $user = auth()->guard($guard)->user();

        if (!$user) {
            return redirect()->route($this->loginRouteForGuard($guard));
        }

        /** @var Model&Authenticatable $user */

        $request->validate([
            'password' => 'required|string',
        ]);

        // Verify password
        if (!password_verify($request->input('password'), $user->password)) {
            return redirect()->back()->withErrors(['Invalid password']);
        }

        $user->mfa_totp_secret = null;
        $user->mfa_totp_enabled = false;

        if (MfaSchema::supportsBackupCodes($user)) {
            $user->mfa_backup_codes = null;
            $user->mfa_backup_codes_used = null;
        }

        try {
            $user->save();
        } catch (QueryException $exception) {
            if (!MfaSchema::isMissingColumnException($exception)) {
                throw $exception;
            }

            MfaSchema::forgetBackupCodeAttributes($user);
            $user->save();
        }

        Log::warning('TOTP disabled', [
            'guard' => $guard,
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return redirect()->back()->with('success', 'TOTP has been disabled.');
    }

    private function loginRouteForGuard(string $guard): string
    {
        return match ($guard) {
            'admin' => 'admin.login',
            'security' => 'security.login',
            default => 'student.login',
        };
    }

    private function dashboardRouteForGuard(string $guard, ?Authenticatable $user): string
    {
        if ($guard === 'admin') {
            return method_exists($user, 'isItAdministrator') && $user->isItAdministrator()
                ? 'admin.super-admin.dashboard'
                : 'admin.dashboard';
        }

        return $guard === 'security'
            ? 'security.dashboard'
            : 'student.dashboard';
    }
}
