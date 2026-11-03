
@extends('layouts.app')

@section('title', 'PUPTracker | A Safer, More Accountable PUP')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/landing-page.css') }}">
@endpush

@section('content')
<div class="landing-page" id="top">

    {{-- =====================================================
         NAVIGATION
    ====================================================== --}}
    <header class="lp-nav" id="lpHeader">

        <a href="{{ route('home') }}"
           class="lp-brand"
           aria-label="PUPTracker home">

            <img
                src="{{ asset('assets/images/SystemLogo_w/name.png') }}"
                alt="PUPTracker logo"
            >

            <span class="lp-brand-text">
                <strong>PUPTracker</strong>
                <small>Violation System</small>
            </span>
        </a>

        <button
            class="lp-menu-toggle"
            type="button"
            aria-label="Toggle navigation"
            aria-expanded="false"
            aria-controls="lpNavigation"
        >
            <span></span>
            <span></span>
            <span></span>
        </button>

        <nav
            class="lp-navigation"
            id="lpNavigation"
            aria-label="Main navigation"
        >
            <a href="#top" class="is-current">Home</a>
            <a href="#about">About</a>
            <a href="#modules">Modules</a>
            <a href="#guidelines">Guidelines</a>
            <a href="#contact">Contact</a>

            <a href="{{ route('login') }}" class="lp-login">
                <i class="bi bi-person-lock" aria-hidden="true"></i>
                Login
            </a>
        </nav>
    </header>


    <main id="main-content">

        {{-- =================================================
             HERO
        ================================================== --}}
        <section
            class="lp-hero"
            id="home"
            aria-labelledby="lp-hero-title"
        >

            <div class="lp-hero-image" aria-hidden="true"></div>
            <div class="lp-hero-overlay" aria-hidden="true"></div>
            <div class="lp-hero-decoration" aria-hidden="true"></div>

            <div class="lp-hero-content">

                <p class="lp-eyebrow" data-scroll-reveal>
                    <span></span>
                    Polytechnic University of the Philippines
                    · Taguig Campus
                </p>

                <h1 id="lp-hero-title" data-scroll-reveal>
                    Empowering a Safer and More Accountable
                    <span>PUP.</span>
                </h1>

                <p class="lp-hero-description" data-scroll-reveal>
                    PUPTracker is a web-based violation tracking system
                    that promotes accountability, transparency, and a
                    disciplined campus community.
                </p>

                <div class="lp-hero-actions" data-scroll-reveal>

                    <a
                        href="{{ route('login') }}"
                        class="lp-button lp-button-primary"
                    >
                        Login to System
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </a>

                    <a
                        href="#about"
                        class="lp-button lp-button-outline"
                    >
                        Learn More
                        <i class="bi bi-arrow-down" aria-hidden="true"></i>
                    </a>

                </div>

                <div class="lp-hero-values" data-scroll-reveal
                     aria-label="PUPTracker values">

                    <div class="lp-hero-value">
                        <span class="lp-value-icon">
                            <i class="bi bi-shield-check"
                               aria-hidden="true"></i>
                        </span>

                        <span>
                            <strong>Safer</strong>
                            <small>Campus</small>
                        </span>
                    </div>

                    <div class="lp-hero-value">
                        <span class="lp-value-icon">
                            <i class="bi bi-file-earmark-text"
                               aria-hidden="true"></i>
                        </span>

                        <span>
                            <strong>Transparent</strong>
                            <small>Process</small>
                        </span>
                    </div>

                    <div class="lp-hero-value">
                        <span class="lp-value-icon">
                            <i class="bi bi-people"
                               aria-hidden="true"></i>
                        </span>

                        <span>
                            <strong>Accountable</strong>
                            <small>Community</small>
                        </span>
                    </div>

                </div>
            </div>

            <div class="lp-hero-signature">
                <span></span>
                <p>
                    Mula sa'yo<br>
                    Para sa Bayan
                </p>
            </div>

        </section>


        {{-- =================================================
             SYSTEM MODULES
        ================================================== --}}
        <section
            class="lp-modules"
            id="modules"
            aria-labelledby="lp-modules-title"
        >

            <div class="section-wrap">

                <div
                    class="lp-section-heading"
                    data-scroll-reveal
                >
                    <span class="lp-section-label">System Modules</span>

                    <h2 id="lp-modules-title">
                        Designed for a Stronger Campus Community
                    </h2>

                    <p>
                        Each module supports the responsibilities of its
                        authorized users, working together for a more
                        disciplined and safer PUP.
                    </p>
                </div>

                <div class="lp-module-grid">

                    {{-- STUDENT MODULE --}}
                    <article
                        class="lp-module-card"
                        data-scroll-reveal="left"
                    >

                        <div class="lp-module-heading">
                            <span class="lp-module-icon">
                                <i class="bi bi-mortarboard"
                                   aria-hidden="true"></i>
                            </span>

                            <h3>Student Module</h3>
                        </div>

                        <p>
                            View violation records, announcements,
                            notifications, and disciplinary status.
                        </p>

                        <div class="lp-module-preview">
                            <img
                                src="{{ asset('assets/images/student-dashboard-preview.png') }}"
                                alt="Student module dashboard preview"
                                loading="lazy"
                            >
                        </div>

                        <a
                            href="{{ route('login') }}"
                            class="lp-module-link"
                            aria-label="Login to access the Student Module"
                        >
                            <span>For Students</span>
                            <i class="bi bi-arrow-right"
                               aria-hidden="true"></i>
                        </a>

                    </article>


                    {{-- SECURITY MODULE --}}
                    <article
                        class="lp-module-card"
                        data-scroll-reveal="zoom"
                    >

                        <div class="lp-module-heading">
                            <span class="lp-module-icon">
                                <i class="bi bi-shield-check"
                                   aria-hidden="true"></i>
                            </span>

                            <h3>Security Module</h3>
                        </div>

                        <p>
                            Record violations, capture supporting
                            evidence, and manage violation reports.
                        </p>

                        <div class="lp-module-preview">
                            <img
                                src="{{ asset('assets/images/security-dashboard-preview.png') }}"
                                alt="Security module dashboard preview"
                                loading="lazy"
                            >
                        </div>

                        <a
                            href="{{ route('login') }}"
                            class="lp-module-link"
                            aria-label="Login to access the Security Module"
                        >
                            <span>For Security Personnel</span>
                            <i class="bi bi-arrow-right"
                               aria-hidden="true"></i>
                        </a>

                    </article>


                    {{-- OSS STAFF MODULE --}}
                    <article
                        class="lp-module-card"
                        data-scroll-reveal="zoom"
                    >

                        <div class="lp-module-heading">
                            <span class="lp-module-icon">
                                <i class="bi bi-people"
                                   aria-hidden="true"></i>
                            </span>

                            <h3>OSS Staff Module</h3>
                        </div>

                        <p>
                            Review submitted violations, process
                            records, and support student discipline.
                        </p>

                        <div class="lp-module-preview">
                            <img
                                src="{{ asset('assets/images/oss-dashboard-preview.png') }}"
                                alt="OSS staff module dashboard preview"
                                loading="lazy"
                            >
                        </div>

                        <a
                            href="{{ route('login') }}"
                            class="lp-module-link lp-module-link-gold"
                            aria-label="Login to access the OSS Staff Module"
                        >
                            <span>For OSS Staff</span>
                            <i class="bi bi-arrow-right"
                               aria-hidden="true"></i>
                        </a>

                    </article>


                    {{-- ADMINISTRATOR MODULE --}}
                    <article
                        class="lp-module-card"
                        data-scroll-reveal="right"
                    >

                        <div class="lp-module-heading">
                            <span class="lp-module-icon">
                                <i class="bi bi-gear"
                                   aria-hidden="true"></i>
                            </span>

                            <h3>Administrator Module</h3>
                        </div>

                        <p>
                            Manage authorized system settings, user
                            access, violation records, and reports.
                        </p>

                        <div class="lp-module-preview">
                            <img
                                src="{{ asset('assets/images/admin-dashboard-preview.png') }}"
                                alt="Administrator module dashboard preview"
                                loading="lazy"
                            >
                        </div>

                        <a
                            href="{{ route('login') }}"
                            class="lp-module-link"
                            aria-label="Login to access the Administrator Module"
                        >
                            <span>For Administrators</span>
                            <i class="bi bi-arrow-right"
                               aria-hidden="true"></i>
                        </a>

                    </article>

                </div>
            </div>
        </section>


        {{-- =================================================
             ABOUT PUPTRACKER
        ================================================== --}}
        <section
            class="lp-about section-wrap"
            id="about"
            aria-labelledby="lp-about-title"
        >

            <div
                class="lp-about-copy"
                data-scroll-reveal="left"
            >
                <span class="lp-section-label">About PUPTracker</span>

                <h2 id="lp-about-title">
                    Supporting a Disciplined and Safer University
                </h2>

                <p>
                    PUPTracker is a web-based violation tracking system
                    developed to support the Polytechnic University of
                    the Philippines. It aims to streamline the reporting,
                    monitoring, and management of student violations.
                </p>

                <p>
                    Through organized disciplinary records and
                    role-based access, the system helps students and
                    authorized university personnel access information
                    relevant to their responsibilities.
                </p>

                <p>
                    PUPTracker supports a more transparent and organized
                    disciplinary process in accordance with applicable
                    university policies.
                </p>

                <a
                    href="{{ route('login') }}"
                    class="lp-button lp-button-maroon"
                >
                    Explore PUPTracker
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>
            </div>


            <div
                class="lp-about-visual"
                data-scroll-reveal="right"
            >

                <div class="lp-slideshow lp-about-slideshow" data-slideshow role="group" aria-label="PUP Taguig campus photos">
                    <img class="lp-slide is-active"
                         src="{{ asset('assets/images/PUP-TAGUIG/PUPT1.jpg') }}"
                         alt="PUP Taguig students during a campus event"
                         loading="lazy">
                    <img class="lp-slide"
                         src="{{ asset('assets/images/PUP-TAGUIG/PUPT5.jpg') }}"
                         alt="PUP Taguig campus"
                         loading="lazy">
                    <img class="lp-slide"
                         src="{{ asset('assets/images/PUP-TAGUIG/PUPT6.jpg') }}"
                         alt="PUP Taguig campus"
                         loading="lazy">
                    <img class="lp-slide"
                         src="{{ asset('assets/images/PUP-TAGUIG/PUPT9.jpg') }}"
                         alt="PUP Taguig campus"
                         loading="lazy">
                    <img class="lp-slide"
                         src="{{ asset('assets/images/PUP-TAGUIG/PUPT10.jpg') }}"
                         alt="PUP Taguig campus"
                         loading="lazy">
                    <img class="lp-slide"
                         src="{{ asset('assets/images/PUP-TAGUIG/PUPT11.jpg') }}"
                         alt="PUP Taguig campus"
                         loading="lazy">
                    <img class="lp-slide"
                         src="{{ asset('assets/images/PUP-TAGUIG/PUPT12.jpg') }}"
                         alt="PUP Taguig campus"
                         loading="lazy">
                    <img class="lp-slide"
                         src="{{ asset('assets/images/PUP-TAGUIG/PUPT13.jpg') }}"
                         alt="PUP Taguig campus"
                         loading="lazy">
                    <img class="lp-slide"
                         src="{{ asset('assets/images/PUP-TAGUIG/PUPT14.jpg') }}"
                         alt="PUP Taguig campus"
                         loading="lazy">
                    <img class="lp-slide"
                         src="{{ asset('assets/images/PUP-TAGUIG/PUPT15.jpg') }}"
                         alt="PUP Taguig campus"
                         loading="lazy">
                    <img class="lp-slide"
                         src="{{ asset('assets/images/PUP-TAGUIG/PUPT16.jpg') }}"
                         alt="PUP Taguig campus"
                         loading="lazy">
                    <img class="lp-slide"
                         src="{{ asset('assets/images/PUP-TAGUIG/PUPT18.jpg') }}"
                         alt="PUP Taguig campus"
                         loading="lazy">
                    <img class="lp-slide"
                         src="{{ asset('assets/images/PUP-TAGUIG/PUPT19.jpg') }}"
                         alt="PUP Taguig campus"
                         loading="lazy">
                    <div class="lp-slideshow-dots" aria-hidden="true"></div>
                </div>

                <div class="lp-about-statistics">

                    <div class="lp-stat-card">
                        <span>
                            <i class="bi bi-shield-check"
                               aria-hidden="true"></i>
                        </span>

                        <div>
                            <strong>Accountability</strong>
                            <small>Responsible conduct</small>
                        </div>
                    </div>

                    <div class="lp-stat-card">
                        <span>
                            <i class="bi bi-journal-check"
                               aria-hidden="true"></i>
                        </span>

                        <div>
                            <strong>Organization</strong>
                            <small>Structured records</small>
                        </div>
                    </div>

                    <div class="lp-stat-card">
                        <span>
                            <i class="bi bi-people"
                               aria-hidden="true"></i>
                        </span>

                        <div>
                            <strong>Community</strong>
                            <small>A safer campus</small>
                        </div>
                    </div>

                </div>
            </div>

        </section>

                {{-- =================================================
             OUR GOAL
        ================================================== --}}
        <section
            class="lp-purpose"
            aria-labelledby="lp-purpose-title"
        >

            <div class="lp-purpose-image lp-slideshow" data-slideshow role="group" aria-label="PUP Taguig campus slideshow">
                <img class="lp-slide is-active"
                     src="{{ asset('assets/images/PUP-TAGUIG/PUPT3.jpg') }}"
                     alt="PUP Taguig campus grounds"
                     loading="lazy">
                <img class="lp-slide"
                     src="{{ asset('assets/images/PUP-TAGUIG/PUPT4.jpg') }}"
                     alt="PUP Taguig campus"
                     loading="lazy">
                <img class="lp-slide"
                     src="{{ asset('assets/images/PUP-TAGUIG/PUPT7.jpg') }}"
                     alt="PUP Taguig campus"
                     loading="lazy">
                <img class="lp-slide"
                     src="{{ asset('assets/images/PUP-TAGUIG/PUPT19.jpg') }}"
                     alt="PUP Taguig campus"
                     loading="lazy">
                <img class="lp-slide"
                     src="{{ asset('assets/images/PUP-TAGUIG/PUPT20.jpg') }}"
                     alt="PUP Taguig campus"
                     loading="lazy">
                <img class="lp-slide"
                     src="{{ asset('assets/images/PUP-TAGUIG/PUPT21.jpg') }}"
                     alt="PUP Taguig campus"
                     loading="lazy">
                <img class="lp-slide"
                     src="{{ asset('assets/images/PUP-TAGUIG/PUPT23.jpg') }}"
                     alt="PUP Taguig campus"
                     loading="lazy">
                <div class="lp-slideshow-dots" aria-hidden="true"></div>
            </div>
            <div
                class="lp-purpose-content"
                data-scroll-reveal="left"
            >
                <span class="lp-section-label">Our Goal</span>

                <h2 id="lp-purpose-title">
                    A Culture of Responsibility and Respect
                </h2>

                <p>
                    We envision a PUP community where every student
                    is accountable, respects university policies,
                    and contributes to a safe, orderly, and
                    conducive learning environment.
                </p>

                <div class="lp-purpose-values">

                    <span>
                        <i class="bi bi-bullseye"
                           aria-hidden="true"></i>
                        Accountability
                    </span>

                    <span>
   <i class="bi-person-check" aria-hidden="true"></i>
    Discipline
