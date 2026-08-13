@extends('layouts.app')

@section('title', 'PUPTracker Violation System')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/landing-page.css') }}">
@endpush

@section('content')

<div class="landing-page">

    {{-- Top navigation --}}
    <header class="lp-nav">
        <a href="{{ route('home') }}" class="lp-nav-brand">
            <img src="{{ asset('assets/images/System-logo.png') }}" alt="PUPTracker logo">
            <span>
                <strong>PUPTracker</strong>
                <small>Violation System</small>
            </span>
        </a>

        <div class="lp-nav-actions">
            <a href="{{ route('login') }}" class="lp-btn lp-btn-outline">Login</a>
        </div>
    </header>

    {{-- Hero --}}
    <section class="lp-hero">
        <div class="lp-hero-copy">
            <p class="lp-eyebrow">
                <i class="bi bi-mortarboard-fill" aria-hidden="true"></i>
                Polytechnic University of the Philippines · Taguig Campus
            </p>

            <h1>
                Campus conduct records,<br>
                <span class="lp-accent">managed with clarity.</span>
            </h1>

            <p class="lp-lead">
                PUPTracker is the University's system for tracking student disciplinary
                records, sanctions, and resolutions — giving the Discipline Office a
                clear, secure space to review and manage campus conduct.
            </p>

            <div class="lp-hero-cta">
                <a href="{{ route('login') }}" class="lp-btn lp-btn-primary lp-btn-lg">
                    <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                    Access the Login Dashboard
                </a>
            </div>

            <p class="lp-hero-note">
                Students, Security staff, and Administrators each have their own secure portal.
            </p>
        </div>

        <div class="lp-hero-visual">
            <div class="lp-visual-card">
                <div class="lp-visual-row">
                    <span class="lp-visual-icon"><i class="bi bi-journal-check" aria-hidden="true"></i></span>
                    <span class="lp-visual-copy">
                        <strong>Disciplinary Records</strong>
                        <small>Centralized, organized, and auditable.</small>
                    </span>
                    <span class="lp-visual-badge lp-badge-good">On Track</span>
                </div>
                <div class="lp-visual-row">
                    <span class="lp-visual-icon"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
                    <span class="lp-visual-copy">
                        <strong>Sanction Tracking</strong>
                        <small>Completed and outstanding at a glance.</small>
                    </span>
                    <span class="lp-visual-badge lp-badge-warn">In Review</span>
                </div>
                <div class="lp-visual-row">
                    <span class="lp-visual-icon"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i></span>
                    <span class="lp-visual-copy">
                        <strong>Reports &amp; Insights</strong>
                        <small>Faster tracking and clear reporting.</small>
                    </span>
                    <span class="lp-visual-badge lp-badge-flag">Flagged</span>
                </div>
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section class="lp-section">
        <div class="lp-section-head">
            <h2>Built for the Discipline Office</h2>
            <p>Everything needed to record, track, and review student conduct in one place.</p>
        </div>

        <div class="lp-features">
            <div class="lp-feature">
                <i class="bi bi-clipboard-data" aria-hidden="true"></i>
                <h3>Record Violations</h3>
                <p>Log incidents with violation types, dates, and supporting details for a complete history.</p>
            </div>
            <div class="lp-feature">
                <i class="bi bi-shield-check" aria-hidden="true"></i>
                <h3>Assign Sanctions</h3>
                <p>Track every assigned sanction from pending to completed, with deadlines and history.</p>
            </div>
            <div class="lp-feature">
                <i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i>
                <h3>Generate Reports</h3>
                <p>Produce clear disciplinary reports and summaries for faster, better-informed reviews.</p>
            </div>
        </div>
    </section>

    {{-- CTA band --}}
    <section class="lp-cta-band">
        <h2>Ready to access the system?</h2>
        <p>
            Choose your portal to sign in securely and continue to your dashboard.
        </p>
        <a href="{{ route('login') }}" class="lp-btn lp-btn-gold lp-btn-lg">
            <i class="bi bi-grid-1x2-fill" aria-hidden="true"></i>
            Go to Login Dashboard
        </a>
    </section>

    {{-- Footer --}}
    <footer class="lp-footer">
        <div class="lp-footer-inner">
            <div class="lp-footer-links">
                <a href="https://www.pup.edu.ph/privacy/" target="_blank" rel="noopener noreferrer">Privacy Statement</a>
                <span class="lp-footer-sep" aria-hidden="true">•</span>
                <a href="https://www.pup.edu.ph/terms/" target="_blank" rel="noopener noreferrer">Terms of Use</a>
                <span class="lp-footer-sep" aria-hidden="true">•</span>
                <a href="mailto:puptrackervs@gmail.com">Contact Us</a>
            </div>
            <span class="lp-footer-version">Version 2.0.0</span>
        </div>
    </footer>

</div>

@endsection
