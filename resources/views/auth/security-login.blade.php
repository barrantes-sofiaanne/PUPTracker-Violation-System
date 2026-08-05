@extends('layouts.app')

@section('title', 'Security Login')

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

        <p class="module-chip">Security Module</p>

        <h2>Security Login</h2>

        <p class="subtitle">
            Sign in using your Email.
        </p>

        <form
            action="{{ route('security.login.post') }}"
            method="POST"
            autocomplete="off">

            @csrf

            @if($errors->has('login'))
                <div class="login-error">
                    {{ $errors->first('login') }}
                </div>
            @endif

            <div class="mb-3">

                <label class="form-label">
                    Email
                </label>

                <div class="input-group login-input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input
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

                <label class="form-label">
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

            <button
                type="submit"
                class="btn btn-success w-100 login-btn">

                Login

            </button>

        </form>

        <div class="footer-links">

            <a href="{{ route('home') }}">
                ← Back to Home
            </a>

            <a href="{{ route('password.request', ['guard' => 'security']) }}">
                Forgot Password?
            </a>

        </div>

    </div>

</div>

@endsection

@push('scripts')
<script src="{{ asset('js/student-login.js') }}"></script>
@endpush