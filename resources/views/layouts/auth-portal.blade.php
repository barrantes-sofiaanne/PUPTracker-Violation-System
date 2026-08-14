@extends('layouts.app')

@section('title', $title ?? 'PUPTracker')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/student-login.css') }}">
@endpush

@section('content')
<main class="auth-portal-page portal-{{ $portal ?? 'access' }}" data-auth-spa-root>
    <div class="auth-ambient-layer" aria-hidden="true">
        <span class="auth-ambient-orb orb-one"></span>
        <span class="auth-ambient-orb orb-two"></span>
        <span class="auth-ambient-orb orb-three"></span>
    </div>

    <section class="auth-portal-shell" data-auth-shell>
        <aside class="auth-portal-brand">
            <div class="auth-brand-heading">
                <a href="{{ route('login') }}" class="auth-brand-mark" aria-label="Back to Access Portal">
                    <img src="{{ asset('assets/images/System-logo.png') }}" alt="PUPTracker Violation System logo">
                </a>
                <div>
                    <p class="auth-brand-campus">Polytechnic University of the Philippines Taguig Campus</p>
                    <h1>PUPTracker</h1>
                    <p class="auth-brand-system">Violation System</p>
                </div>
            </div>
            <p class="auth-brand-description">Secure access to campus conduct records and updates.</p>
            <div class="auth-brand-highlights">
                <span><i class="bi bi-shield-check" aria-hidden="true"></i> Secure Access</span>
                <span><i class="bi bi-graph-up-arrow" aria-hidden="true"></i> Faster Tracking</span>
                <span><i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i> Generating Reports</span>
            </div>
            <div class="auth-brand-footer">
                <a href="https://www.pup.edu.ph/privacy/" target="_blank" rel="noopener noreferrer">Privacy Statement</a>
                <span class="auth-brand-separator" aria-hidden="true">•</span>
                <a href="https://www.pup.edu.ph/terms/" target="_blank" rel="noopener noreferrer">Terms of Use</a>
                <span class="auth-brand-separator" aria-hidden="true">•</span>
                <a href="mailto:puptrackervs@gmail.com">Contact Us</a>
            </div>
            <span class="auth-brand-version">Version 2.0.0</span>
        </aside>

        <section class="auth-portal-content">
            <div class="auth-content-card" data-portal="{{ $portal ?? 'access' }}">
                @yield('auth-content')
            </div>
        </section>
    </section>
</main>
@endsection

@push('scripts')
<script src="{{ asset('js/student-login.js') }}"></script>
<script src="{{ asset('js/password-reset.js') }}"></script>
<script src="{{ asset('js/portal-transitions.js') }}"></script>
@endpush



