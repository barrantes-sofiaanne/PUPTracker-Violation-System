@extends('layouts.auth-portal', ['title' => 'Student Login', 'portal' => 'student'])

@section('ap-content')
    <div class="ap-login">

        <span class="ap-icon" aria-hidden="true">
            <i class="bi bi-mortarboard-fill"></i>
        </span>

        <h2 class="ap-heading">Student Portal</h2>
        <p class="ap-subtitle">Sign in to your account.</p>

        {{-- Password-reset confirmation: the server's own message, shown only
             after a reset completed (it is flashed by the reset action). --}}
        @if(session('status'))
            <div class="ap-alert ap-alert--success" role="status">
                <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if($errors->has('login'))
            <div class="ap-alert" role="alert">
                <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
                <span>{{ $errors->first('login') }}</span>
            </div>
        @endif

        {{-- Existing student sign-in: PUP IDP. --}}
        <a href="{{ route('student.idp.login') }}"
           class="ap-btn"
           role="button"
           data-idp-redirect>
            <span>Sign In with PUP IDP</span>
            <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </a>

        <p class="ap-note">
            You will securely continue to the PUP Identity Provider.
        </p>

        <a href="{{ route('login') }}"
           class="ap-back"
           data-soft-nav
           data-portal-theme="access">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            <span>Back to portal selection</span>
        </a>

    </div>
@endsection
