@extends('layouts.app')

@section('title', $title ?? 'PUPTracker')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/student-login.css') }}">
@endpush

@section('content')
<main class="auth-portal-page">
    <section class="auth-portal-shell">
        <aside class="auth-portal-brand">
            <a href="{{ route('home') }}" class="auth-brand-mark" aria-label="Back to PUPTracker home">
                <img src="{{ asset('assets/images/System-logo.png') }}" alt="PUPTracker Violation System logo">
            </a>
            <p class="auth-brand-campus">Polytechnic University of the Philippines</p>
            <h1>PUPTracker</h1>
            <p class="auth-brand-system">Violation Management System</p>
            <p class="auth-brand-description">A clear, secure space for managing campus conduct records and staying informed.</p>
        </aside>

        <section class="auth-portal-content">
            <div class="auth-content-card">
                @yield('auth-content')
            </div>
        </section>
    </section>
</main>
@endsection
