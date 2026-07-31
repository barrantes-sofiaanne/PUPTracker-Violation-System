@extends('layouts.student')

@section('title', 'Notifications')

@push('styles')
@endpush

@section('content')

<div class="container-fluid py-1">

            {{-- Header --}}
            <div class="portal-hero mb-4">

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

                    <div>

                        <h2 class="fw-bold mb-1">

                            Notifications

                        </h2>

                        <p class="mb-0">

                            You have

                            <strong>

                                {{ $unreadCount }}

                            </strong>

                            unread notifications.

                        </p>

                    </div>

                </div>

            </div>

            <form action="{{ route('student.notifications.status') }}" method="POST" id="notificationsBulkForm">
                @csrf
                <input type="hidden" name="action" id="notificationActionInput" value="">

                <div class="card portal-card mb-4">
                    <div class="card-body d-flex flex-wrap align-items-center gap-2">
                        <div class="form-check me-2">
                            <input class="form-check-input" type="checkbox" id="selectAllNotifications">
                            <label class="form-check-label" for="selectAllNotifications">
                                Select All
                            </label>
                        </div>

                        <button type="button" class="btn btn-sm btn-outline-primary" id="markAllReadBtn">
                            Mark All Unread as Read
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success" id="markSelectedReadBtn">
                            Mark Selected as Read
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-warning" id="markSelectedUnreadBtn">
                            Mark Selected as Unread
                        </button>
                    </div>
                </div>

                @error('notification_ids')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror

                @forelse($notifications as $notification)
                    <div class="card portal-card mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                <div class="d-flex align-items-start gap-2">
                                    <div class="form-check mt-1">
                                        <input
                                            class="form-check-input notification-checkbox"
                                            type="checkbox"
                                            name="notification_ids[]"
                                            value="{{ $notification->notification_id }}"
                                            @checked(in_array($notification->notification_id, old('notification_ids', [])))>
                                    </div>

                                    <div>
                                        <div class="mb-2">
                                            @if(!$notification->is_read)
                                                <span class="portal-badge maroon">Unread</span>
                                            @else
                                                <span class="portal-badge muted">Read</span>
                                            @endif
                                        </div>

                                        <small class="text-muted">
                                            {{ $notification->created_at->format('M d, Y h:i A') }}
                                        </small>
                                    </div>
                                </div>

                                <div>
                                    @switch($notification->notification_type)
                                        @case('announcement')
                                            <i class="bi bi-megaphone-fill fs-2" style="color: var(--portal-goldenrod);"></i>
                                            @break
                                        @case('violation')
                                            <i class="bi bi-exclamation-triangle-fill fs-2" style="color: var(--portal-maroon);"></i>
                                            @break
                                        @case('sanction')
                                            <i class="bi bi-shield-check fs-2" style="color: var(--portal-goldenrod);"></i>
                                            @break
                                        @default
                                            <i class="bi bi-bell-fill fs-2" style="color: var(--portal-goldenrod);"></i>
                                    @endswitch
                                </div>
                            </div>

                            <hr class="border-secondary-subtle">

                            <p style="white-space: pre-line" class="mb-3 text-dark">
                                {{ \Illuminate\Support\Str::limit(strip_tags($notification->message), 180) }}
                            </p>

                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary notification-trigger"
                                data-message="{{ e($notification->message) }}"
                                data-created="{{ $notification->created_at->format('M d, Y h:i A') }}"
                                data-type="{{ $notification->notification_type }}"
                                data-link="{{ $notification->link ?: '' }}">
                                View Full Message
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="card portal-card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-bell-slash fs-1 text-muted"></i>
                            <h4 class="mt-3">
                                No notifications available.
                            </h4>
                        </div>
                    </div>
                @endforelse
            </form>

            @if($notifications->hasPages())
                <div class="mt-4">
                    {{ $notifications->links() }}
                </div>
            @endif

        </div>

@endsection

@push('scripts')
<div class="modal fade" id="notificationDetailModal" tabindex="-1" aria-labelledby="notificationDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="notificationDetailModalLabel">Notification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <small class="text-muted d-block mb-3" id="notificationModalDate"></small>
                <p class="mb-0" style="white-space: pre-line;" id="notificationModalMessage"></p>
            </div>
            <div class="modal-footer">
                <a href="#" class="btn btn-outline-primary d-none" id="notificationModalLink">Open Related Page</a>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('notificationDetailModal');
    if (!modalElement) {
        return;
    }

    const modal = new bootstrap.Modal(modalElement);
    const titleEl = document.getElementById('notificationDetailModalLabel');
    const dateEl = document.getElementById('notificationModalDate');
    const messageEl = document.getElementById('notificationModalMessage');
    const linkEl = document.getElementById('notificationModalLink');

    document.querySelectorAll('.notification-trigger').forEach(function (item) {
        item.addEventListener('click', function () {
            const type = item.dataset.type || 'notification';
            const readableType = type.charAt(0).toUpperCase() + type.slice(1);
            titleEl.textContent = readableType + ' Notification';
            dateEl.textContent = item.dataset.created || '-';
            messageEl.textContent = item.dataset.message || '';

            if (item.dataset.link) {
                linkEl.href = item.dataset.link;
                linkEl.classList.remove('d-none');
            } else {
                linkEl.href = '#';
                linkEl.classList.add('d-none');
            }

            modal.show();
        });
    });

    const selectAllCheckbox = document.getElementById('selectAllNotifications');
    const notificationCheckboxes = Array.from(document.querySelectorAll('.notification-checkbox'));
    const actionInput = document.getElementById('notificationActionInput');
    const form = document.getElementById('notificationsBulkForm');

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            notificationCheckboxes.forEach(function (checkbox) {
                checkbox.checked = selectAllCheckbox.checked;
            });
        });
    }

    notificationCheckboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            if (!selectAllCheckbox) {
                return;
            }

            const allChecked = notificationCheckboxes.length > 0 &&
                notificationCheckboxes.every(function (item) { return item.checked; });

            selectAllCheckbox.checked = allChecked;
        });
    });

    function submitBulkAction(action) {
        if (!form || !actionInput) {
            return;
        }

        actionInput.value = action;
        form.submit();
    }

    const markAllReadBtn = document.getElementById('markAllReadBtn');
    const markSelectedReadBtn = document.getElementById('markSelectedReadBtn');
    const markSelectedUnreadBtn = document.getElementById('markSelectedUnreadBtn');

    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function () {
            submitBulkAction('mark_all_read');
        });
    }

    if (markSelectedReadBtn) {
        markSelectedReadBtn.addEventListener('click', function () {
            submitBulkAction('mark_selected_read');
        });
    }

    if (markSelectedUnreadBtn) {
        markSelectedUnreadBtn.addEventListener('click', function () {
            submitBulkAction('mark_selected_unread');
        });
    }
});
</script>
@endpush