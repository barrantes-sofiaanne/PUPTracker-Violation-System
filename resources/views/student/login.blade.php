@extends('layouts.auth-portal', ['title' => 'Student Login', 'portal' => 'student'])

@section('auth-content')

        <p class="module-chip">Student Module</p>

        <h2>Student Login</h2>

        <p class="subtitle">
            Sign in using your PUP account to access your records.
        </p>

        <form
            action="{{ route('student.idp.start') }}"
            method="POST"
            autocomplete="off"
            class="auth-login-form">

            @csrf

            @if($errors->has('login'))
                <div class="login-error">
                    <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
                    <span>{{ $errors->first('login') }}</span>
                </div>
            @endif

            <div class="mb-3">

                <label class="form-label" for="student_number">
                    Student Number
                </label>

                <div class="input-group login-input-group">
                    <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                    <input
                        id="student_number"
                        type="text"
                        name="student_number"
                        class="form-control @error('student_number') is-invalid @enderror"
                        value="{{ old('student_number') }}"
                        placeholder="e.g. 2026-00001"
                        autocomplete="off"
                        required>
                </div>

                @error('student_number')
                    <div class="invalid-feedback d-block">
                        {{ $message }}
                    </div>
                @enderror

            </div>

            <button
                type="submit"
                class="btn btn-success w-100 login-btn">

                Continue with PUP IDP

            </button>

        </form>

        <div class="footer-links">

            <a href="{{ route('home') }}">
                &larr; Back to Home
            </a>

            <a href="{{ route('password.request', ['guard' => 'student']) }}">
                Forgot Password?
            </a>

        </div>

@endsection