{{-- 
    PUPTracker Admin — Standard Page Header

    Matches the SECURITY module's module header (the source of truth): the same
    maroon→gold hero gradient, radius, spacing and typography, plus the same
    Philippine Standard Time clock widget.

    Usage:
    <x-admin.page-header title="Violation Reports" description="Generate, analyze, and export disciplinary reports.">
        <x-slot name="actions">
            <x-admin.button icon="bi-plus-lg" href="...">New Report</x-admin.button>
        </x-slot>
    </x-admin.page-header>

    Attributes:
    - title: page title (required)
    - description: one-line explanation under the title (optional)
    - icon: optional Bootstrap icon class rendered beside the title
    - level: heading level (h1/h2/h3), default h2
    - breadcrumbs: optional array of ['label' => ..., 'url' => ...]
    - hero: render the full Security-style hero (title + optional PHT clock), default true
    - time: show the PHT clock in the header. OPT-IN — default false, so the clock
            is only rendered where a page explicitly asks for it.
--}}
@props([
    'title' => '',
    'description' => '',
    'icon' => null,
    'level' => 'h2',
    'breadcrumbs' => [],
    'hero' => true,
    'time' => false,
])

@php
    // The clock is OPT-IN: only pages that genuinely need it (the Dashboards)
    // pass :time="true". Every other module header keeps the Security-style hero
    // WITHOUT a clock.
    //
    // The clock markup + behaviour is shared: this one widget is what the
    // Security module renders, so the JavaScript below matches its formatting
    // (en-PH, Asia/Manila, 24-hour, suffixed " PHT") exactly.
    $showTime = $hero && $time;
@endphp

<div {{ $attributes->merge([
    'class' => 'page-header-modern d-flex justify-content-between align-items-center flex-wrap gap-3'
        . ($hero ? ' portal-hero' : ''),
]) }}>
    @if($hero)
        <div class="dashboard-hero-grid w-100">
            <div>
                @if(!empty($breadcrumbs))
                    <nav aria-label="breadcrumb" class="admin-breadcrumb mb-1">
                        <ol class="breadcrumb mb-0">
                            @foreach($breadcrumbs as $crumb)
                                @if(!empty($crumb['url']))
                                    <li class="breadcrumb-item"><a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a></li>
                                @else
                                    <li class="breadcrumb-item active" aria-current="page">{{ $crumb['label'] }}</li>
                                @endif
                            @endforeach
                        </ol>
                    </nav>
                @endif

                <{{ $level }} class="fw-bold dashboard-hero-title admin-page-title">
                    @if($icon)
                        <i class="{{ $icon }} me-2"></i>
                    @endif
                    {{ $title }}
                </{{ $level }}>

                @if($description)
                    <p class="dashboard-hero-subtitle admin-page-desc">{{ $description }}</p>
                @endif
            </div>

            @if($showTime)
                <div class="dashboard-time-wrap">
                    <div class="dashboard-time-label">Philippine Standard Time</div>
                    <div id="phTimeDisplay" class="dashboard-time-value">
                        <i class="bi bi-clock-history"></i>
                        <span>Loading...</span>
                    </div>
                </div>
            @else
                @isset($actions)
                    <div class="d-flex flex-wrap align-items-center gap-2 admin-page-actions">
                        {{ $actions }}
                    </div>
                @endisset
            @endif
        </div>
    @else
        <div class="admin-page-heading">
            @if(!empty($breadcrumbs))
                <nav aria-label="breadcrumb" class="admin-breadcrumb mb-1">
                    <ol class="breadcrumb mb-0">
                        @foreach($breadcrumbs as $crumb)
                            @if(!empty($crumb['url']))
                                <li class="breadcrumb-item"><a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a></li>
                            @else
                                <li class="breadcrumb-item active" aria-current="page">{{ $crumb['label'] }}</li>
                            @endif
                        @endforeach
                    </ol>
                </nav>
            @endif
            <{{ $level }} class="fw-bold mb-1 admin-page-title">
                @if($icon)
                    <i class="{{ $icon }} me-2"></i>
                @endif
                {{ $title }}
            </{{ $level }}>
            @if($description)
                <p class="mb-0 admin-page-desc">{{ $description }}</p>
            @endif
        </div>

        @isset($actions)
            <div class="d-flex flex-wrap align-items-center gap-2 admin-page-actions">
                {{ $actions }}
            </div>
        @endisset
    @endif
</div>

@if($hero)
    @isset($actions)
        @if($showTime)
            {{-- Actions stay below the hero when the clock occupies the right slot,
                 so a long action row never collides with the time widget. --}}
            <div class="d-flex flex-wrap align-items-center gap-2 admin-page-actions mt-2 mb-3">
                {{ $actions }}
            </div>
        @endif
    @endisset

    @once
        {{-- ONE clock implementation for every Admin/Super Admin page header.
             Same locale, timezone, format and suffix as Security's. --}}
        @push('scripts')
        <script>
        (function () {
            var el = document.getElementById('phTimeDisplay');
            if (!el) { return; }

            function update() {
                var formatted = new Intl.DateTimeFormat('en-PH', {
                    timeZone: 'Asia/Manila',
                    month: 'short',
                    day: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false,
                }).format(new Date());

                var span = el.querySelector('span');
                if (span) { span.textContent = formatted + ' PHT'; }
            }

            update();
            setInterval(update, 1000);
        })();
        </script>
        @endpush
    @endonce
@endif
