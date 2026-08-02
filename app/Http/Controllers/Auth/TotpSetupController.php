<?php

namespace App\Http\Controllers\Auth;

use App\Support\MfaService;
use App\Support\TotpService;
use Illuminate\Contracts\Auth\Authenticatable;
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
            return redirect()->route('login');
        }

        if ($user->mfa_totp_enabled ?? false) {
            return redirect()->route('dashboard')->with('info', 'TOTP is already enabled on your account');
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
            return redirect()->route('login');
        }

        /** @var \Illuminate\Database\Eloquent\Model $user */

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

        // Generate backup codes
        $backupCodes = $this->totpService->generateBackupCodes();
        $hashedCodes = $this->totpService->hashBackupCodes($backupCodes);

        // Save to database
        $user->mfa_totp_secret = $secret;
        $user->mfa_totp_enabled = true;
        $user->mfa_backup_codes = json_encode($hashedCodes);
        $user->mfa_backup_codes_used = json_encode([]);
        $user->save();

        session()->forget("totp_setup_secret_{$guard}");

        Log::info('TOTP enabled successfully', [
            'guard' => $guard,
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

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

        if (!$backupCodes) {
            return redirect()->route('dashboard')->with('info', 'No backup codes in session');
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

        session()->forget('backup_codes');

        Log::info('TOTP backup codes acknowledged', [
            'guard' => $guard,
            'user_id' => auth()->guard($guard)->id(),
        ]);

        return redirect()->route('dashboard')
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
            return redirect()->route('login');
        }

        /** @var \Illuminate\Database\Eloquent\Model $user */

        $request->validate([
            'password' => 'required|string',
        ]);

        // Verify password
        if (!password_verify($request->input('password'), $user->password)) {
            return redirect()->back()->withErrors(['Invalid password']);
        }

        $user->mfa_totp_secret = null;
        $user->mfa_totp_enabled = false;
        $user->mfa_backup_codes = null;
        $user->mfa_backup_codes_used = null;
        $user->save();

        Log::warning('TOTP disabled', [
            'guard' => $guard,
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return redirect()->back()->with('success', 'TOTP has been disabled.');
    }
}
