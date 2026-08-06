@extends('layouts.app')

@section('title', $moduleLabel . ' Reset Password')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/student-login.css') }}">
@endpush

@section('content')
<div class="login-page">
    <div class="login-card">
        <img src="{{ asset('assets/images/System-logo.png') }}" class="logo" alt="PUPTracker Violation System logo">

        <p class="module-chip">{{ $moduleLabel }} Module</p>
        <h2>Reset Password</h2>
        <p class="subtitle">Set a new password for your account.</p>

        <form id="resetPasswordForm" action="{{ route('password.update', ['guard' => $guard]) }}" method="POST" autocomplete="off">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="mb-3">
                <label class="form-label">Email</label>
                <div class="input-group login-input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $email) }}" autocomplete="email" required>
                </div>
                @error('email')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">New Password</label>
                <div class="input-group login-input-group">
                    <span class="input-group-text"><i class="bi bi-key"></i></span>
                    <input id="newPassword" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" required>
                </div>
                @error('password')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <section class="password-policy" aria-labelledby="password-policy-title">
                <div class="password-policy-heading">
                    <i class="bi bi-shield-check" aria-hidden="true"></i>
                    <h3 id="password-policy-title">Password policy</h3>
                </div>
                <ul>
                    <li data-policy="length"><i class="bi bi-circle" aria-hidden="true"></i> At least 8 characters</li>
                    <li data-policy="lowercase"><i class="bi bi-circle" aria-hidden="true"></i> One lowercase letter</li>
                    <li data-policy="uppercase"><i class="bi bi-circle" aria-hidden="true"></i> One uppercase letter</li>
                    <li data-policy="number"><i class="bi bi-circle" aria-hidden="true"></i> One number</li>
                    <li data-policy="symbol"><i class="bi bi-circle" aria-hidden="true"></i> One symbol</li>
                </ul>
            </section>

            <div class="mb-4">
                <label class="form-label">Confirm Password</label>
                <div class="input-group login-input-group">
                    <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
                    <input id="passwordConfirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password" required>
                </div>
            </div>

            <button id="resetPasswordSubmit" type="submit" class="btn btn-success w-100 login-btn" disabled>Reset Password</button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/password-reset.js') }}"></script>
@endpush
