@extends('layouts.app')

@section('title', 'MFA Verification')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/student-login.css') }}">
@endpush

@section('content')

<div class="login-page">

    <div class="login-card">

        <img
            src="{{ asset('assets/images/Tracker-logo.png') }}"
            class="logo"
            alt="PUPTracker Logo">

        <p class="module-chip">Multi-Factor Authentication</p>

        <h2>Verify Login</h2>

        @php
            $methods = $pending['methods'] ?? ['email'];
            $selectedMethod = old('method', $pending['selected_method'] ?? 'email');
            $hasTotp = in_array('totp', $methods, true);
        @endphp

        <p class="subtitle">
            @if($hasTotp)
                Choose your verification method, then enter a 6-digit code.
            @else
                Enter the 6-digit code sent to {{ $pending['email_masked'] ?? 'your email' }}.
            @endif
        </p>

        <form
            action="{{ route('mfa.verify.submit') }}"
            method="POST"
            autocomplete="off">

            @csrf

            @if($hasTotp)
                <div class="mb-3">
                    <label class="form-label">Verification Method</label>
                    <select name="method" class="form-select" required>
                        <option value="email" {{ $selectedMethod === 'email' ? 'selected' : '' }}>Email OTP ({{ $pending['email_masked'] ?? 'masked email' }})</option>
                        <option value="totp" {{ $selectedMethod === 'totp' ? 'selected' : '' }}>Authenticator App</option>
                    </select>
                </div>
            @else
                <input type="hidden" name="method" value="email">
            @endif

            <div class="mb-3">
                <label class="form-label">Verification Code</label>
                <input
                    type="text"
                    name="code"
                    class="form-control @error('code') is-invalid @enderror"
                    value="{{ old('code') }}"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    maxlength="6"
                    required>

                @error('code')
                    <div class="invalid-feedback d-block">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <div class="form-check mb-3 text-start">
                <input
                    class="form-check-input"
                    type="checkbox"
                    value="1"
                    id="rememberDevice"
                    name="remember_device"
                    {{ old('remember_device') ? 'checked' : '' }}>
                <input
                    type="hidden"
                    id="rememberDeviceCheckedAt"
                    name="remember_device_checked_at"
                    value="{{ old('remember_device_checked_at') }}">
                <label class="form-check-label" for="rememberDevice">
                    Remember this device until {{ $rememberUntil ?? 'one month from today' }}
                </label>
                <button
                    type="button"
                    class="btn btn-link p-0 ms-1 align-baseline"
                    data-bs-toggle="tooltip"
                    data-bs-trigger="hover focus click"
                    data-bs-title="For security, trusted-device access is limited to 1 month. The 1-month timer starts when you select this option."
                    aria-label="Why remember me lasts only one month?">
                    ?
                </button>
            </div>

            <button
                type="submit"
                class="btn btn-success w-100 login-btn mb-2">
                Verify and Continue
            </button>
        </form>

        <div class="d-flex gap-2 flex-wrap mt-2">
            <form action="{{ route('mfa.verify.resend') }}" method="POST" class="flex-grow-1">
                @csrf
                <button type="submit" class="btn btn-outline-secondary w-100">Resend Code</button>
            </form>

            <form action="{{ route('mfa.verify.cancel') }}" method="POST" class="flex-grow-1">
                @csrf
                <button type="submit" class="btn btn-outline-danger w-100">Cancel</button>
            </form>
        </div>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const rememberCheckbox = document.getElementById('rememberDevice');
    const checkedAtInput = document.getElementById('rememberDeviceCheckedAt');

    if (rememberCheckbox && checkedAtInput && rememberCheckbox.checked && !checkedAtInput.value) {
        checkedAtInput.value = Math.floor(Date.now() / 1000);
    }

    if (rememberCheckbox && checkedAtInput) {
        rememberCheckbox.addEventListener('change', function () {
            if (rememberCheckbox.checked) {
                checkedAtInput.value = Math.floor(Date.now() / 1000);
            } else {
                checkedAtInput.value = '';
            }
        });
    }

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
        new bootstrap.Tooltip(element);
    });
});
</script>

@endsection
