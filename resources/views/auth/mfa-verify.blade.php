@extends('layouts.auth-portal', ['title' => 'MFA Verification', 'portal' => 'mfa'])

@section('auth-content')

@php
    $methods = $pending['methods'] ?? ['email'];
    $selectedMethod = old('method', $pending['selected_method'] ?? null);
    $stage = $pending['stage'] ?? (count($methods) > 1 ? 'select' : 'code');
    $hasTotpSecret = !empty($pending['totp_secret']);
    $totpPendingSetup = (bool) ($pending['totp_pending_setup'] ?? false);
    $methodLabels = [
        'email' => 'Email OTP',
        'totp' => 'Authenticator App',
        'backup' => 'Backup Code',
    ];

    // "Remember me" carried over from the login screen. Only the privileged
    // (admin/security) challenge can ever hold this flag — MfaService
    // normalizes it to false for student logins — so the checkbox is rendered
    // solely for those guards. Laravel keeps the posted `remember` value in the
    // session automatically when validation fails, so a failed verification
    // re-renders this screen with the user's most recent choice intact.
    $canRemember = in_array((string) ($pending['guard'] ?? ''), ['admin', 'security'], true);
    $rememberChecked = $canRemember
        && (bool) old('remember', $pending['remember'] ?? false);
@endphp

@if($canRemember)
    {{--
        Auto-submit guard for the Remember Me checkbox on the MFA screen.

        A checkbox that is only posted when the user presses [Verify] cannot
        persist a *changed* choice: if the next verification fails, the screen
        re-renders and the box would silently revert. To let the toggle persist
        immediately, the checkbox auto-submits a dedicated form that records the
        choice and re-renders the SAME stage, without validating any code.

        The visible checkbox lives inside an `aria-hidden` form that is its only
        submit button, so `form.requestSubmit()` can never accidentally hit
        [Verify and Continue] and burn a verification attempt.
    --}}
    <script>
    (function () {
        function wire() {
            var checkbox = document.getElementById('mfaRememberMe');
            var form = document.getElementById('mfaRememberForm');
            if (!checkbox || !form || checkbox.dataset.bound === '1') {
                return;
            }
            checkbox.dataset.bound = '1';
            checkbox.addEventListener('change', function () {
                // Keep the hidden mirror in step: a checkbox is not submitted
                // when unchecked, so the mirror carries the explicit value.
                var mirror = document.getElementById('mfaRememberMirror');
                if (mirror) {
                    mirror.value = checkbox.checked ? '1' : '0';
                }
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            });
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', wire);
        } else {
            wire();
        }
    })();
    </script>