</span>

                    <span>
                        <i class="bi bi-people"
                           aria-hidden="true"></i>
                        Safe Campus
                    </span>

                </div>
            </div>

        </section>

        {{-- =================================================
             KEY FEATURES
        ================================================== --}}
        <section
            class="lp-features"
            id="features"
            aria-labelledby="lp-features-title"
        >

            <div class="section-wrap">

                <div
                    class="lp-section-heading lp-heading-centered"
                    data-scroll-reveal
                >
                    <span class="lp-section-label">Key Features</span>

                    <h2 id="lp-features-title">
                        Built for Efficiency, Transparency, and Accountability
                    </h2>

                    <p>
                        PUPTracker integrates tools that help authorized
                        users organize violation management and support
                        a safer learning environment.
                    </p>
                </div>

                <div class="lp-feature-grid">

                    <article
                        class="lp-feature-item"
                        data-scroll-reveal="left"
                    >
                        <span>
                            <i class="bi bi-journal-text"
                               aria-hidden="true"></i>
                        </span>

                        <h3>Violation Tracking</h3>

                        <p>
                            Record, monitor, and manage student
                            violations in a centralized system.
                        </p>
                    </article>


                    <article
                        class="lp-feature-item"
                        data-scroll-reveal
                    >
                        <span>
                            <i class="bi bi-camera"
                               aria-hidden="true"></i>
                        </span>

                        <h3>Evidence Capture</h3>

                        <p>
                            Securely capture and store supporting
                            evidence for violation records.
                        </p>
                    </article>


                    <article
                        class="lp-feature-item"
                        data-scroll-reveal
                    >
                        <span>
                            <i class="bi bi-bar-chart-line"
                               aria-hidden="true"></i>
                        </span>

                        <h3>Reports and Analytics</h3>

                        <p>
                            Access available reports and summaries
                            to support informed decisions.
                        </p>
                    </article>


                    <article
                        class="lp-feature-item"
                        data-scroll-reveal="right"
                    >
                        <span>
                            <i class="bi bi-bell"
                               aria-hidden="true"></i>
                        </span>

                        <h3>Real-time Notifications</h3>

                        <p>
                            Stay informed about relevant announcements
                            and system notifications.
                        </p>
                    </article>

                </div>
            </div>
        </section>


        {{-- =================================================
             GUIDELINES AND RESOURCES
        ================================================== --}}
        <section
            class="lp-resources"
            id="guidelines"
            aria-labelledby="lp-resources-title"
        >

            <div class="section-wrap">

                <div
                    class="lp-section-heading lp-heading-centered"
                    data-scroll-reveal
                >
                    <span class="lp-section-label">Need Help?</span>

                    <h2 id="lp-resources-title">
                        Guidelines and Resources
                    </h2>

                    <p>
                        Access university information, privacy resources,
                        and support to help you understand the
                        disciplinary process.
                    </p>
                </div>

                <div class="lp-resource-grid">

                    {{-- VIOLATION GUIDELINES --}}
                    <a
                        class="lp-resource-card"
                        href="https://drive.google.com/file/d/1S9eq0I75D3YW7SCw9zd_Ur0WQBfJIHrS/view?usp=sharing"
                        target="_blank"
                        rel="noopener noreferrer"
                        data-scroll-reveal="left"
                    >
                        <span class="lp-resource-icon">
                            <i class="bi bi-book"
                               aria-hidden="true"></i>
                        </span>

                        <span class="lp-resource-copy">
                            <strong>Violation Guidelines</strong>
                            <small>
                                Consult official university resources
                                for applicable policies.
                            </small>
                        </span>

                        <i
                            class="bi bi-arrow-up-right lp-resource-arrow"
                            aria-hidden="true"
                        ></i>
                    </a>


                    {{-- PUP WEBSITE --}}
                    <a
                        class="lp-resource-card"
                        href="https://www.pup.edu.ph/"
                        target="_blank"
                        rel="noopener noreferrer"
                        data-scroll-reveal
                    >
                        <span class="lp-resource-icon">
                            <i class="bi bi-file-earmark-text"
                               aria-hidden="true"></i>
                        </span>

                        <span class="lp-resource-copy">
                            <strong>PUP Official Website</strong>
                            <small>
                                Visit the university's official website.
                            </small>
                        </span>

                        <i
                            class="bi bi-arrow-up-right lp-resource-arrow"
                            aria-hidden="true"
                        ></i>
                    </a>


                    {{-- PRIVACY STATEMENT --}}
                    <a
                        class="lp-resource-card"
                        href="https://www.pup.edu.ph/privacy/"
                        target="_blank"
                        rel="noopener noreferrer"
                        data-scroll-reveal
                    >
                        <span class="lp-resource-icon">
                            <i class="bi bi-shield-lock"
                               aria-hidden="true"></i>
                        </span>

                        <span class="lp-resource-copy">
                            <strong>Privacy Statement</strong>
                            <small>
                                Read the university's privacy information.
                            </small>
                        </span>

                        <i
                            class="bi bi-arrow-up-right lp-resource-arrow"
                            aria-hidden="true"
                        ></i>
                    </a>


                    {{-- CONTACT AND SUPPORT --}}
                    <a
                        class="lp-resource-card"
                        href="mailto:contact.puptrackervs@gmail.com"
                        data-scroll-reveal="right"
                    >
                        <span class="lp-resource-icon">
                            <i class="bi bi-question-circle"
                               aria-hidden="true"></i>
                        </span>

                        <span class="lp-resource-copy">
                            <strong>Contact and Support</strong>
                            <small>
                                Contact the PUPTracker team by email.
                            </small>
                        </span>

                        <i
                            class="bi bi-arrow-up-right lp-resource-arrow"
                            aria-hidden="true"
                        ></i>
                    </a>

                </div>
            </div>
        </section>


        {{-- =================================================
             FINAL CALL TO ACTION
        ================================================== --}}
        <section
            class="lp-final-cta"
            aria-labelledby="lp-final-cta-title"
            data-scroll-reveal
        >

            <div>
                <span class="lp-section-label">
                    PUPTracker Violation System
                </span>

                <h2 id="lp-final-cta-title">
                    Together for a Safer, More Disciplined PUP.
                </h2>
            </div>

            <a
                href="{{ route('login') }}"
                class="lp-button lp-button-light"
            >
                Login to System
                <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </a>

        </section>

    </main>


    {{-- =====================================================
         FOOTER
    ====================================================== --}}
    <footer class="lp-footer" id="contact">

        <div class="lp-footer-main">

            {{-- BRAND INFORMATION --}}
            <div class="lp-footer-brand-block">

                <a
                    href="{{ route('home') }}"
                    class="lp-footer-brand"
                    aria-label="PUPTracker home"
                >

                    <img
                        src="{{ asset('assets/images/SystemLogo_w/name.png') }}"
                        alt="PUPTracker logo"
                        loading="lazy"
                    >

                    <span class="lp-brand-text">
                        <strong>PUPTracker</strong>
                        <small>Violation System</small>
                    </span>

                </a>

                <p>
                    Supporting an organized, accountable, and safer
                    campus community.
                </p>

                <small>
                    Polytechnic University of the Philippines<br>
                    Taguig Campus
                </small>

            </div>


            {{-- QUICK LINKS --}}
            <div class="lp-footer-column">

                <h3>Quick Links</h3>

                <a href="#top">Home</a>
                <a href="#about">About</a>
                <a href="#modules">Modules</a>
                <a href="#features">Features</a>
                <a href="#guidelines">Guidelines</a>

            </div>


            {{-- OTHER RESOURCES --}}
            <div class="lp-footer-column">

                <h3>Other Resources</h3>

                <a
                    href="https://www.pup.edu.ph/"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    PUP Official Website
                </a>

                <a
                    href="https://www.pup.edu.ph/privacy/"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Privacy Statement
                </a>

                <a href="{{ route('login') }}">
                    System Login
                </a>

            </div>


            {{-- CONTACT --}}
            <div class="lp-footer-column">

                <h3>Connect With Us</h3>

                <a href="mailto:contact.puptrackervs@gmail.com">
                    <i class="bi bi-envelope" aria-hidden="true"></i>
                    contact.puptrackervs@gmail.com
                </a>

                <span>
                    <i class="bi bi-geo-alt" aria-hidden="true"></i>
                    PUP Taguig Campus, Taguig City
                </span>

            </div>

        </div>


        {{-- FOOTER COPYRIGHT --}}
        <div class="lp-footer-bottom">

            <span>
                &copy; {{ date('Y') }} PUPTracker. All rights reserved.
            </span>

            <span>
                A safer, more accountable PUP for everyone.
            </span>

        </div>

    </footer>

</div>
@endsection


{{-- =========================================================
     LANDING PAGE JAVASCRIPT
========================================================== --}}
@push('scripts')
<script src="{{ asset('js/landing-page.js') }}" defer></script>
@endpush
