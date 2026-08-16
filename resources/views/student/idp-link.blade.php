@extends('layouts.auth-portal', ['title' => 'Link Your PUPTracker Account', 'portal' => 'student'])

@section('auth-content')

        <p class="module-chip">Student Module</p>

        <h2>Link Your Student Account</h2>

        <p class="subtitle">
            Your PUP ID was authenticated. Verify your PUPTracker student account to finish linking.
        </p>

        @if (session('info'))
            <div class="alert alert-info">{{ session('info') }}</div>
        @endif

        @if($errors->has('login'))
            <div class="login-error">
                <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
                <span>{{ $errors->first('login') }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('student.idp.link.post') }}" autocomplete="off" class="auth-login-form">
            @csrf

            <div class="mb-3">
                <label class="form-label" for="student_number">PUPTracker Student Number</label>
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
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-success w-100 login-btn">
                Link My Account
            </button>

            <p class="security-note">
                <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
                Your PUP ID is verified securely on the server. You are only confirming which
                PUPTracker student account belongs to you.
            </p>
        </form>

        <div class="footer-links">
            <a href="{{ route('student.login') }}">&larr; Back to Login</a>
        </div>

@endsection
