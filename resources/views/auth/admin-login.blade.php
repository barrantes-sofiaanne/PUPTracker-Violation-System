@extends('layouts.auth-portal', ['title' => 'Admin Login', 'portal' => 'admin'])

@section('auth-content')

        <p class="module-chip">Administrator Module</p>

        <h2>Administrator Login</h2>

        <p class="subtitle">
            Sign in using your Email, or continue with your PUP IDP account.
        </p>

        <form
            action="{{ route('admin.login.post') }}"
            method="POST"
            autocomplete="off"
            class="auth-login-form">

            @csrf

            @if($errors->has('login'))
                <div class="login-error">
                    {{ $errors->first('login') }}
                </div>
            @endif

            {{-- PART 5 — inactivity expiry notice. Carried on the fresh session
                 by EnforceInactivityTimeout; contains no session details. --}}
            @if(session('auth.expired_reason'))
                <div class="login-error" role="alert">
                    <i class="bi bi-clock-history" aria-hidden="true"></i>
                    <span>{{ session('auth.expired_reason') }}</span>
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

        {{-- Second administrator login process: PUP IDP (SSO). --}}
        <div class="auth-divider" role="separator" aria-label="or">
            <span>or</span>
        </div>

        <a
            href="{{ route('admin.idp.login') }}"
            class="btn w-100 login-btn btn-outline-idp"
            id="adminIdpLoginButton">

            <i class="bi bi-shield-lock-fill me-2" aria-hidden="true"></i>
            Login through PUP IDP

        </a>

        <p class="auth-hint">
            Use your PUP IDP account to sign in with your institutional credentials.
        </p>

        <div class="footer-links">

            <a href="{{ route('login') }}">
                &larr; Back
            </a>

            <a href="{{ route('password.request', ['guard' => 'admin']) }}">
                Forgot Password?
            </a>

        </div>

    {{-- Redirecting-to-IDP state. The browser leaves this page for the IDP, so
         a full-screen overlay blocks any further interaction (double submits,
         another password attempt) while the navigation is in flight. --}}
    <div id="adminIdpOverlay" class="auth-redirect-overlay" hidden aria-live="polite">
        <div class="auth-redirect-card">
            <div class="auth-redirect-spinner" aria-hidden="true"></div>
            <h3>Redirecting to PUP IDP</h3>
            <p>Please wait while we redirect you to the PUP Identity Provider.</p>
            <div class="auth-redirect-skeleton" aria-hidden="true">
                <span></span><span></span><span></span>
            </div>
            <p class="auth-redirect-note">
                If nothing happens, <a href="{{ route('admin.idp.login') }}">click here to continue</a>.
            </p>
        </div>
    </div>

    @push('scripts')
    <script>
    (function () {
        /* The auth layout swaps `.auth-content-card` in place (portal-transitions
           .js) without re-running pushed scripts, so the handler is delegated on
           `document` and looks its elements up at click time. Without this the
           redirect state would silently stop working after any card swap. */
        const beginRedirect = function () {
            const overlay = document.getElementById('adminIdpOverlay');
            const button = document.getElementById('adminIdpLoginButton');
            const form = document.querySelector('.auth-login-form');

            if (overlay) {
                overlay.hidden = false;
            }

            // The page is about to unload, so make it inert: no second submit
            // from a stray Enter key while the browser leaves for the IDP. The
            // IDP anchor itself stays clickable as the manual fallback.
            if (form) {
                form.querySelectorAll('input, button').forEach(function (element) {
                    element.disabled = true;
                });
            }

            if (button) {
                button.classList.add('is-busy');
                button.setAttribute('aria-busy', 'true');
            }
        };

        document.addEventListener('click', function (event) {
            if (event.defaultPrevented || event.button !== 0) {
                return;
            }

            const trigger = event.target.closest('#adminIdpLoginButton');

            if (trigger) {
                beginRedirect();
            }
        });
    })();
    </script>
    @endpush

@endsection

