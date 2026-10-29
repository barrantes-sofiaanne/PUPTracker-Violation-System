{{--
    Email + password form used by the Security, OSS Staff and Administrator logins.

    Expects:
        $action  – the POST route URL
        $guard   – 'security' | 'admin'  (forgot-password guard)
        $idpUrl  – (optional) PUP IDP start URL; shows the "or" button + redirect overlay
--}}
<form action="{{ $action }}"
      method="POST"
      autocomplete="off"
      class="spl-form">

    @csrf

    @if($errors->has('login'))
        <div class="spl-error" role="alert">
            <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
            <span>{{ $errors->first('login') }}</span>
        </div>
    @endif

    {{-- Inactivity expiry notice. PULLED, so it shows at most once and only
         after a genuinely authenticated session expired. --}}
    @php
        $expiredReason = \App\Support\Auth\SessionExpiryNotice::pullFromCurrentRequest();
    @endphp

    @if($expiredReason)
        <div class="spl-error" role="alert">
            <i class="bi bi-clock-history" aria-hidden="true"></i>
            <span>{{ $expiredReason }}</span>
        </div>
    @endif

    <div class="spl-field-group">
        <label class="spl-sr" for="email">Email</label>

        <div class="spl-field @error('email') has-error @enderror">
            <i class="bi bi-envelope spl-field-icon" aria-hidden="true"></i>
            <input id="email"
                   type="email"
                   name="email"
                   placeholder="Email address"
                   value="{{ old('email') }}"
                   autocomplete="email"
                   required>
        </div>

        @error('email')
            <p class="spl-field-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="spl-field-group">
        <label class="spl-sr" for="password">Password</label>

        <div class="spl-field spl-field--password @error('password') has-error @enderror">
            <i class="bi bi-lock spl-field-icon" aria-hidden="true"></i>
            <input id="password"
                   type="password"
                   name="password"
                   placeholder="Password"
                   autocomplete="current-password"
                   required>

            <button type="button"
                    class="spl-toggle"
                    id="togglePassword"
                    aria-label="Show password">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        </div>

        @error('password')
            <p class="spl-field-error">{{ $message }}</p>
        @enderror
    </div>

    {{-- "Remember me" intentionally stays on the MFA verification screen only,
         so a persistent credential is never requested before MFA. --}}
    <div class="spl-row">
        <a href="{{ route('password.request', ['guard' => $guard]) }}" class="spl-forgot">
            Forgot password?
        </a>
    </div>

    <button type="submit" class="spl-btn">
        <span>Sign In</span>
        <i class="bi bi-arrow-right" aria-hidden="true"></i>
    </button>
</form>

@if(!empty($idpUrl))
    {{-- Second login process: PUP IDP (SSO) --}}
    <div class="spl-or" role="separator" aria-label="or"><span>or</span></div>

    <a href="{{ $idpUrl }}"
       class="spl-btn spl-btn--ghost"
       id="portalIdpButton">
        <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
        <span>Continue with PUP IDP</span>
    </a>

    {{-- Redirecting state: blocks further interaction while the browser leaves for the IDP --}}
    <div id="portalIdpOverlay" class="spl-overlay" hidden aria-live="polite">
        <div class="spl-overlay-card">
            <div class="spl-overlay-spinner" aria-hidden="true"></div>
            <h3>Redirecting to PUP IDP</h3>
            <p>Please wait while we redirect you to the PUP Identity Provider.</p>
            <p style="margin-top:14px">
                If nothing happens, <a href="{{ $idpUrl }}">click here to continue</a>.
            </p>
        </div>
    </div>

    @push('scripts')
    <script>
    (function () {
        document.addEventListener('click', function (event) {
            if (event.defaultPrevented || event.button !== 0) { return; }

            var trigger = event.target.closest('#portalIdpButton');
            if (!trigger) { return; }

            var overlay = document.getElementById('portalIdpOverlay');
            var form = document.querySelector('.spl-form');

            if (overlay) { overlay.hidden = false; }

            // The page is about to unload: make the form inert so a stray Enter
            // key cannot submit a password attempt while the IDP redirect runs.
            if (form) {
                form.querySelectorAll('input, button').forEach(function (el) {
                    el.disabled = true;
                });
            }

            trigger.classList.add('is-busy');
            trigger.setAttribute('aria-busy', 'true');
        });
    })();
    </script>
    @endpush
@endif
