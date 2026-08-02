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
            Student login is temporarily unavailable while IDP access is being updated.
        </p>

        @if($errors->has('login'))
            <div class="login-error mb-3">
                {{ $errors->first('login') }}
            </div>
        @endif

        {{-- IDP student login is temporarily disabled. --}}
        <div class="alert alert-warning mb-0" role="alert">
            IDP sign-in for students is currently disabled. Please try again later.
        </div>

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