@extends('layouts.auth-portal', ['title' => 'Security and OSS Staff Portal', 'portal' => 'security'])

@section('auth-content')

        {{-- SHARED PORTAL. This ONE page is the sign-in entry point for BOTH
             Security Personnel and OSS Staff. They remain different account
             types on different guards (security vs admin) and land in their own
             modules after MFA — only the login page is shared. --}}
        <p class="module-chip">Security &amp; OSS Staff Portal</p>

        <h2>Security and OSS Staff Portal</h2>

        <p class="subtitle">
            Sign in using your Email. This portal is for <strong>Security Personnel</strong>
            and <strong>OSS Staff</strong> accounts.
        </p>

        <form
            action="{{ route('security.login.post') }}"
            method="POST"
            autocomplete="off"
            class="auth-login-form">

            @csrf

            @if($errors->has('login'))
                <div class="login-error">
                    {{ $errors->first('login') }}
                </div>
            @endif

            {{-- Inactivity expiry notice.
                 PULLED rather than read: pulling consumes the value, so the
                 message can be displayed at most once and can never reappear on
                 a later visit. This page previously had no expiry notice at all,
                 so a timed-out Security user was bounced to the login form with
                 no explanation; it now matches the Administrator login. --}}
            @php($expiredReason = \App\Support\Auth\SessionExpiryNotice::pullFromCurrentRequest())
            @if($expiredReason)
                <div class="login-error" role="alert">
                    <i class="bi bi-clock-history" aria-hidden="true"></i>
                    <span>{{ $expiredReason }}</span>
                </div>
            @endif

            <div class="mb-3">

                <label class="form-label" for="email">
                    Email
                </label>

                <div class="input-group login-input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        class="form-control @error('email') is-invalid @enderror"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        required>
                </div>

                @error('email')
                    <div class="invalid-feedback">
                        {{ $message }}
                    </div>
                @enderror

            </div>

            <div class="mb-4">

                <label class="form-label" for="password">
                    Password
                </label>

                <div class="password-wrapper">

                    <span class="password-input-icon"><i class="bi bi-key"></i></span>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        class="form-control @error('password') is-invalid @enderror"
                        autocomplete="current-password"
                        required>

                    <button
                        type="button"
                        class="toggle-password"
                        id="togglePassword"
                        aria-label="Show password">

                        <i class="bi bi-eye"></i>

                    </button>

                </div>

                @error('password')
                    <div class="invalid-feedback d-block">
                        {{ $message }}
                    </div>
                @enderror

            </div>

            {{--
                PART 8 — the "Remember Me" option intentionally does NOT appear
                here. It is offered ONLY on the selected MFA verification screen
                (PART 10), so a persistent credential can never be requested
                before MFA has been satisfied.
            --}}

            <button
                type="submit"
                class="btn btn-success w-100 login-btn">

                Login

            </button>

        </form>

        <div class="footer-links">

            <a href="{{ route('login') }}">
                &larr; Back
            </a>

            <a href="{{ route('password.request', ['guard' => 'security']) }}">
                Forgot Password?
            </a>

        </div>

@endsection

