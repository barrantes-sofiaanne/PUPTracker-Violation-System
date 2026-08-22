{{-- 
    PUPTracker Admin — Standard Breadcrumb

    Usage:
    <x-admin.breadcrumb :items="[
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Violations'],
    ]" />
--}}
@props([
    'items' => [],
])

<nav aria-label="breadcrumb" class="admin-breadcrumb">
    <ol class="breadcrumb mb-0">
        @foreach($items as $index => $item)
            @if(($item['url'] ?? null) && $index < count($items) - 1)
                <li class="breadcrumb-item">
                    <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                </li>
            @else
                <li class="breadcrumb-item active" aria-current="page">{{ $item['label'] }}</li>
            @endif
        @endforeach
    </ol>
</nav>
