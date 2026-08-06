<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Security;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    private const TOKEN_LIFETIME_MINUTES = 60;

    public function showForgotForm(string $guard): View
    {
        $config = $this->guardConfig($guard);

        return view('auth.forgot-password', [
            'guard' => $guard,
            'moduleLabel' => $config['module_label'],
        ]);
    }

    public function sendResetLink(Request $request, string $guard): RedirectResponse
    {
        Log::info('Password reset request received', ['guard' => $guard, 'email' => $request->input('email')]);

        $config = $this->guardConfig($guard);

        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $modelClass = $config['model'];
        $email = strtolower(trim($validated['email']));

        Log::info('Looking up account for password reset', ['email' => $email, 'model' => $modelClass]);

        /** @var User|Admin|Security|null $account */
        $account = $modelClass::query()->where('email', $email)->first();

        if (!$account) {
            Log::warning('Password reset: account not found', ['email' => $email]);
            return back()
                ->withErrors([
                    'email' => "No {$config['module_label']} account was found for this email.",
                ])
                ->withInput();
        }

        Log::info('Account found for password reset', ['email' => $email, 'account_id' => $account->id]);

        $token = Str::random(64);
        $account->setAttribute('reset_token_hash', Hash::make($token));
        $account->setAttribute('reset_token_expires_at', now()->addMinutes(self::TOKEN_LIFETIME_MINUTES));
        $account->save();

        Log::info('Reset token saved to database', ['email' => $email]);

        $this->sendResetMail($guard, $email, $token);

        Log::info('Password reset link request completed', ['email' => $email]);

        return back()->with('success', "A password reset link was sent to your {$config['module_label']} account email.");
    }

    public function showResetForm(Request $request, string $guard, string $token): View
    {
        $config = $this->guardConfig($guard);

        return view('auth.reset-password', [
            'guard' => $guard,
            'moduleLabel' => $config['module_label'],
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request, string $guard): RedirectResponse
    {
        $config = $this->guardConfig($guard);

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ]);

        $modelClass = $config['model'];
        $email = strtolower(trim($validated['email']));

        /** @var User|Admin|Security|null $account */
        $account = $modelClass::query()->where('email', $email)->first();

        if (
            !$account ||
            !$account->getAttribute('reset_token_hash') ||
            !$account->getAttribute('reset_token_expires_at') ||
            !$account->getAttribute('reset_token_expires_at')->isFuture() ||
            !Hash::check($validated['token'], (string) $account->getAttribute('reset_token_hash'))
        ) {
            return back()
                ->withErrors([
                    'email' => "The reset link is invalid, expired, or not valid for the {$config['module_label']} module.",
                ])
                ->withInput($request->except('password', 'password_confirmation'));
        }

        $passwordColumn = $config['password_column'];
        $account->setAttribute($passwordColumn, Hash::make($validated['password']));
        $account->setAttribute('reset_token_hash', null);
        $account->setAttribute('reset_token_expires_at', null);
        $account->save();

        return redirect()
            ->route($config['login_route'])
            ->with('success', 'Your password has been reset successfully. Please log in.');
    }

    private function sendResetMail(string $guard, string $email, string $token): void
    {
        Log::info('Password reset email sending initiated', ['email' => $email, 'guard' => $guard]);

        $url = route('password.reset.form', [
            'guard' => $guard,
            'token' => $token,
            'email' => $email,
        ]);

        Log::info('Password reset URL generated', ['url' => $url]);

        $message = implode(PHP_EOL, [
            'Hello,',
            '',
            'You requested to reset your password.',
            "Reset link: {$url}",
            '',
            'This link will expire in 60 minutes.',
            'If you did not request this, you can ignore this email.',
        ]);

        try {
            Log::info('Attempting to send mail via Mail::raw', [
                'email' => $email,
                'mailer' => config('mail.default'),
                'mailgun_domain' => config('mail.mailers.mailgun.domain'),
                'mailgun_secret_exists' => !empty(config('mail.mailers.mailgun.secret')),
                'mailgun_secret_length' => strlen(config('mail.mailers.mailgun.secret') ?? ''),
            ]);
            Mail::raw($message, function ($mail) use ($email): void {
                $mail->to($email)->subject('PUPTracker Password Reset');
            });
            Log::info('Password reset email sent successfully', ['email' => $email]);
        } catch (\Throwable $exception) {
            Log::error('Unable to send password reset email.', [
                'email' => $email,
                'error' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
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
