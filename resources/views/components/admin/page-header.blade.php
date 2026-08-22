{{-- 
    PUPTracker Admin — Standard Page Header
    
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
--}}
@props([
    'title' => '',
    'description' => '',
    'icon' => null,
    'level' => 'h2',
    'breadcrumbs' => [],
])

<div {{ $attributes->merge(['class' => 'page-header-modern d-flex justify-content-between align-items-center flex-wrap gap-3']) }}>
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
</div>
