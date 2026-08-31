@extends('layouts.auth-portal', ['title' => $moduleLabel . ' Verify Reset', 'portal' => 'recovery'])

@section('auth-content')

        <p class="module-chip">{{ $moduleLabel }} Module</p>
        <h2>Verify It's You</h2>
        <p class="subtitle">For your security, enter the 6-digit code sent to {{ $emailMasked ?? 'your registered email' }}.</p>

        @if($errors->has('link'))
            <div class="login-error">
                <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
                <span>{{ $errors->first('link') }}</span>
            </div>
        @endif

        @if($errors->has('code'))
            <div class="login-error">
                <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
                <span>{{ $errors->first('code') }}</span>
            </div>
        @endif

        <form action="{{ route('password.reset.otp', ['guard' => $guard]) }}" method="POST" autocomplete="off">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="mb-3">
                <label class="form-label">Verification Code</label>
                <div class="input-group login-input-group">
                    <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
                    <input
                        type="text"
                        name="code"
                        class="form-control @error('code') is-invalid @enderror"
                        value="{{ old('code') }}"
                        maxlength="6"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        placeholder="123456"
                        required>
                </div>
                @error('code')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-success w-100 login-btn">Verify and Continue</button>
        </form>

        <p class="security-note mt-3">
            <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
            This code expires in 5 minutes. You have a limited number of attempts before this reset link is locked.
        </p>

        <div class="footer-links mt-3">
            <a href="{{ route($guard . '.login') }}">← Back to Login</a>
        </div>
@endsection
