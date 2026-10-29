@php
    $activePortal = $portal ?? 'student';

    $portalLinks = [
        [
            'key' => 'student',
            'label' => 'Student',
            'route' => 'student.login',
            'icon' => 'bi-mortarboard',
        ],
        [
            'key' => 'security',
            'label' => 'Security',
            'route' => 'security.login',
            'icon' => 'bi-shield-check',
        ],
        [
            'key' => 'oss',
            'label' => 'OSS Staff',
            'route' => 'security.login',
            'icon' => 'bi-people',
        ],
        [
            'key' => 'admin',
            'label' => 'Administrator',
            'route' => 'admin.login',
            'icon' => 'bi-gear',
        ],
    ];
@endphp

<header class="reference-login-header">
    <img
        class="reference-login-logo"
        src="{{ asset('assets/images/System-logo.png') }}"
        alt="PUPTracker logo">

    <h2>Sign in to PUPTracker</h2>

    <p>Choose your portal and enter your credentials.</p>
</header>

<nav class="reference-portal-tabs" aria-label="Choose login portal">
    @foreach ($portalLinks as $item)
        @php
            $url = $item['key'] === 'oss'
                ? route($item['route'], ['portal' => 'oss'])
                : route($item['route']);

            $isActive = $activePortal === $item['key'];
        @endphp

        <a
            href="{{ $url }}"
            class="reference-portal-tab {{ $isActive ? 'is-active' : '' }}"
            @if ($isActive) aria-current="page" @endif>

            <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>
