{{-- 
    PUPTracker Admin — Standard Button
    
    Usage:
    <x-admin.button type="submit">Save</x-admin.button>
    <x-admin.button variant="secondary" icon="bi-stars">Generate AI Review</x-admin.button>
    <x-admin.button variant="danger" href="{{ route(...) }}">Delete</x-admin.button>
    <x-admin.button variant="view" href="...">View</x-admin.button>
    <x-admin.button variant="neutral" data-bs-dismiss="modal">Cancel</x-admin.button>

    Variants:
    - primary   : burgundy (create/save/submit/generate/record/publish)
    - secondary : gold accent (review / special admin actions)
    - neutral   : white/gray outline (cancel/back/reset/close)
    - danger    : red (delete/reject/remove)
    - view      : consistent icon + label
    - link      : subtle text link
--}}
@props([
    'variant' => 'primary',
    'icon' => null,
    'href' => null,
    'size' => 'md',
    'type' => 'button',
])

@php
    $variantClass = match ($variant) {
        'secondary' => 'admin-btn-secondary',
        'neutral' => 'admin-btn-neutral',
        'danger' => 'admin-btn-danger',
        'view' => 'admin-btn-view',
        'link' => 'admin-btn-link',
        default => 'admin-btn-primary',
    };

    $sizeClass = match ($size) {
        'sm' => 'admin-btn-sm',
        'lg' => 'admin-btn-lg',
        default => '',
    };

    $classes = "admin-btn {$variantClass} {$sizeClass}";
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<i class="{{ $icon }} me-1" aria-hidden="true"></i>@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<i class="{{ $icon }} me-1" aria-hidden="true"></i>@endif
        {{ $slot }}
    </button>
@endif
