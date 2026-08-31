@extends('layouts.auth-portal', ['title' => 'Student Login', 'portal' => 'student'])

@section('auth-content')

        <p class="module-chip">Student Module</p>

        <h2>Student Login</h2>

        <p class="subtitle">
            Sign in using your IDP account.
        </p>

        @if($errors->has('login'))
            <div class="login-error">
                <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
                <span>{{ $errors->first('login') }}</span>
            </div>
        @endif

        <a
            href="{{ route('student.idp.login') }}"
            class="btn btn-success w-100 login-btn"
            role="button">

            <span class="login-btn-label">Continue with PUP IDP</span>

        </a>

        <p class="security-note">
            <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
            You will be securely redirected to the PUP Identity Provider to sign in.
        </p>

        <div class="footer-links">

            <a href="{{ route('login') }}">
                &larr; Back
            </a>

        </div>

@endsection