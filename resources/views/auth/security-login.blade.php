{{--
    Shared Security + OSS Staff login (one route: /security/login).
    "?portal=oss" switches the branding to the OSS Staff module; the credentials
    still post to the same route, which decides the account type.
--}}
@extends('layouts.auth-portal', [
    'title' => request()->query('portal') === 'oss'
        ? 'OSS Staff Portal'
        : 'Security Portal',
    'portal' => request()->query('portal') === 'oss'
        ? 'oss'
        : 'security',
])

@section('ap-content')
    <div class="ap-login">

        <span class="ap-icon" aria-hidden="true">
            <i class="bi {{ request()->query('portal') === 'oss' ? 'bi-people-fill' : 'bi-shield-fill-check' }}"></i>
        </span>

        <h2 class="ap-heading">
            {{ request()->query('portal') === 'oss' ? 'OSS Staff Portal' : 'Security Portal' }}
        </h2>
        <p class="ap-subtitle">Sign in to your account.</p>

        {{-- Password-reset confirmation: the server's own message, shown only
             after a reset completed (it is flashed by the reset action). --}}
        @if(session('status'))
            <div class="ap-alert ap-alert--success" role="status">
                <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <form action="{{ route('security.login.post') }}"
              method="POST"
              autocomplete="off"
              class="ap-form">

            @csrf

            @if($errors->has('login'))
                <div class="ap-alert" role="alert">
                    <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
                    <span>{{ $errors->first('login') }}</span>
                </div>
            @endif

            @php
                $expiredReason =
                    \App\Support\Auth\SessionExpiryNotice::pullFromCurrentRequest();
            @endphp

            @if($expiredReason)
                <div class="ap-alert" role="alert">
                    <i class="bi bi-clock-history" aria-hidden="true"></i>
                    <span>{{ $expiredReason }}</span>
                </div>
            @endif

            <div class="ap-field-group">
                <label class="ap-sr" for="email">Email</label>

                <div class="ap-field @error('email') has-error @enderror">
                    <i class="bi bi-envelope ap-field-icon" aria-hidden="true"></i>
                    <input id="email"
                           type="email"
                           name="email"
                           placeholder="Email address"
                           value="{{ old('email') }}"
                           autocomplete="email"
                           required>
                </div>

                @error('email')
                    <p class="ap-field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="ap-field-group">
                <label class="ap-sr" for="password">Password</label>

                <div class="ap-field ap-field--password @error('password') has-error @enderror">
                    <i class="bi bi-lock ap-field-icon" aria-hidden="true"></i>
                    <input id="password"
                           type="password"
                           name="password"
                           placeholder="Password"
                           autocomplete="current-password"
                           required>

                    <button type="button"
                            class="ap-toggle"
                            data-toggle-password
                            aria-controls="password"
                            aria-label="Show password">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                </div>

                @error('password')
                    <p class="ap-field-error">{{ $message }}</p>
                @enderror
            </div>

            {{-- Remember Me remains on the existing MFA verification screen. --}}

            <div class="ap-row">
                <a href="{{ route('password.request', ['guard' => 'security'] + (request()->query('portal') === 'oss' ? ['portal' => 'oss'] : [])) }}" class="ap-link">
                    Forgot password?
                </a>
            </div>

            <button type="submit" class="ap-btn">
                <span>Sign In</span>
                <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </button>
        </form>

        <a href="{{ route('login') }}"
           class="ap-back"
           data-soft-nav
           data-portal-theme="access">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            <span>Back to portal selection</span>
        </a>

    </div>
@endsection
