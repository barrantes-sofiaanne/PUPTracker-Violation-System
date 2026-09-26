<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordChangedNotification;
use App\Mail\PasswordResetLink;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Security;
use App\Models\User;
use App\Services\EmailIncidentService;
use App\Services\PasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{


    /**
     * Step 1 — "Forgot Password?" form (asks only for the registered email).
     */
    public function showForgotForm(string $guard): View
    {
        $config = $this->guardConfig($guard);

        return view('auth.forgot-password', [
            'guard' => $guard,
            'moduleLabel' => $config['module_label'],
        ]);
    }

    /**
     * Step 2 — Accept the email and (if an account exists) send a reset link.
     *
     * SHARED PORTAL: `/security/login` serves BOTH Security Personnel and OSS
     * Staff, so a reset requested from that page must reach the right account
     * type. OSS Staff are ADMIN accounts, so when no Security row matches the
     * email we fall back to the Administrator table — but ONLY for an account
     * that actually holds the OSS Staff role. A regular Administrator or an IT
     * Administrator is NOT recoverable through this portal (they use
     * `admin.login`), so the shared page cannot be used to enumerate or reset
     * their accounts.
     *
     * Deliberately does not reveal whether the email has an account: the same
     * generic success message is shown regardless.
     */
    public function sendResetLink(Request $request, string $guard, PasswordResetService $passwordResetService): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $config = $this->guardConfig($guard);
        $email = strtolower(trim($validated['email']));

        // The ledger the token is filed under. Normally the URL's guard, but an
        // OSS Staff account must be reset as an ADMIN (that is the account it
        // really is), while still being told to return to the shared portal.
        $accountGuard = $guard;

        $account = $passwordResetService->findAccountByEmail($guard, $email);

        if ($account === null && $guard === 'security') {
            $candidate = Admin::query()->where('email', $email)->first();

            if ($candidate instanceof Admin && $candidate->isOssStaff()) {
                $account = $candidate;
                $accountGuard = 'admin';
            }
        }

        if ($account === null) {
            $this->audit(
                $guard,
                null,
                'PASSWORD_RESET_REQUESTED',
                'Password Reset',
                'Password reset requested for an unregistered email.',
                $request
            );

            return back()->with('status', 'If an account is associated with this email address, a password reset link has been sent.');
        }

        $result = $passwordResetService->createReset($accountGuard, $account);
        $token = $result['token'];

        $this->sendResetLinkMail($accountGuard, $email, $token);

        $this->audit(
            $accountGuard,
            $account->getKey(),
            'PASSWORD_RESET_REQUESTED',
            'Password Reset',
            'Password reset link generated and emailed to the registered account.',
            $request
        );

        return back()->with('status', 'If an account is associated with this email address, a password reset link has been sent.');
    }

    /**
     * Step 6/7 — Open the reset link.
     *
     * The token identifies the recovery attempt; once validated it resolves to
     * the account. Privileged accounts (admin/security) require an OTP step
     * before the new-password form is revealed.
     */
    public function showResetForm(Request $request, string $guard, string $token, PasswordResetService $passwordResetService): View|RedirectResponse
    {
        $config = $this->guardConfig($guard);
        $reset = $passwordResetService->findValidReset($guard, $token);

        if ($reset === null) {
            return redirect()
                ->route($this->loginRouteFor($guard))
                ->withErrors(['link' => 'Invalid or expired password reset link. Please request a new one.']);
        }

        $account = $passwordResetService->resolveAccount($guard, $reset);

        if ($account === null) {
            return redirect()
                ->route($this->loginRouteFor($guard))
                ->withErrors(['link' => 'Invalid or expired password reset link. Please request a new one.']);
        }

        $this->audit(
            $guard,
            $account->getKey(),
            'PASSWORD_RESET_VERIFIED',
            'Password Reset',
            'Password reset link opened and validated.',
            $request
        );

       

        return view('auth.reset-password', [
            'guard' => $guard,
            'moduleLabel' => $config['module_label'],
            'token' => $token,
            'email' => (string) $account->email,
        ]);
    }

    /**
     * Step 8 — Verify the OTP for a privileged account, then reveal the
     * new-password form.
     */


    /**
     * Steps 9–13 — Set a new password for the verified recovery attempt.
     */
    public function reset(Request $request, string $guard, PasswordResetService $passwordResetService): RedirectResponse
    {
        $config = $this->guardConfig($guard);

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(12)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ]);

        $reset = $passwordResetService->findValidReset($guard, $validated['token']);

        if ($reset === null) {
            return redirect()
                ->route($this->loginRouteFor($guard))
                ->withErrors(['link' => 'Invalid or expired password reset link. Please request a new one.']);
        }

        $account = $passwordResetService->resolveAccount($guard, $reset);

        if ($account === null) {
            return redirect()
                ->route($this->loginRouteFor($guard))
                ->withErrors(['link' => 'Invalid or expired password reset link. Please request a new one.']);
        }

      

        // Hash the new password; never store/log the plaintext.
        $passwordColumn = $config['password_column'];
        $account->setAttribute($passwordColumn, Hash::make($validated['password']));
        $account->save();

        // Invalidate the token immediately (first click valid, second click invalid).
        $passwordResetService->invalidateReset($reset);

        // Revoke existing sessions so every device must re-authenticate.
        $this->revokeSessions($guard, $account);



        $this->audit(
            $guard,
            $account->getKey(),
            'PASSWORD_RESET_COMPLETED',
            'Password Reset',
            'Password reset completed successfully; existing sessions revoked.',
            $request
        );

        // Send the password-changed security notification.
        $this->sendPasswordChangedMail($guard, (string) $account->email);

        return redirect()
            ->route($this->loginRouteFor($guard, $account))
            ->with('status', 'Your password has been changed successfully. Please log in.');
    }

    /**
     * The login page to return to for a guard, aware of WHICH account it is.
     *
     * An OSS Staff account is an ADMIN account that signs in through the SHARED
     * Security + OSS Staff portal, so it is returned there instead of the
     * Administrator login it never uses. A regular Administrator or IT
     * Administrator still returns to `admin.login`.
     */
