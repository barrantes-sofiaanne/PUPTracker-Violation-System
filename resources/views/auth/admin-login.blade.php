@extends('layouts.auth-portal', ['title' => 'Admin Login', 'portal' => 'admin'])

@section('ap-content')
    <div class="ap-login">

        <span class="ap-icon" aria-hidden="true">
            <i class="bi bi-gear-fill"></i>
        </span>

        <h2 class="ap-heading">Administrator Portal</h2>
        <p class="ap-subtitle">Sign in to your account.</p>

        {{-- Password-reset confirmation: the server's own message, shown only
             after a reset completed (it is flashed by the reset action). --}}
        @if(session('status'))
            <div class="ap-alert ap-alert--success" role="status">
                <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <form action="{{ route('admin.login.post') }}"
              method="POST"
              autocomplete="off"
              class="ap-form">

            @csrf

            @if($errors->has('login'))
                <div class="ap-alert" role="alert">
                    <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
                    <span>{{ $errors->first('login') }}</span>
                </div>
            @endif

            {{-- Inactivity expiry notice.
                 PULLED rather than read: pulling consumes the value, so the
                 message can be displayed at most once and can never reappear on
                 a later visit to this page. It is only ever recorded by the
                 inactivity middleware, after a genuinely AUTHENTICATED session
                 expired — so a first-time unauthenticated visitor, or someone
                 who simply opened the login page, sees nothing here. --}}
            @php($expiredReason = \App\Support\Auth\SessionExpiryNotice::pullFromCurrentRequest())
            @if($expiredReason)
                <div class="ap-alert" role="alert">
                    <i class="bi bi-clock-history" aria-hidden="true"></i>
                    <span>{{ $expiredReason }}</span>
                </div>
            @endif

            <div class="ap-field-group">
                <label class="ap-sr" for="email">Email</label>

                <div class="ap-field @error('email') has-error @enderror">
                    <i class="bi bi-envelope ap-field-icon" aria-hidden="true"></i>
                    <input id="email"
                           type="email"
                           name="email"
                           placeholder="Email address"
                           value="{{ old('email') }}"
                           autocomplete="email"
                           required>
                </div>

                @error('email')
                    <p class="ap-field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="ap-field-group">
                <label class="ap-sr" for="password">Password</label>

                <div class="ap-field ap-field--password @error('password') has-error @enderror">
                    <i class="bi bi-lock ap-field-icon" aria-hidden="true"></i>
                    <input id="password"
                           type="password"
                           name="password"
                           placeholder="Password"
                           autocomplete="current-password"
                           required>

                    <button type="button"
                            class="ap-toggle"
                            data-toggle-password
                            aria-controls="password"
                            aria-label="Show password">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                </div>

                @error('password')
                    <p class="ap-field-error">{{ $message }}</p>
                @enderror
            </div>

            {{--
                PART 8 — the "Remember Me" option intentionally does NOT appear
                here. It is offered ONLY on the selected MFA verification screen
                (PART 10), so a persistent credential can never be requested
                before MFA has been satisfied.
            --}}

            <div class="ap-row">
                <a href="{{ route('password.request', ['guard' => 'admin']) }}" class="ap-link">
                    Forgot password?
                </a>
            </div>

            <button type="submit" class="ap-btn">
                <span>Sign In</span>
                <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </button>
        </form>

        {{-- Second administrator login process: PUP IDP (SSO). --}}
        <div class="ap-or" role="separator" aria-label="or"><span>or</span></div>

        <a href="{{ route('admin.idp.login') }}"
           class="ap-btn ap-btn--ghost"
           id="adminIdpLoginButton"
           data-idp-redirect>
            <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
            <span>Continue with PUP IDP</span>
        </a>

        <a href="{{ route('login') }}"
           class="ap-back"
           data-soft-nav
           data-portal-theme="access">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            <span>Back to portal selection</span>
        </a>

    </div>

    {{-- Redirecting-to-IDP state. The browser leaves this page for the IDP, so
         a full-screen overlay blocks any further interaction (double submits,
         another password attempt) while the navigation is in flight. --}}
    <div id="apIdpOverlay" class="ap-overlay" hidden aria-live="polite">
        <div class="ap-overlay-card">
            <div class="ap-overlay-spinner" aria-hidden="true"></div>
            <h3>Redirecting to PUP IDP</h3>
            <p>Please wait while we redirect you to the PUP Identity Provider.</p>
            <p style="margin-top: 14px;">
                If nothing happens, <a href="{{ route('admin.idp.login') }}">click here to continue</a>.
            </p>
        </div>
    </div>
@endsection
