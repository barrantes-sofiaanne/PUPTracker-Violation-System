@extends('layouts.app')

@section('title', 'PUPTracker Violation System')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/landing-page.css') }}">
@endpush

@section('content')

<div class="landing-page">

    {{-- Decorative pointer glow (desktop, non-reduced-motion) --}}
    <div class="lp-glow" aria-hidden="true"></div>

    {{-- ===================== TOP NAVIGATION ===================== --}}
    <header class="lp-nav" id="lpHeader">
        <a href="{{ route('home') }}" class="lp-nav-brand" aria-label="PUPTracker home">
            <img src="{{ asset('assets/images/System-logo.png') }}" alt="PUPTracker logo">
            <span>
                <strong>PUPTracker</strong>
                <small>Violation System</small>
            </span>
        </a>

        <div class="lp-nav-actions">
            <a href="{{ route('login') }}" class="lp-btn lp-btn-outline lp-btn-sm">
                Login
            </a>
        </div>
    </header>

    {{-- ===================== HERO ===================== --}}
    <section class="lp-hero" aria-labelledby="lp-hero-heading">
        <div class="lp-hero-copy">

            <p class="lp-eyebrow" data-reveal="1">
                <i class="bi bi-mortarboard-fill" aria-hidden="true"></i>
                Polytechnic University of the Philippines · Taguig Campus
            </p>

            <h1 id="lp-hero-heading" data-reveal="2">
                Campus conduct records,<br>
                <span class="lp-accent">managed with clarity.</span>
            </h1>

            <p class="lp-lead" data-reveal="3">
                PUPTracker is the University's system for tracking student disciplinary
                records, sanctions, and resolutions — giving the Discipline Office a
                clear, secure space to review and manage campus conduct.
            </p>

            <div class="lp-hero-cta" data-reveal="4">
                <a href="{{ route('login') }}" class="lp-btn lp-btn-primary lp-btn-lg lp-cta-arrow">
                    <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                    Access the Login Dashboard
                    <span class="lp-arrow" aria-hidden="true">→</span>
                </a>
            </div>

            <p class="lp-hero-note" data-reveal="5">
                Students, Security staff, and Administrators each have their own secure portal.
            </p>
        </div>

        {{-- Interactive feature preview --}}
        <div class="lp-hero-visual" data-reveal="6">
            <div class="lp-visual-card" role="tablist" aria-label="PUPTracker feature preview">
                <div class="lp-visual-head">
                    <span class="lp-visual-dot" aria-hidden="true"></span>
                    <span class="lp-visual-title">System Preview</span>
                </div>

                {{-- Disciplinary Records --}}
                <div class="lp-visual-row lp-visual-feature is-active" role="tab" tabindex="0"
                     data-feature="records" aria-selected="true"
                     aria-controls="lp-panel-records">
                    <span class="lp-visual-icon"><i class="bi bi-journal-check" aria-hidden="true"></i></span>
                    <span class="lp-visual-copy">
                        <strong>Disciplinary Records</strong>
                        <small>Centralized, organized, and auditable.</small>
                    </span>
                    <span class="lp-visual-tag">Organized</span>
                </div>
                <div class="lp-visual-panel is-open" id="lp-panel-records" role="tabpanel" aria-label="Disciplinary Records">
                    <div class="lp-panel-line">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i> Organized records
                    </div>
                    <div class="lp-panel-line">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i> Auditable history
                    </div>
                    <div class="lp-panel-line">
                        <i class="bi bi-shield-lock-fill" aria-hidden="true"></i> Secure access
                    </div>
                </div>

                {{-- Sanction Tracking --}}
                <div class="lp-visual-row lp-visual-feature" role="tab" tabindex="0"
                     data-feature="sanctions" aria-selected="false"
                     aria-controls="lp-panel-sanctions">
                    <span class="lp-visual-icon"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
                    <span class="lp-visual-copy">
                        <strong>Sanction Tracking</strong>
                        <small>Completed and outstanding at a glance.</small>
                    </span>
                    <span class="lp-visual-tag">Trackable</span>
                </div>
                <div class="lp-visual-panel" id="lp-panel-sanctions" role="tabpanel" aria-label="Sanction Tracking" hidden>
                    <div class="lp-panel-line">Status tracking</div>
                    <div class="lp-mini-progress" aria-hidden="true">
                        <span class="lp-mini-progress-fill"></span>
                    </div>
                    <div class="lp-panel-line">Clear follow-up</div>
                </div>

                {{-- Reports & Insights --}}
                <div class="lp-visual-row lp-visual-feature" role="tab" tabindex="0"
                     data-feature="reports" aria-selected="false"
                     aria-controls="lp-panel-reports">
                    <span class="lp-visual-icon"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i></span>
                    <span class="lp-visual-copy">
                        <strong>Reports &amp; Insights</strong>
                        <small>Faster tracking and clear reporting.</small>
                    </span>
                    <span class="lp-visual-tag">Insights</span>
                </div>
                <div class="lp-visual-panel" id="lp-panel-reports" role="tabpanel" aria-label="Reports and Insights" hidden>
                    <div class="lp-mini-bars" aria-hidden="true">
                        <span class="lp-mini-bar" style="height: 38%"></span>
                        <span class="lp-mini-bar" style="height: 58%"></span>
                        <span class="lp-mini-bar" style="height: 46%"></span>
                        <span class="lp-mini-bar" style="height: 72%"></span>
                        <span class="lp-mini-bar" style="height: 62%"></span>
                    </div>
                    <div class="lp-panel-line">Structured summaries</div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===================== TRUST STRIP ===================== --}}
    <section class="lp-trust" aria-label="System strengths" data-scroll-reveal>
        <div class="lp-trust-item">
            <i class="bi bi-shield-lock" aria-hidden="true"></i>
            <div>
                <strong>Secure Access</strong>
                <span>Authorized users only</span>
            </div>
        </div>
        <div class="lp-trust-item">
            <i class="bi bi-journal-check" aria-hidden="true"></i>
            <div>
                <strong>Auditable Records</strong>
                <span>Trackable administrative activity</span>
            </div>
        </div>
        <div class="lp-trust-item">
            <i class="bi bi-bar-chart-line" aria-hidden="true"></i>
            <div>
                <strong>Clear Reporting</strong>
                <span>Structured disciplinary insights</span>
            </div>
        </div>
    </section>

    {{-- ===================== HOW IT WORKS ===================== --}}
    <section class="lp-section" aria-labelledby="lp-how-heading" data-scroll-reveal>
        <div class="lp-section-head">
            <h2 id="lp-how-heading">How PUPTracker Works</h2>
            <p>A straightforward path from recording an incident to resolving it.</p>
        </div>

        <div class="lp-steps">
            <article class="lp-step">
                <span class="lp-step-num" aria-hidden="true">01</span>
                <h3>Record</h3>
                <p>Student disciplinary records are centralized and organized.</p>
            </article>
            <article class="lp-step">
                <span class="lp-step-num" aria-hidden="true">02</span>
                <h3>Review</h3>
                <p>Authorized personnel review violations, sanctions, and resolutions.</p>
            </article>
            <article class="lp-step">
                <span class="lp-step-num" aria-hidden="true">03</span>
                <h3>Resolve</h3>
                <p>Sanctions and case outcomes are tracked for proper follow-up.</p>
            </article>
        </div>
    </section>

    {{-- ===================== AI-ASSISTED REVIEW ===================== --}}
    <section class="lp-ai" aria-labelledby="lp-ai-heading" data-scroll-reveal>
        <div class="lp-ai-icon" aria-hidden="true">
            <i class="bi bi-stars"></i>
        </div>
        <div class="lp-ai-copy">
            <h2 id="lp-ai-heading">AI-Assisted Graduation Review</h2>
            <p>
                Assists authorized personnel in screening graduating students'
                disciplinary histories and identifying records requiring
                administrative review. All final decisions remain with authorized staff.
            </p>
        </div>
    </section>

    {{-- ===================== LOGIN CTA ===================== --}}
    <section class="lp-cta-band" aria-labelledby="lp-cta-heading" data-scroll-reveal>
        <h2 id="lp-cta-heading">Ready to access the system?</h2>
        <p>Choose your portal to sign in securely and continue to your dashboard.</p>
        <a href="{{ route('login') }}" class="lp-btn lp-btn-gold lp-btn-lg lp-cta-arrow">
            <i class="bi bi-grid-1x2-fill" aria-hidden="true"></i>
            Go to Login Dashboard
            <span class="lp-arrow" aria-hidden="true">→</span>
        </a>
    </section>

    {{-- ===================== FOOTER ===================== --}}
    <footer class="lp-footer">
        <div class="lp-footer-brand">
            <img src="{{ asset('assets/images/System-logo.png') }}" alt="PUPTracker logo">
            <div>
                <strong>PUPTracker</strong>
                <span>Violation System</span>
            </div>
        </div>

        <div class="lp-footer-info">
            <div>
                <strong>Polytechnic University of the Philippines</strong>
                <span>Taguig Campus</span>
            </div>
        </div>

        <div class="lp-footer-links">
            <a href="https://www.pup.edu.ph/privacy/" target="_blank" rel="noopener noreferrer">
                Privacy &amp; Confidentiality
            </a>
            <a href="mailto:puptrackervs@gmail.com">Help &amp; Support</a>
            <span class="lp-status" id="lpSystemStatus" data-status-url="{{ url('/up') }}" title="System Status">
                <span class="lp-status-dot" aria-hidden="true"></span>
                System Status
            </span>
        </div>

        <p class="lp-footer-note">
            PUPTracker contains confidential student disciplinary information and is accessible only to authorized users.
        </p>

        <p class="lp-footer-copy">© 2026 PUP Taguig</p>
    </footer>

</div>

@endsection

@push('scripts')
<script src="{{ asset('js/landing-page.js') }}"></script>
@endpush
