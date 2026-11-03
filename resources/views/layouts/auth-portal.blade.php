{{--
    Shared shell for the authentication screens.

    New-style pages (portal selection + Student / Security / OSS Staff /
    Administrator login) fill   @section('ap-content')
    Legacy pages (forgot / reset password, MFA, IDP registration) still fill
    @section('auth-content') and keep their own stylesheet and scripts.

    The portal key ($portal) selects the colour theme and the brand-panel copy:
        access | student | security | oss | admin
    Optional data from the page:
        'brandKey' => 'reset' | 'signup'   (brand-panel wording only; the
                                             colour theme still follows $portal)
        'wide'     => true                 (roomier right panel, e.g. signup)
--}}
@extends('layouts.app')

@php
    $portal = $portal ?? 'access';
    $brandKey = $brandKey ?? $portal;
    $wide = (bool) ($wide ?? false);

    // Legacy pages keep using the "auth-content" section.
    $isLegacyPage = $__env->hasSection('auth-content');

    $themes = ['access', 'student', 'security', 'oss', 'admin'];
    $themeKey = in_array($portal, $themes, true) ? $portal : 'student';

    $brandCopy = [
        'access'   => [
            'lines' => ['Choose Your', 'Portal'],
            'desc'  => 'Select the module you will log in to and continue to your designated portal.',
        ],
        'student'  => [
            'lines' => ['Student', 'Portal'],
            'desc'  => 'Access your records, announcements, and violation history.',
        ],
        'security' => [
            'lines' => ['Security', 'Portal'],
            'desc'  => 'Record violations, manage evidence, and maintain security reports.',
        ],
        'oss'      => [
            'lines' => ['OSS Staff', 'Portal'],
            'desc'  => 'Review submissions, manage records, and process student concerns.',
        ],
        'admin'    => [
            'lines' => ['Administrator', 'Portal'],
            'desc'  => 'Manage users, system settings, and monitor activities.',
        ],
        'reset'    => [
            'lines' => ['Reset Your', 'Password'],
            'desc'  => 'Verify your account and set a new password to regain secure access.',
        ],
        'signup'   => [
            'lines' => ['Create Your', 'Account'],
            'desc'  => 'Complete your student information to finish setting up PUPTracker.',
        ],
    ];

    $genericCopy = [
        'lines' => ['A Safer,', 'More Accountable', 'PUP.'],
        'desc'  => 'PUPTracker promotes accountability, transparency, and a disciplined campus community through an organized violation tracking system.',
    ];

    $brand = $isLegacyPage
        ? $genericCopy
        : ($brandCopy[$brandKey] ?? $brandCopy[$portal] ?? $genericCopy);
    $isLongTitle = strlen($brand['lines'][0]) > 9;
@endphp

@section('title', $title ?? 'PUPTracker Violation System')

@push('styles')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,500&display=swap"
          rel="stylesheet">
    @if($isLegacyPage)
        {{-- Form / alert / registration styles used by the legacy pages only. --}}
        <link rel="stylesheet" href="{{ asset('css/student-login.css') }}">
    @endif
    <link rel="stylesheet" href="{{ asset('css/auth-portal-redesign.css') }}">
@endpush

@section('content')
<main class="ap-page ap-theme-{{ $themeKey }}"
      data-auth-spa-root>

    <div class="ap-shell" data-auth-shell>

        {{-- ================= LEFT: BRAND PANEL ================= --}}
        <aside class="ap-brand"
               style="--ap-photo: url('{{ asset('assets/images/PUP-TAGUIG/PUPT-picture.jpg') }}')">
            <div class="ap-brand-inner">

                <a href="{{ route('home') }}" class="ap-logo" aria-label="PUPTracker home">
                    <img src="{{ asset('assets/images/SystemLogo_w/name.png') }}"
                         alt="" width="48" height="44">
                    <span class="ap-logo-text">
                        <strong>PUPTracker</strong>
                        <small>Violation System</small>
                    </span>
                </a>

                <div class="ap-brand-body">
                    <p class="ap-campus">
                        <span class="ap-dash" aria-hidden="true"></span>
                        <span>Polytechnic University of the Philippines &middot; Taguig Campus</span>
                    </p>

                    <h1 class="ap-title{{ $isLongTitle ? ' ap-title--long' : '' }}">
                        @foreach($brand['lines'] as $line)
                            <span>{{ $line }}</span>
                        @endforeach
                    </h1>

                    <p class="ap-desc">{{ $brand['desc'] }}</p>
                </div>

                <p class="ap-motto">
                    <span class="ap-dash" aria-hidden="true"></span>
                    <span>Mula sa'yo,<br>Para sa Bayan</span>
                </p>

            </div>
        </aside>

        {{-- ================= RIGHT: CONTENT PANEL ================= --}}
        <section class="ap-panel">

            {{-- The single curved divider: cream mask + gold line + white highlight --}}
            <svg class="ap-divider" viewBox="0 0 70 100" preserveAspectRatio="none" aria-hidden="true">
                <defs>
                    <linearGradient id="apPanelGradient" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0" class="ap-grad-top"/>
                        <stop offset="1" class="ap-grad-bottom"/>
                    </linearGradient>
                </defs>
                <path class="ap-divider-fill"
                      d="M 62 0 C 40 10 6 30 10 56 C 14 80 38 94 54 100 L 70 100 L 70 0 Z"/>
                <path class="ap-divider-light"
                      d="M 66 0 C 44 10 10 30 14 56 C 18 80 42 94 58 100"
                      vector-effect="non-scaling-stroke"/>
                <path class="ap-divider-gold"
                      d="M 62 0 C 40 10 6 30 10 56 C 14 80 38 94 54 100"
                      vector-effect="non-scaling-stroke"/>
            </svg>

            <div class="ap-panel-inner{{ $isLegacyPage ? ' ap-panel-inner--legacy' : '' }}{{ $wide ? ' ap-panel-inner--wide' : '' }}">
                @if($isLegacyPage)
                    <div class="auth-content-card">
                        @yield('auth-content')
                    </div>
                @else
                    @yield('ap-content')
                @endif
            </div>

        </section>

    </div>
</main>
@endsection

@push('scripts')
    @if($isLegacyPage)
        <script src="{{ asset('js/student-login.js') }}"></script>
        <script src="{{ asset('js/password-reset.js') }}"></script>
        <script src="{{ asset('js/portal-transitions.js') }}"></script>
    @endif
    <script src="{{ asset('js/auth-portal.js') }}" defer></script>
@endpush