private function loginRouteFor(
    string $guard,
    Admin|Security|User|null $account = null
): string    {
        if ($guard === 'admin') {
            return \App\Support\Auth\PortalLoginRedirect::forGuard('admin', $account);
        }

        return $this->guardConfig($guard)['login_route'];
    }

    // ------------------------------------------------------------------
    // Email sending
    // ------------------------------------------------------------------

    private function sendResetLinkMail(string $guard, string $email, string $token): void
    {
        $url = route('password.reset.form', [
            'guard' => $guard,
            'token' => $token,
        ]);

        try {
            Mail::to($email)->send(new PasswordResetLink($url, PasswordResetService::TOKEN_LIFETIME_MINUTES));
        } catch (\Throwable $exception) {
            Log::error('Unable to send password reset link email.', [
                'email' => $email,
                'guard' => $guard,
                'error' => $exception->getMessage(),
            ]);

            app(EmailIncidentService::class)->record(
                emailType: 'Password Reset',
                errorMessage: $exception->getMessage(),
                relatedRecordType: 'Password Reset',
                relatedRecordId: $email,
            );
        }
    }

    private function sendPasswordChangedMail(string $guard, string $email): void
    {
        try {
            Mail::to($email)->send(new PasswordChangedNotification());
        } catch (\Throwable $exception) {
            Log::error('Unable to send password-changed notification email.', [
                'email' => $email,
                'guard' => $guard,
                'error' => $exception->getMessage(),
            ]);

            app(EmailIncidentService::class)->record(
                emailType: 'Password Changed Notification',
                errorMessage: $exception->getMessage(),
                relatedRecordType: 'Password Reset',
                relatedRecordId: $email,
            );
        }
    }

    // ------------------------------------------------------------------
    // Session revocation
    // ------------------------------------------------------------------

    private function revokeSessions(string $guard, Admin|Security|User $account): void
    {
        try {
            if (Schema::hasTable('sessions')) {
                DB::table('sessions')
                    ->where('user_id', $account->getKey())
                    ->delete();
            }

            // Rotate the "remember me" token as well. Deleting the DB sessions
            // kills live sessions, but the remember token also lives in each
            // browser's recaller cookie; without rotation a stolen (or simply
            // pre-existing) remember cookie would stay valid after a password
            // reset. Rotating it here invalidates every trusted device so the
            // account must be logged into again (password + MFA) everywhere.
            // (For students the remember token is disabled — the set is a no-op;
            // and if the table predates the remember_token column we skip it.)
            $rememberTokenName = $account->getRememberTokenName();
            $table = $account->getTable();

            if (
                $rememberTokenName !== ''
                && Schema::hasTable($table)
                && Schema::hasColumn($table, $rememberTokenName)
            ) {
                $account->setRememberToken(Str::random(60));
                $account->save();
            }

            Log::info('Sessions and remember tokens revoked after password reset.', [
                'guard' => $guard,
                'account_id' => $account->getKey(),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Unable to revoke sessions after password reset.', [
                'guard' => $guard,
                'account_id' => $account->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Audit + helpers
    // ------------------------------------------------------------------

    private function audit(string $guard, ?int $accountId, string $action, string $module, string $description, Request $request): void
    {
        try {
            AuditLog::create([
                'actor_type' => $guard,
                'actor_id' => $accountId,
                'action' => $action,
                'module' => $module,
                'description' => $description . sprintf(
                    ' [%s:%s | %s | %s]',
                    $guard,
                    $accountId ?? 'guest',
                    $request->ip() ?? 'unknown',
                    $this->shortenUserAgent((string) $request->userAgent())
                ),
                'created_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Unable to write password-reset audit log.', [
                'action' => $action,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function shortenUserAgent(string $userAgent): string
    {
        $clean = preg_replace('/\s+/', ' ', trim($userAgent)) ?? '';

        return strlen($clean) > 120 ? substr($clean, 0, 120) . '…' : $clean;
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($name === '' || $domain === '') {
            return '***';
        }

        $visiblePrefix = substr($name, 0, 1);
        $visibleSuffix = strlen($name) > 2 ? substr($name, -1) : '';
        $maskedMid = str_repeat('*', max(1, strlen($name) - 2));

        return $visiblePrefix . $maskedMid . $visibleSuffix . '@' . $domain;
    }

    private function guardConfig(string $guard): array
    {
        return match ($guard) {
            'student' => [
                'model' => User::class,
                'password_column' => 'password_hash',
                'login_route' => 'student.login',
                'module_label' => 'Student',
            ],
            'admin' => [
                'model' => Admin::class,
                'password_column' => 'password',
                'login_route' => 'admin.login',
                'module_label' => 'Administrator',
            ],
            'security' => [
                'model' => Security::class,
                'password_column' => 'password',
                'login_route' => 'security.login',
                // This guard backs the SHARED portal, so the label names both
                // account types that may recover here.
                'module_label' => 'Security and OSS Staff',
            ],
            default => abort(404),
        };
    }
}
