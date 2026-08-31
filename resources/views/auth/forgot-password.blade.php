@extends('layouts.auth-portal', ['title' => $moduleLabel . ' Forgot Password', 'portal' => 'recovery'])

@section('auth-content')

        <p class="module-chip">{{ $moduleLabel }} Module</p>
        <h2>Forgot Password</h2>
        <p class="subtitle">Enter your registered email address to receive a reset link.</p>

        @if(session('status'))
            <div class="login-success" role="status">
                <i class="bi bi-envelope-check" aria-hidden="true"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if($errors->has('email'))
            <div class="login-error">
                <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
                <span>{{ $errors->first('email') }}</span>
            </div>
        @endif

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
                        placeholder="admin@puptvs.com"
                        required>
                </div>
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-success w-100 login-btn">Send Reset Link</button>
        </form>

        <div class="footer-links mt-3">
            <a href="{{ route($guard . '.login') }}">← Back to Login</a>
        </div>
@endsection
