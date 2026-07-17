@extends('layouts.app')

@section('title', 'Student Login')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/student-login.css') }}">
@endpush

@section('content')

<div class="login-container {{ $errors->any() ? 'no-anim' : '' }}">

    <img
        src="{{ asset('assets/images/PUP_logo.png') }}"
        alt="PUP Logo"
        class="logo">

    <div class="welcome-panel">
        <h2>Welcome PUPTians!</h2>
        <p>Access your records and stay updated.</p>
    </div>

    <div class="login-form-wrapper">

        <form method="POST" action="{{ route('student.login.post') }}">

            @csrf

            {{-- Login Error --}}
            @if ($errors->has('login'))
                <p class="message error">
                    {{ $errors->first('login') }}
                </p>
            @endif

            {{-- Success Message --}}
            @if(session('success'))
                <p class="message success">
                    {{ session('success') }}
                </p>
            @endif

            {{-- Student Number --}}
            <div class="input-group">

                <input
                    id="student_number"
                    type="text"
                    name="student_number"
                    placeholder="Student Number"
                    value="{{ old('student_number') }}"
                    required>

                @error('student_number')
                    <span class="error-message">
                        {{ $message }}
                    </span>
                @enderror

            </div>

            {{-- Password --}}
            <div class="input-group">

                <input
                    id="password"
                    type="password"
                    name="password"
                    placeholder="Password"
                    required>

                <span
                    class="password-toggle-icon"
                    onclick="togglePassword()">

                    <svg id="eye-icon"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        style="width:20px;height:20px;">

                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>

                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>

                    </svg>

                </span>

                @error('password')
                    <span class="error-message">
                        {{ $message }}
                    </span>
                @enderror

            </div>

            <button
                type="submit"
                class="login-btn">
                Log In
            </button>

            <div class="form-footer">

                <a
                    href="{{ route('home') }}"
                    class="back-link">
                    Back to Home
                </a>

                <a
                    href="#"
                    class="forgot-password">
                    Forgot password?
                </a>

            </div>

        </form>

    </div>

</div>

@endsection

@push('scripts')
<script src="{{ asset('js/student-login.js') }}"></script>
@endpush