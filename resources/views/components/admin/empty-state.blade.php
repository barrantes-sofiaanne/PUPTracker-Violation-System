{{-- 
    PUPTracker Admin — Standard Empty State
    
    Usage:
    <x-admin.empty-state 
        icon="bi-clipboard-x" 
        title="No records found" 
        message="There are no records matching your current filters."
        clearUrl="{{ route('admin.violations.index') }}"
        clearLabel="Clear Filters" />

    Attributes:
    - icon: Bootstrap icon class (default bi-inbox)
    - title: heading text
    - message: supporting text
    - clearUrl: optional URL for the "Clear Filters" action
    - clearLabel: button label (default "Clear Filters")
    - colSpan: optional table colspan when used inside a table row
--}}
@props([
    'icon' => 'bi-inbox',
    'title' => 'No records found',
    'message' => null,
    'clearUrl' => null,
    'clearLabel' => 'Clear Filters',
    'colspan' => null,
])

@if($colspan)
    <tr>
        <td colspan="{{ $colspan }}" class="text-center">
            <div class="admin-empty-state">
                <div class="admin-empty-icon" aria-hidden="true">
                    <i class="bi {{ $icon }}"></i>
                </div>
                <h5 class="admin-empty-title">{{ $title }}</h5>
                @if($message)
                    <p class="admin-empty-message">{{ $message }}</p>
                @endif
                @if($clearUrl)
                    <a href="{{ $clearUrl }}" class="admin-btn admin-btn-neutral admin-btn-sm">
                        <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>
                        {{ $clearLabel }}
                    </a>
                @endif
            </div>
        </td>
    </tr>
@else
    <div {{ $attributes->merge(['class' => 'admin-empty-state']) }}>
        <div class="admin-empty-icon" aria-hidden="true">
            <i class="bi {{ $icon }}"></i>
        </div>
        <h5 class="admin-empty-title">{{ $title }}</h5>
        @if($message)
            <p class="admin-empty-message">{{ $message }}</p>
        @endif
        @if($clearUrl)
            <a href="{{ $clearUrl }}" class="admin-btn admin-btn-neutral admin-btn-sm">
                <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>
                {{ $clearLabel }}
            </a>
        @endif
    </div>
@endif
