{{--
    Reset Password — step 1 of 3 (Verify Account).

    Presentation only. The existing process is unchanged: the registered email
    is posted to password.email, the server answers with the same generic
    message whether or not an account exists, and the reset itself happens
    through the emailed link (see reset-password.blade.php).
--}}
@php
    // The shared Security / OSS Staff portal keeps its own colours: the login
    // page hands over "?portal=oss" and it is carried through this page.
    $isOss = $guard === 'security' && request()->query('portal') === 'oss';
    $portalKey = $isOss ? 'oss' : $guard;
    $loginParams = $isOss ? ['portal' => 'oss'] : [];
@endphp

@extends('layouts.auth-portal', [
    'title'    => $moduleLabel . ' Forgot Password',
    'portal'   => $isOss ? 'oss' : $guard,
    'brandKey' => 'reset',
])

@section('ap-content')
    <div class="ap-login ap-reset">

        <ol class="ap-steps" aria-label="Password reset progress">
            <li class="ap-step is-current" aria-current="step">
                <span class="ap-step-dot">1</span>
                <span class="ap-step-label">Verify Account</span>
            </li>
            <li class="ap-step">
                <span class="ap-step-dot">2</span>
                <span class="ap-step-label">Reset Password</span>
            </li>
            <li class="ap-step">
                <span class="ap-step-dot">3</span>
                <span class="ap-step-label">Complete</span>
            </li>
        </ol>

        <span class="ap-icon" aria-hidden="true">
            <i class="bi bi-envelope-check-fill"></i>
        </span>

        <h2 class="ap-heading">Forgot Password</h2>
        <p class="ap-subtitle">
            Enter your registered email address to receive a reset link.
        </p>

        @if(session('status'))
            <div class="ap-alert ap-alert--success" role="status">
                <i class="bi bi-envelope-check" aria-hidden="true"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <form action="{{ route('password.email', ['guard' => $guard]) }}"
              method="POST"
              autocomplete="off"
              class="ap-form">

            @csrf

            <div class="ap-field-group">
                <label class="ap-label" for="email">Email address</label>

                <div class="ap-field @error('email') has-error @enderror">
                    <i class="bi bi-envelope ap-field-icon" aria-hidden="true"></i>
                    <input id="email"
                           type="email"
                           name="email"
                           placeholder="Registered email address"
                           value="{{ old('email') }}"
                           autocomplete="email"
                           aria-describedby="@error('email')emailError @enderror"
                           required>
                </div>

                @error('email')
                    <p class="ap-field-error" id="emailError">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="ap-btn">
                <span>Send Reset Link</span>
                <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </button>
        </form>

        <a href="{{ route($guard . '.login', $loginParams) }}"
           class="ap-back"
           data-soft-nav
           data-portal-theme="{{ $portalKey }}">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            <span>Back to Login</span>
        </a>

    </div>
@endsection
