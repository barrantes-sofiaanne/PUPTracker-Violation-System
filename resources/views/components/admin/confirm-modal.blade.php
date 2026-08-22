{{-- 
    PUPTracker Admin — Standard Confirmation Modal
    
    Usage (destructive):
    <x-admin.confirm-modal id="deleteViolationModal" title="Delete Violation?" message="This action cannot be undone." confirmLabel="Delete" :danger="true">
        <x-slot name="form">... optional form to submit on confirm ...</x-slot>
    </x-admin.confirm-modal>

    Usage (non-destructive):
    <x-admin.confirm-modal id="markReadModal" title="Mark Notification as Read?" message="..." confirmLabel="Confirm" />

    The confirm button triggers a form submission (if a form is provided in the "form" slot,
    it must have id="confirm-form-{id}") or a custom JS event "puptracker:confirm-{id}".
--}}
@props([
    'id' => null,
    'title' => '',
    'message' => null,
    'confirmLabel' => 'Confirm',
    'cancelLabel' => 'Cancel',
    'danger' => false,
    'icon' => null,
])

@php
    $defaultIcon = $danger ? 'bi-trash3' : 'bi-question-circle';
    $iconClass = $icon ?? $defaultIcon;
@endphp

<div class="modal fade admin-modal admin-confirm-modal" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Label" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content admin-modal-content text-center">
            <div class="admin-confirm-icon {{ $danger ? 'danger' : 'info' }}" aria-hidden="true">
                <i class="bi {{ $iconClass }}"></i>
            </div>

            <h4 class="admin-confirm-title mt-3" id="{{ $id }}Label">{{ $title }}</h4>

            @if($message)
                <p class="admin-confirm-message mb-0">{{ $message }}</p>
            @endif

            <div class="d-flex gap-2 justify-content-center mt-4">
                <button type="button" class="admin-btn admin-btn-neutral" data-bs-dismiss="modal">
                    {{ $cancelLabel }}
                </button>
                <button type="button" class="admin-btn {{ $danger ? 'admin-btn-danger' : 'admin-btn-primary' }} admin-confirm-action"
                        data-target="{{ $id }}">
                    {{ $confirmLabel }}
                </button>
            </div>

            {{ $slot }}
        </div>
    </div>
</div>