@endif

    @if($stage === 'select')
        <div>
            <p class="module-chip">Multi-Factor Authentication</p>
            <h2>Choose Verification Method</h2>
            <p class="subtitle">Select how you want to verify this login.</p>

            @error('method')
                <div class="alert alert-danger">{{ $message }}</div>
            @enderror

            @error('code')
                <div class="alert alert-danger">{{ $message }}</div>
            @enderror

            <div class="d-grid gap-2">
                <form action="{{ route('mfa.verify.submit') }}" method="POST">
                    @csrf
                    <input type="hidden" name="action" value="select">
                    <input type="hidden" name="method" value="email">
                    @if($canRemember)
                        <input type="hidden" name="remember" value="{{ $rememberChecked ? '1' : '0' }}">
                    @endif
                    <button type="submit" class="btn btn-success w-100">Use Email OTP ({{ $pending['email_masked'] ?? 'masked email' }})</button>
                </form>

                @if(in_array('totp', $methods, true))
                    <form action="{{ route('mfa.verify.submit') }}" method="POST">
                        @csrf
                        <input type="hidden" name="action" value="select">
                        <input type="hidden" name="method" value="totp">
                        @if($canRemember)
                            <input type="hidden" name="remember" value="{{ $rememberChecked ? '1' : '0' }}">
                        @endif
                        <button type="submit" class="btn btn-outline-success w-100">Use Authenticator App (TOTP)</button>
                    </form>
                @endif

                @if(in_array('backup', $methods, true))
                    <form action="{{ route('mfa.verify.submit') }}" method="POST">
                        @csrf
                        <input type="hidden" name="action" value="select">
                        <input type="hidden" name="method" value="backup">
                        @if($canRemember)
                            <input type="hidden" name="remember" value="{{ $rememberChecked ? '1' : '0' }}">
                        @endif
                        <button type="submit" class="btn btn-outline-secondary w-100">Use Backup Code</button>
                    </form>
                @endif
            </div>

            @if($canRemember)
                <div class="remember-row">
                    <div class="form-check remember-check">
                        <input
                            type="checkbox"
                            class="form-check-input"
                            id="mfaRememberMe"
                            name="remember"
                            value="1"
                            form="mfaRememberForm"
                            @checked($rememberChecked)>
                        <label class="form-check-label remember-label" for="mfaRememberMe">
                            Remember this device for 12 hours
                        </label>
                    </div>
                </div>
            @endif

            <form action="{{ route('mfa.verify.cancel') }}" method="POST" class="mt-3">
                @csrf
                <button type="submit" class="btn btn-outline-danger w-100">Cancel</button>
            </form>

            @if($canRemember)
                {{-- The checkbox visible above is associated with this form via
                     the `form` attribute, so requestSubmit() targets the only
                     button here and never [Verify and Continue]. --}}
                <form id="mfaRememberForm" action="{{ route('mfa.verify.submit') }}" method="POST" aria-hidden="true">
                    @csrf
                    <input type="hidden" name="action" value="remember">
                    <input type="hidden" name="stage_toggle" value="1">
                    <button type="submit" tabindex="-1" hidden></button>
                </form>
            @endif
        </div>
    @else
        <div>

            <p class="module-chip">Multi-Factor Authentication</p>
            <h2>Verify Login</h2>
            <p class="subtitle">
                @if($selectedMethod === 'email')
                    Enter the 6-digit code sent to {{ $pending['email_masked'] ?? 'your email' }}.
                @elseif($selectedMethod === 'totp')
                    Enter the 6-digit code from your authenticator app.
                @else
                    Enter one of your backup recovery codes.
                @endif
            </p>

            @if($selectedMethod === 'totp' && !$hasTotpSecret)
                <div class="alert alert-warning">
                    Unable to start authenticator setup right now. Please choose another method and try again.
                </div>
            @else
                @if($selectedMethod === 'totp' && $totpPendingSetup)
                    <div class="alert alert-info text-start">
                        <strong>Step 1: Scan this QR code</strong>
                        @php $totpQrCode = (string) ($pending['totp_qr_code'] ?? ''); @endphp
                        <div class="totp-qr-code bg-white border rounded p-2 my-2 d-inline-block">
                            @if(str_starts_with($totpQrCode, 'data:image'))
                                <img src="{{ $totpQrCode }}" alt="TOTP QR Code">
                            @elseif(str_starts_with(ltrim($totpQrCode), '<svg'))
                                {!! $totpQrCode !!}
                            @endif
                        </div>
                        <div class="small text-muted mt-2">Step 2: Enter the 6-digit code from your authenticator app below.</div>
                    </div>
                @endif

                <form
                    action="{{ route('mfa.verify.submit') }}"
                    method="POST"
                    autocomplete="off">

                    @csrf
                    <input type="hidden" name="action" value="verify">
                    <input type="hidden" name="method" value="{{ $selectedMethod ?? 'email' }}">

                    <div class="mb-3">
                        <label class="form-label">
                            {{ $methodLabels[$selectedMethod ?? 'email'] ?? 'Verification Code' }}
                        </label>
                        <div class="input-group login-input-group">
                            <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
                            <input
                                type="text"
                                name="code"
                                class="form-control @error('code') is-invalid @enderror"
                                value="{{ old('code') }}"
                                maxlength="20"
                                autocomplete="one-time-code"
                                required>
                        </div>

                        @error('code')
                            <div class="invalid-feedback d-block">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    @if($canRemember)
                        <div class="remember-row">
                            <div class="form-check remember-check">
                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    id="mfaRememberMe"
                                    name="remember"
                                    value="1"
                                    @checked($rememberChecked)>
                                <label class="form-check-label remember-label" for="mfaRememberMe">
                                    Remember this device for 12 hours
                                </label>
                            </div>
                        </div>
                    @endif

                    <button
                        type="submit"
                        class="btn btn-success w-100 login-btn mb-2">
                        Verify and Continue
                    </button>
                </form>

                @if($canRemember)
                    {{-- Standalone toggle form. The checkbox above cannot live in
                         it (it is inside the verification form), so a hidden
                         mirror is kept in sync by the script below; a checkbox
                         is not submitted when unchecked, which makes the mirror
                         the reliable source for requestSubmit(). --}}
                    <form id="mfaRememberForm" action="{{ route('mfa.verify.submit') }}" method="POST" aria-hidden="true">
                        @csrf
                        <input type="hidden" name="action" value="remember">
                        <input type="hidden" name="stage_toggle" value="1">
                        <input type="hidden" name="remember" id="mfaRememberMirror" value="{{ $rememberChecked ? '1' : '0' }}">
                        <button type="submit" tabindex="-1" hidden></button>
                    </form>
                @endif
            @endif

            <div class="d-flex gap-2 flex-wrap mt-2">
                @if(($selectedMethod ?? 'email') === 'email')
                    <form action="{{ route('mfa.verify.resend') }}" method="POST" class="flex-grow-1">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary w-100">Resend Code</button>
                    </form>
                @endif

                @if(count($methods) > 1)
                    <form action="{{ route('mfa.verify.submit') }}" method="POST" class="flex-grow-1">
                        @csrf
                        <input type="hidden" name="action" value="back">
                        <button type="submit" class="btn btn-outline-primary w-100">Choose Another Method</button>
                    </form>
                @endif

                <form action="{{ route('mfa.verify.cancel') }}" method="POST" class="flex-grow-1">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger w-100">Cancel</button>
                </form>
            </div>
        </div>
    @endif

@endsection
