{{-- 
    PUPTracker Admin — Standard Status Badge
    
    Usage:
    <x-admin.badge status="pending" />
    <x-admin.badge status="completed" label="Completed" />
    <x-admin.badge status="rejected" />

    Map (business-neutral presentation only — DB values are unchanged):
    pending      → gold (warning)
    completed    → green (success)
    approved     → green
    confirmed    → green
    active       → green
    generated    → green
    success      → green
    rejected     → red
    failed       → red
    cancelled    → gray
    inactive     → gray
    read         → gray
    unread       → burgundy
    warning      → gold
    error        → red
    info         → blue
--}}
@props([
    'status' => '',
    'label' => null,
    'icon' => null,
])

@php
    $normalized = strtolower(trim((string) $status));

    $map = [
        'pending' => ['gold', 'bi-hourglass-split'],
        'in progress' => ['blue', 'bi-play-circle'],
        'overdue' => ['red', 'bi-exclamation-triangle'],
        'completed' => ['green', 'bi-check-circle'],
        'approved' => ['green', 'bi-check-circle'],
        'confirmed' => ['green', 'bi-check-circle'],
        'active' => ['green', 'bi-check-circle'],
        'generated' => ['green', 'bi-check-circle'],
        'success' => ['green', 'bi-check-circle'],
        'resolved' => ['green', 'bi-check-circle'],
        'rejected' => ['red', 'bi-x-circle'],
        'failed' => ['red', 'bi-x-circle'],
        'cancelled' => ['gray', 'bi-slash-circle'],
        'canceled' => ['gray', 'bi-slash-circle'],
        'inactive' => ['gray', 'bi-slash-circle'],
        'disabled' => ['gray', 'bi-slash-circle'],
        'read' => ['gray', 'bi-check2'],
        'unread' => ['burgundy', 'bi-envelope'],
        'warning' => ['gold', 'bi-exclamation-triangle'],
        'error' => ['red', 'bi-x-octagon'],
        'info' => ['blue', 'bi-info-circle'],
        'ai_failed' => ['red', 'bi-x-circle'],
        'outstanding' => ['red', 'bi-exclamation-diamond'],
        'manual review' => ['gold', 'bi-person-exclamation'],
    ];

    [$tone, $defaultIcon] = $map[$normalized] ?? ['gray', 'bi-tag'];
    $displayLabel = $label ?? ucwords(str_replace('_', ' ', (string) $status));
    $iconClass = $icon ?? $defaultIcon;
@endphp

<span {{ $attributes->merge(['class' => "admin-badge admin-badge-{$tone}"]) }}>
    @if($iconClass)
        <i class="bi {{ $iconClass }}" aria-hidden="true"></i>
    @endif
    <span class="admin-badge-text">{{ $displayLabel }}</span>
</span>
