@extends('layouts.app')

@section('title', 'PUPTracker Violation System')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/landing.css') }}">
@endpush

@section('content')

<div class="landing-page">


    <main class="landing-shell">
        <section class="landing-intro">
            <div class="landing-heading">
                <div class="landing-logo-wrap">
                    <img
                        src="{{ asset('assets/images/System-logo.png') }}"
                        class="logo"
                        alt="PUPTracker Violation System logo">
                </div>

                <div>
                    <p class="system-pill">Polytechnic University of the Philippines</p>
                    <h1>PUPTracker</h1>
                    <h5>Violation Management System</h5>
                </div>
            </div>

            <p class="description">
                A clear, secure space for managing campus conduct records and staying informed.
            </p>

            <div class="highlights">
                <span><i class="bi bi-shield-check"></i> Secure Access</span>
                <span><i class="bi bi-clipboard2-check"></i> Organized Records</span>
                <span><i class="bi bi-graph-up-arrow"></i> Faster Tracking</span>
            </div>

            <div class="footer-links">

                <a href="https://www.pup.edu.ph/privacy/" target="_blank">
                    Privacy Statement
                </a>

                <span class="footer-separator" aria-hidden="true">•</span>

                <a href="https://www.pup.edu.ph/terms/" target="_blank">
                    Terms of Use
                </a>

                <span class="footer-separator" aria-hidden="true">•</span>

                <a href="mailto:puptrackervs@gmail.com">
                    puptrackervs@gmail.com
                </a>

            </div>
        </section>

        <section class="landing-access">
            <div class="portal-buttons">

                <a href="{{ route('student.login') }}" class="portal-btn">
                    <span class="portal-icon"><i class="bi bi-mortarboard-fill"></i></span>
                    <span class="portal-copy">
                        <strong>Student Portal</strong>
                        <small>View your records and updates</small>
                    </span>
                    <i class="bi bi-arrow-up-right portal-arrow" aria-hidden="true"></i>
                </a>

                <a href="{{ route('security.login') }}" class="portal-btn">
                    <span class="portal-icon"><i class="bi bi-shield-lock-fill"></i></span>
                    <span class="portal-copy">
                        <strong>Security Portal</strong>
                        <small>Manage campus conduct reports</small>
                    </span>
                    <i class="bi bi-arrow-up-right portal-arrow" aria-hidden="true"></i>
                </a>

                <a href="{{ route('admin.login') }}" class="portal-btn">
                    <span class="portal-icon"><i class="bi bi-person-workspace"></i></span>
                    <span class="portal-copy">
                        <strong>Administrator</strong>
                        <small>Oversee records and system activity</small>
                    </span>
                    <i class="bi bi-arrow-up-right portal-arrow" aria-hidden="true"></i>
                </a>

            </div>
        </section>
    </main>

</div>

@endsection