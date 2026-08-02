@extends('layouts.admin')

@section('content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-shield-alt"></i> Set Up Two-Factor Authentication (TOTP)
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle"></i>
                        <strong>Two-Factor Authentication adds an extra layer of security</strong> to your account.
                        You'll need to enter a code from an authenticator app in addition to your password.
                    </div>

                    <div class="row">
                        <div class="col-md-6 text-center mb-4">
                            <h6 class="mb-3">Step 1: Scan QR Code</h6>
                            <p class="text-muted small mb-3">
                                Use an authenticator app like Google Authenticator, Microsoft Authenticator, or Authy
                            </p>
                            <div class="qr-code-container" style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                                <img src="{{ $qrCode }}" alt="TOTP QR Code" class="img-fluid">
                            </div>
                            <p class="text-muted small mt-3">
                                <a href="#" data-bs-toggle="collapse" data-bs-target="#manualEntry">
                                    Can't scan? Enter manually
                                </a>
                            </p>
                        </div>

                        <div class="col-md-6">
                            <h6 class="mb-3">Step 2: Verify Code</h6>
                            <p class="text-muted small mb-3">
                                After scanning, enter the 6-digit code from your authenticator app
                            </p>

                            <form method="POST" action="{{ route('totp.verify') }}" id="totpForm">
                                @csrf
                                <input type="hidden" name="guard" value="{{ $guard }}">

                                <div class="mb-3">
                                    <label for="totpCode" class="form-label">
                                        <strong>Verification Code</strong>
                                    </label>
                                    <input
                                        type="text"
                                        id="totpCode"
                                        name="totp_code"
                                        class="form-control form-control-lg text-center @error('totp_code') is-invalid @enderror"
                                        placeholder="000000"
                                        maxlength="6"
                                        pattern="\d{6}"
                                        autocomplete="off"
                                        inputmode="numeric"
                                        required
                                    >
                                    @error('totp_code')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-check"></i> Verify & Enable TOTP
                                </button>
                            </form>

                            <div class="collapse mt-3" id="manualEntry">
                                <div class="alert alert-secondary">
                                    <strong>Manual Entry Key:</strong>
                                    <div class="mt-2">
                                        <code class="bg-light p-2 d-block" style="word-break: break-all;">
                                            {{ $secret }}
                                        </code>
                                    </div>
                                    <small class="d-block mt-2 text-muted">
                                        Enter this key in your authenticator app if you can't scan the QR code
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning mt-4" role="alert">
                       <h6><i class="fas fa-exclamation-triangle"></i> Important Security Notes</h6>
                       <ul class="small mb-0 mt-2">
                           <li>Never share your secret key with anyone</li>
                           <li>Save the secret key in a secure location</li>
                           <li>You'll need backup codes to recover access if you lose your phone</li>
                           <li>Each code can only be used once</li>
                       </ul>
                    </div>
                </div>
            </div>

            <div class="card mt-3 shadow-sm">
                <div class="card-body">
                    <a href="{{ $guard === 'security' ? route('security.dashboard') : (auth('admin')->check() && auth('admin')->user()->isItAdministrator() ? route('admin.super-admin.dashboard') : route('admin.dashboard')) }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .qr-code-container img,
    .qr-code-container svg {
        max-width: 200px;
        height: auto;
    }

    #totpCode {
        font-size: 1.5rem;
        letter-spacing: 0.5rem;
        font-weight: bold;
    }

    code {
        font-size: 0.85rem;
    }
</style>
@endsection
