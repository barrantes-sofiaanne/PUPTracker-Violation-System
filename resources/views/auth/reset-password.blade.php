@extends('layouts.app')

@section('title', $moduleLabel . ' Reset Password')

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
        <h2>Reset Password</h2>
        <p class="subtitle">Set a new password for your account.</p>

        <form action="{{ route('password.update', ['guard' => $guard]) }}" method="POST" autocomplete="off">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input
                    type="email"
                    name="email"
                    class="form-control @error('email') is-invalid @enderror"
                    value="{{ old('email', $email) }}"
                    required>
                @error('email')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input
                    type="password"
                    name="password"
                    class="form-control @error('password') is-invalid @enderror"
                    required>
                @error('password')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">Confirm Password</label>
                <input
                    type="password"
                    name="password_confirmation"
                    class="form-control"
                    required>
            </div>

            <button type="submit" class="btn btn-success w-100 login-btn">Reset Password</button>
        </form>
    </div>
</div>
@endsection
