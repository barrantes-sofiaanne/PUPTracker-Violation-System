@extends('layouts.auth-portal', ['title' => $moduleLabel . ' Reset Password', 'portal' => 'recovery'])

@section('auth-content')

        <p class="module-chip">{{ $moduleLabel }} Module</p>
        <h2>Reset Password</h2>
        <p class="subtitle">Set a new password for your account.</p>

        @if($errors->has('link'))
            <div class="login-error">
                <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
                <span>{{ $errors->first('link') }}</span>
            </div>
        @endif

        <form id="resetPasswordForm" action="{{ route('password.update', ['guard' => $guard]) }}" method="POST" autocomplete="off">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            @if(!empty($email))
                <div class="mb-3 text-muted small">
                    <i class="bi bi-shield-check" aria-hidden="true"></i>
                    Resetting the password for the account registered to {{ $email }}.
                </div>
            @endif

            <div class="mb-3">
                <label class="form-label">New Password</label>
                <div class="input-group login-input-group">
                    <span class="input-group-text"><i class="bi bi-key"></i></span>
                    <input id="newPassword" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" required>
                </div>
                @error('password')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <section class="password-policy" aria-labelledby="password-policy-title">
                <div class="password-policy-heading">
                    <i class="bi bi-shield-check" aria-hidden="true"></i>
                    <h3 id="password-policy-title">Password policy</h3>
                </div>
                <ul>
                    <li data-policy="length"><i class="bi bi-circle" aria-hidden="true"></i> At least 12 characters</li>
                    <li data-policy="lowercase"><i class="bi bi-circle" aria-hidden="true"></i> One lowercase letter</li>
                    <li data-policy="uppercase"><i class="bi bi-circle" aria-hidden="true"></i> One uppercase letter</li>
                    <li data-policy="number"><i class="bi bi-circle" aria-hidden="true"></i> One number</li>
                    <li data-policy="symbol"><i class="bi bi-circle" aria-hidden="true"></i> One symbol</li>
                </ul>
            </section>

            <div class="mb-4">
                <label class="form-label">Confirm Password</label>
                <div class="input-group login-input-group">
                    <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
                    <input id="passwordConfirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password" required>
                </div>
            </div>

            <button id="resetPasswordSubmit" type="submit" class="btn btn-success w-100 login-btn" disabled>Reset Password</button>
        </form>
@endsection
