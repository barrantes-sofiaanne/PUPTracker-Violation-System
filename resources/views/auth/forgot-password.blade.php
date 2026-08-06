@extends('layouts.auth-portal', ['title' => $moduleLabel . ' Forgot Password', 'portal' => 'recovery'])

@section('auth-content')

        <p class="module-chip">{{ $moduleLabel }} Module</p>
        <h2>Forgot Password</h2>
        <p class="subtitle">Enter your account email to receive a reset link.</p>

        <section class="password-policy password-policy-preview" aria-labelledby="password-policy-title">
            <div class="password-policy-heading">
                <i class="bi bi-shield-check" aria-hidden="true"></i>
                <h3 id="password-policy-title">Password policy</h3>
            </div>
            <p>Your new password must have at least 8 characters, including an uppercase letter, lowercase letter, number, and symbol.</p>
        </section>
        <form action="{{ route('password.email', ['guard' => $guard]) }}" method="POST" autocomplete="off">
            @csrf

            <div class="mb-4">
                <label class="form-label">Email</label>
                <div class="input-group login-input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input
                        type="email"
                        name="email"
                        class="form-control @error('email') is-invalid @enderror"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        required>
                </div>
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-success w-100 login-btn">Send Reset Link</button>
        </form>

        <div class="footer-links mt-3">
            <a href="{{ route($guard . '.login') }}">Ã¢â€ Â Back to Login</a>
        </div>
@endsection
