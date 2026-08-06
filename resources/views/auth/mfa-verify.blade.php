@extends('layouts.auth-portal', ['title' => 'MFA Verification', 'portal' => 'mfa'])

@push('styles')
<style>
    .totp-qr-code svg,
    .totp-qr-code img {
        display: block;
        width: 220px;
        height: 220px;
    }
</style>
@endpush

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
@endphp

    @if($stage === 'select')
        <div>

            <p class="module-chip">Multi-Factor Authentication</p>
            <h2>Choose Verification Method</h2>
            <p class="subtitle">Select how you want to verify this login.</p>

            @error('method')
                <div class="alert alert-danger">{{ $message }}</div>
            @enderror

            <div class="d-grid gap-2">
                <form action="{{ route('mfa.verify.submit') }}" method="POST">
                    @csrf
                    <input type="hidden" name="action" value="select">
                    <input type="hidden" name="method" value="email">
                    <button type="submit" class="btn btn-success w-100">Use Email OTP ({{ $pending['email_masked'] ?? 'masked email' }})</button>
                </form>

                @if(in_array('totp', $methods, true))
                    <form action="{{ route('mfa.verify.submit') }}" method="POST">
                        @csrf
                        <input type="hidden" name="action" value="select">
                        <input type="hidden" name="method" value="totp">
                        <button type="submit" class="btn btn-outline-success w-100">Use Authenticator App (TOTP)</button>
                    </form>
                @endif

                @if(in_array('backup', $methods, true))
                    <form action="{{ route('mfa.verify.submit') }}" method="POST">
                        @csrf
                        <input type="hidden" name="action" value="select">
                        <input type="hidden" name="method" value="backup">
                        <button type="submit" class="btn btn-outline-secondary w-100">Use Backup Code</button>
                    </form>
                @endif
            </div>

            <form action="{{ route('mfa.verify.cancel') }}" method="POST" class="mt-3">
                @csrf
                <button type="submit" class="btn btn-outline-danger w-100">Cancel</button>
            </form>
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

                    <button
                        type="submit"
                        class="btn btn-success w-100 login-btn mb-2">
                        Verify and Continue
                    </button>
                </form>
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
