{{-- 
    PUPTracker Admin — Standard Loading Indicator

    Usage (block):
    <x-admin.loading id="violationsLoading" label="Loading violations..." />

    Usage (button, disables + shows spinner on the button):
    <x-admin.loading id="generateLoading" label="Generating AI review..." button="generateBtn" />
    Then in JS: window.PUPLoading.start('generateLoading'); / window.PUPLoading.stop('generateLoading');
--}}
@props([
    'id' => null,
    'label' => 'Loading...',
    'button' => null,
    'inline' => false,
])

@if($inline)
    <span {{ $attributes->merge(['id' => $id, 'class' => 'admin-loading-inline', 'role' => 'status', 'aria-live' => 'polite']) }}>
        <span class="spinner-border spinner-border-sm admin-loading-spinner" aria-hidden="true"></span>
        <span class="admin-loading-label">{{ $label }}</span>
    </span>
@else
    <div {{ $attributes->merge(['id' => $id, 'class' => 'admin-loading d-none', 'role' => 'status', 'aria-live' => 'polite', 'data-button' => $button]) }}>
        <div class="admin-loading-card">
            <span class="spinner-border admin-loading-spinner" aria-hidden="true"></span>
            <div class="admin-loading-text">
                <strong class="admin-loading-label">{{ $label }}</strong>
            </div>
        </div>
    </div>
@endif
