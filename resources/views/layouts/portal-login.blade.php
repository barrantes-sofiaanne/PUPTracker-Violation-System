{{--
    Shared full-screen login shell for every portal.

    Child views set the module with:
        @extends('layouts.portal-login', ['portal' => 'student|security|oss|admin'])
    and fill the right-hand panel with:
        @section('portal-form') ... @endsection

    Each module only changes its colours (left-panel gradient, tint, icon,
    buttons) and its copy; the structure is identical.
--}}
@extends('layouts.app')

@php
    $portal = $portal ?? 'student';

    $portals = [
        'student' => [
            'name' => 'Student Portal',
            'top'  => 'Student',
            'desc' => 'Access your records, announcements, and violation history.',
            'icon' => 'bi-mortarboard-fill',
        ],
        'security' => [
            'name' => 'Security Portal',
            'top'  => 'Security',
            'desc' => 'Record violations, manage evidence, and maintain security reports.',
            'icon' => 'bi-shield-fill',
        ],
        'oss' => [
            'name' => 'OSS Staff Portal',
            'top'  => 'OSS Staff',
            'desc' => 'Review submissions, manage records, and process student concerns.',
            'icon' => 'bi-people-fill',
        ],
        'admin' => [
            'name' => 'Administrator Portal',
            'top'  => 'Administrator',
            'desc' => 'Manage users, system settings, and monitor activities.',
            'icon' => 'bi-gear-fill',
        ],
    ];

    $p = $portals[$portal] ?? $portals['student'];
    $isLongTitle = strlen($p['top']) > 9;
@endphp

@section('title', $p['name'])

@push('styles')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,500&display=swap"
          rel="stylesheet">
    <style>
        @include('partials.portal-login-styles')
    </style>
@endpush

@section('content')
<main class="spl-page spl-theme-{{ $portal }}">

    <div class="spl-card">

        {{-- ============ LEFT: BRAND PANEL (gradient follows the module colour) ============ --}}
        <aside class="spl-brand"
               style="--spl-photo: url('{{ asset('assets/images/PUP-TAGUIG/PUPT-picture.jpg') }}')"
               aria-label="PUPTracker Violation System">

            <a href="{{ route('home') }}" class="spl-logo" aria-label="PUPTracker home">
                <img src="{{ asset('assets/images/SystemLogo_w/name.png') }}"
                     alt="" width="44" height="40">
                <span class="spl-logo-text">
                    <strong>PUPTracker</strong>
                    <small>Violation System</small>
                </span>
            </a>

            <div class="spl-brand-body">
                <p class="spl-campus">
                    <span class="spl-dash" aria-hidden="true"></span>
                    Polytechnic University of the Philippines &middot; Taguig Campus
                </p>

                <h1 class="spl-title {{ $isLongTitle ? 'spl-title--long' : '' }}">
                    {{ $p['top'] }}
                    <span>Portal</span>
                </h1>

                <p class="spl-desc">{{ $p['desc'] }}</p>
            </div>

            <p class="spl-tagline">
                <span class="spl-dash" aria-hidden="true"></span>
                Para sa'yo, Para sa Bayan
            </p>
        </aside>

        {{-- ============ RIGHT: SIGN-IN PANEL ============ --}}
        <section class="spl-panel" aria-labelledby="spl-heading">

            {{-- Curved, gold-edged divider (vertical on desktop) --}}
            <svg class="spl-curve spl-curve--v" viewBox="0 0 60 100" preserveAspectRatio="none" aria-hidden="true">
                <path class="spl-curve-fill"
                      d="M 60 0 C 30 12, 14 40, 20 62 C 26 82, 12 94, 0 100 L 60 100 Z"/>
                <path class="spl-curve-line"
                      d="M 60 0 C 30 12, 14 40, 20 62 C 26 82, 12 94, 0 100"
                      vector-effect="non-scaling-stroke"/>
            </svg>

            {{-- Same divider as a horizontal wave (tablet / phone) --}}
            <svg class="spl-curve spl-curve--h" viewBox="0 0 100 60" preserveAspectRatio="none" aria-hidden="true">
                <path class="spl-curve-fill"
                      d="M 0 38 C 22 8, 44 6, 62 30 S 90 48, 100 20 L 100 60 L 0 60 Z"/>
                <path class="spl-curve-line"
                      d="M 0 38 C 22 8, 44 6, 62 30 S 90 48, 100 20"
                      vector-effect="non-scaling-stroke"/>
            </svg>

            <div class="spl-panel-inner">

                <span class="spl-icon" aria-hidden="true">
                    <i class="bi {{ $p['icon'] }}"></i>
                </span>

                <h2 id="spl-heading" class="spl-heading">{{ $p['name'] }}</h2>
                <p class="spl-subtitle">Sign in to your account.</p>

                @yield('portal-form')

                <a href="{{ route('login') }}" class="spl-back">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                    <span>Back to portal selection</span>
                </a>

            </div>
        </section>

    </div>
</main>
@endsection

@push('scripts')
<script>
(function () {
    // Show / hide password
    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('#togglePassword');
        if (!toggle) { return; }

        var field = document.getElementById('password');
        if (!field) { return; }

        var reveal = field.type === 'password';
        field.type = reveal ? 'text' : 'password';
        toggle.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');

        var icon = toggle.querySelector('i');
        if (icon) { icon.className = reveal ? 'bi bi-eye-slash' : 'bi bi-eye'; }
    });

    // Busy state + double-submit guard
    document.querySelectorAll('.spl-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var button = form.querySelector('button[type="submit"]');
            if (!button) { return; }

            if (button.classList.contains('is-busy')) {
                event.preventDefault();
                return;
            }

            button.classList.add('is-busy');
            button.setAttribute('aria-busy', 'true');
        });
    });

    // Restore buttons if the page comes back from the back/forward cache
    window.addEventListener('pageshow', function (event) {
        if (!event.persisted) { return; }
        document.querySelectorAll('.spl-btn.is-busy').forEach(function (button) {
            button.classList.remove('is-busy');
            button.removeAttribute('aria-busy');
        });
    });

    // Focus the first field on desktop only (avoids popping the phone keyboard)
    if (window.matchMedia && window.matchMedia('(pointer: fine)').matches) {
        var first = document.querySelector('.spl-form input');
        if (first) { first.focus({ preventScroll: true }); }
    }
})();
</script>
@endpush
