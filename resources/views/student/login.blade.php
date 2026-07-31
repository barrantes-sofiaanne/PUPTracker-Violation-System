@extends('layouts.app')

@section('title', 'Student Login')

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

        <p class="module-chip">Student Module</p>

        <h2>Student Login</h2>

        <p class="subtitle">
            Sign in using your Student Number.
        </p>

        <form
            action="{{ route('student.login.post') }}"
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
                    Student Number
                </label>

                <input
                    type="text"
                    name="student_number"
                    class="form-control @error('student_number') is-invalid @enderror"
                    value="{{ old('student_number') }}"
                    required>

                @error('student_number')
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

            <a href="{{ route('password.request', ['guard' => 'student']) }}">
                Forgot Password?
            </a>

        </div>

    </div>

</div>

@endsection

@push('scripts')
<script src="{{ asset('js/student-login.js') }}"></script>
@endpush