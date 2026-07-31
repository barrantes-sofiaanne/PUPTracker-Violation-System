@extends('layouts.app')

@section('title', $moduleLabel . ' Forgot Password')

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

        <p class="module-chip">{{ $moduleLabel }} Module</p>
        <h2>Forgot Password</h2>
        <p class="subtitle">Enter your account email to receive a reset link.</p>

        <form action="{{ route('password.email', ['guard' => $guard]) }}" method="POST" autocomplete="off">
            @csrf

            <div class="mb-4">
                <label class="form-label">Email</label>
                <input
                    type="email"
                    name="email"
                    class="form-control @error('email') is-invalid @enderror"
                    value="{{ old('email') }}"
                    required>
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-success w-100 login-btn">Send Reset Link</button>
        </form>

        <div class="footer-links mt-3">
            <a href="{{ route($guard . '.login') }}">← Back to Login</a>
        </div>
    </div>
</div>
@endsection
