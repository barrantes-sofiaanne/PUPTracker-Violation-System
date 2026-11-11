{{--
    Reset Password — step 2 of 3.

    Only reachable through a VALID emailed reset link: the controller has
    already validated the token before rendering this page. Presentation only —
    the token, CSRF protection, the password policy and the server-side
    validation are exactly as before. The "Complete" step is never shown here:
    after a successful reset the server redirects to the login page, which
    shows the server's own confirmation message.
--}}
@php
    $portalKey = $portal ?? $guard;
    $loginHref = $loginUrl ?? route($guard . '.login');

    // Display a masked address rather than the full one.
    $maskedEmail = '';

    if (! empty($email) && str_contains($email, '@')) {
        [$mailName, $mailDomain] = explode('@', $email, 2);
        $maskedEmail = substr($mailName, 0, 1)
            . str_repeat('*', max(1, strlen($mailName) - 2))
            . (strlen($mailName) > 2 ? substr($mailName, -1) : '')
            . '@' . $mailDomain;
    }

    $linkLifetime = \App\Services\PasswordResetService::TOKEN_LIFETIME_MINUTES;
@endphp

@extends('layouts.auth-portal', [
    'title'    => $moduleLabel . ' Reset Password',
    'portal'   => $portalKey,
    'brandKey' => 'reset',
])

@section('ap-content')
    <div class="ap-login ap-reset">

        <ol class="ap-steps" aria-label="Password reset progress">
            <li class="ap-step is-done">
                <span class="ap-step-dot"><i class="bi bi-check-lg" aria-hidden="true"></i></span>
                <span class="ap-step-label">Verify Account</span>
            </li>
            <li class="ap-step is-current" aria-current="step">
                <span class="ap-step-dot">2</span>
                <span class="ap-step-label">Reset Password</span>
            </li>
            <li class="ap-step">
                <span class="ap-step-dot">3</span>
                <span class="ap-step-label">Complete</span>
            </li>
        </ol>

        <span class="ap-icon" aria-hidden="true">
            <i class="bi bi-key-fill"></i>
        </span>

        <h2 class="ap-heading">Reset Password</h2>
        <p class="ap-subtitle">Set a new password for your account.</p>

        @if($errors->has('link'))
            <div class="ap-alert" role="alert">
                <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
                <span>{{ $errors->first('link') }}</span>
            </div>
        @endif

        <form id="resetPasswordForm"
              action="{{ route('password.update', ['guard' => $guard]) }}"
              method="POST"
              autocomplete="off"
              class="ap-form">

            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            @if($maskedEmail !== '')
                <p class="ap-hint">
                    <i class="bi bi-shield-check" aria-hidden="true"></i>
                    <span>
                        Resetting the password for the account registered to
                        <strong>{{ $maskedEmail }}</strong>.
                        This link expires {{ $linkLifetime }} minutes after it was requested.
                    </span>
                </p>
            @endif

            <div class="ap-field-group">
                <label class="ap-label" for="newPassword">New password</label>

                <div class="ap-field ap-field--password @error('password') has-error @enderror">
                    <i class="bi bi-key ap-field-icon" aria-hidden="true"></i>
                    <input id="newPassword"
                           type="password"
                           name="password"
                           placeholder="New password"
                           autocomplete="new-password"
                           aria-describedby="passwordPolicy @error('password')passwordError @enderror"
                           required>

                    <button type="button"
                            class="ap-toggle"
                            data-toggle-password
                            aria-controls="newPassword"
                            aria-label="Show password">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                </div>

                @error('password')
                    <p class="ap-field-error" id="passwordError">{{ $message }}</p>
                @enderror
            </div>

            {{-- Mirrors Password::min(12)->mixedCase()->numbers()->symbols() in
                 ForgotPasswordController::reset(); the server remains the authority. --}}
            <section class="ap-policy" id="passwordPolicy" aria-labelledby="passwordPolicyTitle">
                <h3 class="ap-policy-title" id="passwordPolicyTitle">
                    <i class="bi bi-shield-check" aria-hidden="true"></i>
                    Password requirements
                </h3>
                <ul>
                    <li data-policy="length"><i class="bi bi-circle" aria-hidden="true"></i> At least 12 characters</li>
                    <li data-policy="lowercase"><i class="bi bi-circle" aria-hidden="true"></i> One lowercase letter</li>
                    <li data-policy="uppercase"><i class="bi bi-circle" aria-hidden="true"></i> One uppercase letter</li>
                    <li data-policy="number"><i class="bi bi-circle" aria-hidden="true"></i> One number</li>
                    <li data-policy="symbol"><i class="bi bi-circle" aria-hidden="true"></i> One symbol</li>
                </ul>
            </section>

            <div class="ap-field-group">
                <label class="ap-label" for="passwordConfirmation">Confirm new password</label>

                <div class="ap-field ap-field--password">
                    <i class="bi bi-shield-check ap-field-icon" aria-hidden="true"></i>
                    <input id="passwordConfirmation"
                           type="password"
                           name="password_confirmation"
                           placeholder="Confirm new password"
                           autocomplete="new-password"
                           required>

                    <button type="button"
                            class="ap-toggle"
                            data-toggle-password
                            aria-controls="passwordConfirmation"
                            aria-label="Show password">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                </div>

                <p class="ap-field-error" id="passwordMatchHint" hidden>Passwords do not match.</p>
            </div>

            {{-- Enabled by the script once the policy is met and both fields match
                 (as before). Without JavaScript it stays usable; the server decides. --}}
            <button id="resetPasswordSubmit" type="submit" class="ap-btn">
                <span>Update Password</span>
                <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </button>
        </form>

        <a href="{{ $loginHref }}"
           class="ap-back"
           data-soft-nav
           data-portal-theme="{{ $portalKey }}">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            <span>Back to Login</span>
        </a>

    </div>
@endsection
