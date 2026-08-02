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
            Sign in with your IDP student account.
        </p>

        @if($errors->has('login'))
            <div class="login-error mb-3">
                {{ $errors->first('login') }}
            </div>
        @endif

        <form action="{{ route('student.idp.start') }}" method="POST" autocomplete="off">
            @csrf

            <div class="mb-3">
                <label class="form-label">Student Number</label>
                <input
                    type="text"
                    name="student_number"
                    class="form-control @error('student_number') is-invalid @enderror"
                    value="{{ old('student_number') }}"
                    required>

                @error('student_number')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-success w-100 login-btn">
                Continue with IDP
            </button>
        </form>

        <p class="subtitle mt-3 mb-0">
            Student passwords are no longer used for login. Your account is linked using IDP identity and student number.
        </p>

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