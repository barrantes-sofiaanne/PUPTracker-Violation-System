{{-- 
    PUPTracker Admin — Standard Modal
    
    Usage:
    <x-admin.modal id="exampleModal" title="Record Student Violation" size="xl">
        <x-slot name="subtitle">Create a new violation record for a student.</x-slot>
        ... body content ...
        <x-slot name="footer">
            <x-admin.button variant="neutral" data-bs-dismiss="modal">Cancel</x-admin.button>
            <x-admin.button type="submit" form="myForm">Save</x-admin.button>
        </x-slot>
    </x-admin.modal>

    Attributes:
    - id: modal id (required)
    - title: modal title (required)
    - subtitle: optional helper text under the title
    - icon: optional icon in header
    - size: sm / default / lg / xl
    - scrollable: boolean for modal-dialog-scrollable
--}}
@props([
    'id' => null,
    'title' => '',
    'subtitle' => null,
    'icon' => null,
    'size' => null,
    'scrollable' => false,
])

@php
    $sizeClass = match ($size) {
        'sm' => 'modal-sm',
        'lg' => 'modal-lg',
        'xl' => 'modal-xl',
        default => '',
    };
@endphp

<div class="modal fade admin-modal" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Label" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="modal-dialog {{ $sizeClass }} {{ $scrollable ? 'modal-dialog-scrollable' : '' }}">
        <div class="modal-content admin-modal-content">
            <div class="modal-header admin-modal-header">
                <div>
                    <h4 class="modal-title admin-modal-title" id="{{ $id }}Label">
                        @if($icon)
                            <i class="bi {{ $icon }} me-2" aria-hidden="true"></i>
                        @endif
                        {{ $title }}
                    </h4>
                    @if($subtitle)
                        <small class="admin-modal-subtitle d-block mt-1">{{ $subtitle }}</small>
                    @endif
                </div>
                <button type="button" class="btn-close admin-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body admin-modal-body">
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="modal-footer admin-modal-footer">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
