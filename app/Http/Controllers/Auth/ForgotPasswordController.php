<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordChangedNotification;
use App\Mail\PasswordResetLink;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\PasswordReset;
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
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    /** Session key holding the OTP step during a privileged password reset. */
    private const RESET_OTP_SESSION = 'password.reset.otp';

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

        $account = $passwordResetService->findAccountByEmail($guard, $email);

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

        $result = $passwordResetService->createReset($guard, $account);
        $token = $result['token'];

        $this->sendResetLinkMail($guard, $email, $token);

        $this->audit(
            $guard,
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
                ->route($config['login_route'])
                ->withErrors(['link' => 'Invalid or expired password reset link. Please request a new one.']);
        }

        $account = $passwordResetService->resolveAccount($guard, $reset);

        if ($account === null) {
            return redirect()
                ->route($config['login_route'])
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

        $privileged = $passwordResetService->isPrivileged($guard);

        if ($privileged) {
            $this->stageOtp($request, $token, $reset, $account);

            return view('auth.reset-otp', [
                'guard' => $guard,
                'moduleLabel' => $config['module_label'],
                'token' => $token,
                'emailMasked' => $this->maskEmail((string) $account->email),
            ]);
        }

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
    public function verifyOtp(Request $request, string $guard, PasswordResetService $passwordResetService): RedirectResponse|View
    {
        $config = $this->guardConfig($guard);
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'code' => ['required', 'string', 'max:20'],
        ]);

        $staged = $request->session()->get(self::RESET_OTP_SESSION);

        if (
            !is_array($staged)
            || ($staged['guard'] ?? null) !== $guard
            || ($staged['token'] ?? null) !== $validated['token']
        ) {
            return redirect()
                ->route($config['login_route'])
                ->withErrors(['link' => 'Invalid or expired password reset link. Please request a new one.']);
        }

        $accountId = (int) ($staged['account_id'] ?? 0);
        $attempts = (int) ($staged['attempts'] ?? 0) + 1;
        $staged['attempts'] = $attempts;
        $request->session()->put(self::RESET_OTP_SESSION, $staged);

        if ($attempts > 5) {
            $request->session()->forget(self::RESET_OTP_SESSION);

            $this->audit(
                $guard,
                $accountId,
                'PASSWORD_RESET_OTP_FAILED',
                'Password Reset',
                'Password reset OTP verification locked after repeated failures.',
                $request
            );

            return redirect()
                ->route($config['login_route'])
                ->withErrors(['link' => 'Too many incorrect attempts. Please request a new password reset link.']);
        }

        if (
            empty($staged['code_hash'])
            || empty($staged['expires_at'])
            || now()->timestamp > (int) $staged['expires_at']
            || !Hash::check(trim($validated['code']), (string) $staged['code_hash'])
        ) {
            $this->audit(
                $guard,
                $accountId,
                'PASSWORD_RESET_OTP_FAILED',
                'Password Reset',
                'Password reset OTP verification failed.',
                $request
            );

            return back()->withErrors(['code' => 'Invalid or expired verification code. Please try again.'])->withInput();
        }

        $staged['otp_verified'] = true;
        $request->session()->put(self::RESET_OTP_SESSION, $staged);

        $this->audit(
            $guard,
            $accountId,
            'PASSWORD_RESET_OTP_VERIFIED',
            'Password Reset',
            'Password reset OTP verified successfully.',
            $request
        );

        return view('auth.reset-password', [
            'guard' => $guard,
            'moduleLabel' => $config['module_label'],
            'token' => $validated['token'],
            'email' => (string) ($staged['email'] ?? ''),
        ]);
    }

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
                ->route($config['login_route'])
                ->withErrors(['link' => 'Invalid or expired password reset link. Please request a new one.']);
        }

        $account = $passwordResetService->resolveAccount($guard, $reset);

        if ($account === null) {
            return redirect()
                ->route($config['login_route'])
                ->withErrors(['link' => 'Invalid or expired password reset link. Please request a new one.']);
        }

        // Privileged accounts must have completed the OTP step in this session
        // before the password may be changed.
        if ($passwordResetService->isPrivileged($guard)) {
            $staged = $request->session()->get(self::RESET_OTP_SESSION);

            if (
                !is_array($staged)
                || ($staged['guard'] ?? null) !== $guard
                || ($staged['token'] ?? null) !== $validated['token']
                || !($staged['otp_verified'] ?? false)
                || (int) ($staged['account_id'] ?? 0) !== (int) $account->getKey()
            ) {
                return redirect()
                    ->route($config['login_route'])
                    ->withErrors(['link' => 'Invalid or expired password reset link. Please request a new one.']);
            }
        }

        // Hash the new password; never store/log the plaintext.
        $passwordColumn = $config['password_column'];
        $account->setAttribute($passwordColumn, Hash::make($validated['password']));
        $account->save();

        // Invalidate the token immediately (first click valid, second click invalid).
        $passwordResetService->invalidateReset($reset);

        // Revoke existing sessions so every device must re-authenticate.
        $this->revokeSessions($guard, $account);

        // Clear the OTP staging data.
        $request->session()->forget(self::RESET_OTP_SESSION);

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
            ->route($config['login_route'])
            ->with('status', 'Your password has been changed successfully. Please log in.');
    }

    // ------------------------------------------------------------------
    // OTP staging helpers
    // ------------------------------------------------------------------

    private function stageOtp(Request $request, string $token, PasswordReset $reset, Admin|Security|User $account): void
    {
        $code = (string) random_int(100000, 999999);

        $request->session()->put(self::RESET_OTP_SESSION, [
            'guard' => $reset->guard,
            'token' => $token,
            'account_id' => $account->getKey(),
            'email' => (string) $account->email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(5)->timestamp,
            'attempts' => 0,
            'otp_verified' => false,
        ]);

        $this->sendOtpMail((string) $account->email, $code);
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

    private function sendOtpMail(string $email, string $code): void
    {
        try {
            Mail::to($email)->send(new \App\Mail\OtpCode($code, expiryMinutes: 5));
        } catch (\Throwable $exception) {
            Log::error('Unable to send password reset OTP email.', [
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);

            app(EmailIncidentService::class)->record(
                emailType: 'Password Reset OTP',
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
            if (!Schema::hasTable('sessions')) {
                return;
            }

            DB::table('sessions')
                ->where('user_id', $account->getKey())
                ->delete();

            Log::info('Sessions revoked after password reset.', [
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
                'module_label' => 'Security',
            ],
            default => abort(404),
        };
    }
}
