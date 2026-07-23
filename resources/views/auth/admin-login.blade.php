@extends('layouts.app')

@section('title', 'Admin Login')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/student-login.css') }}">
@endpush

@section('content')

<div class="login-page">

    <div class="login-card">

        <img
            src="{{ asset('assets/images/PUP_logo.png') }}"
            class="logo"
            alt="PUP Logo">

        <h2>Administrator Login</h2>

        <p class="subtitle">
            Sign in using your Email.
        </p>

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->has('login'))
            <div class="alert alert-danger">
                {{ $errors->first('login') }}
            </div>
        @endif

        <form
            action="{{ route('admin.login.post') }}"
            method="POST"
            autocomplete="off">

            @csrf

            <div class="mb-3">

                <label class="form-label">
                    Email
                </label>

                <input
                    type="text"
                    name="email"
                    class="form-control @error('email') is-invalid @enderror"
                    value="{{ old('email') }}"
                    required>

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

                    <input
                        id="password"
                        type="password"
                        name="password"
                        class="form-control @error('password') is-invalid @enderror"
                        required>

                    <button
                        type="button"
                        class="toggle-password"
                        id="togglePassword">

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

            <a href="#">
                Forgot Password?
            </a>

        </div>

    </div>

</div>

@endsection

@push('scripts')
<script src="{{ asset('js/student-login.js') }}"></script>
@endpush