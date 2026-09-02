@extends('layouts.student')

@section('title', 'Notifications')

@push('styles')
@endpush

@section('content')

<div class="container-fluid py-1">

    {{-- Header (same design as the Admin module) --}}
    <div class="page-header-modern d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">Notifications</h2>
            <p class="text-muted mb-0">
                You have <strong>{{ $unreadCount }}</strong> unread notifications.
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('student.dashboard') }}" class="admin-btn admin-btn-neutral">
                <i class="bi bi-speedometer2 me-1"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <form action="{{ route('student.notifications.status') }}" method="POST" id="notificationsBulkForm">
        @csrf
        <input type="hidden" name="action" id="notificationActionInput" value="">

        {{-- Result count --}}
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <span class="text-muted small">
                Showing {{ $notifications->firstItem() ?? 0 }}–{{ $notifications->lastItem() ?? 0 }} of {{ $notifications->total() }} notifications
            </span>
        </div>

        {{-- Bulk actions (admin-style action bar) --}}
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body d-flex flex-wrap align-items-center gap-2 py-2">
                <div class="form-check me-2 mb-0">
                    <input class="form-check-input" type="checkbox" id="selectAllNotifications">
                    <label class="form-check-label" for="selectAllNotifications">
                        Select All
                    </label>
                </div>

                <button type="button" class="admin-btn admin-btn-view admin-btn-sm" id="markAllReadBtn">
                    <i class="bi bi-check2-all me-1"></i>Mark All Unread as Read
                </button>
                <button type="button" class="admin-btn admin-btn-success admin-btn-sm" id="markSelectedReadBtn">
                    <i class="bi bi-check2 me-1"></i>Mark Selected as Read
                </button>
                <button type="button" class="admin-btn admin-btn-neutral admin-btn-sm" id="markSelectedUnreadBtn">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Mark Selected as Unread
                </button>
            </div>
        </div>

        @error('notification_ids')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror

        @if($notifications->count())
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @foreach($notifications as $notification)
                            <div class="list-group-item d-flex justify-content-between align-items-start gap-2 {{ $notification->is_read ? '' : 'admin-notif-unread-row' }}">
                                <div class="d-flex align-items-start gap-2 flex-grow-1 min-w-0">
                                    <div class="form-check mt-2 flex-shrink-0">
                                        <input
                                            class="form-check-input notification-checkbox"
                                            type="checkbox"
                                            name="notification_ids[]"
                                            value="{{ $notification->notification_id }}"
                                            @checked(in_array($notification->notification_id, old('notification_ids', [])))>
                                    </div>

                                    <button
                                        type="button"
                                        class="btn btn-link text-decoration-none p-0 text-start flex-grow-1 admin-notif-trigger"
                                        data-message="{{ e($notification->message) }}"
                                        data-created="{{ $notification->created_at->format('F d, Y h:i A') }}"
                                        data-type="{{ $notification->notification_type }}"
                                        data-link="{{ $notification->link ?: '' }}">
                                        <div class="{{ $notification->is_read ? 'admin-notif-center-msg' : 'admin-notif-center-msg fw-bold' }}">
                                            @switch($notification->notification_type)
                                                @case('announcement')
                                                    <i class="bi bi-megaphone-fill text-warning me-1"></i>
                                                    @break
                                                @case('violation')
                                                    <i class="bi bi-exclamation-triangle-fill text-danger me-1"></i>
                                                    @break
                                                @case('sanction')
                                                    <i class="bi bi-shield-check text-primary me-1"></i>
                                                    @break
                                                @default
                                                    <i class="bi bi-bell-fill text-muted me-1"></i>
                                            @endswitch
                                            {{ \Illuminate\Support\Str::limit(strip_tags($notification->message), 120) }}
                                        </div>
                                        <small class="text-muted">{{ $notification->created_at->format('F d, Y h:i A') }}</small>
                                    </button>
                                </div>

                                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                    <x-admin.badge :status="$notification->is_read ? 'read' : 'unread'" />
                                    @if($notification->target_type)
                                        <a href="{{ route('student.notifications.open', $notification) }}" class="admin-btn admin-btn-view admin-btn-sm">
                                            <i class="bi bi-box-arrow-up-right"></i>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if($notifications->hasPages())
                    <div class="card-footer bg-white d-flex justify-content-center">
                        {{ $notifications->links() }}
                    </div>
                @endif
            </div>
        @else
            <div class="card portal-card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-bell-slash fs-1 text-muted"></i>
                    <h4 class="mt-3">No notifications available.</h4>
                </div>
            </div>
        @endif
    </form>

</div>

@endsection

@push('scripts')
<div class="modal fade admin-modal" id="notificationDetailModal" tabindex="-1" aria-labelledby="notificationDetailModalLabel" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content admin-modal-content">
            <div class="modal-header admin-modal-header">
                <h5 class="modal-title admin-modal-title" id="notificationDetailModalLabel">Notification</h5>
                <button type="button" class="btn-close admin-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body admin-modal-body">
                <div class="mb-3">
                    <span class="admin-filter-label d-block">Received</span>
                    <strong id="notificationModalDate"></strong>
                </div>
                <div class="mb-0">
                    <span class="admin-filter-label d-block">Message</span>
                    <p class="mb-0" style="white-space: pre-line;" id="notificationModalMessage"></p>
                </div>
            </div>
            <div class="modal-footer admin-modal-footer">
                <a href="#" class="admin-btn admin-btn-primary d-none" id="notificationModalLink">
                    <i class="bi bi-box-arrow-up-right me-1"></i>
                    <span id="notificationModalLinkLabel">View</span>
                </a>
                <button type="button" class="admin-btn admin-btn-neutral" data-bs-dismiss="modal">Close</button>
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
    const linkLabelEl = document.getElementById('notificationModalLinkLabel');

    document.querySelectorAll('.admin-notif-trigger').forEach(function (item) {
        item.addEventListener('click', function () {
            const type = item.dataset.type || 'notification';
            const readableType = type.charAt(0).toUpperCase() + type.slice(1);
            titleEl.textContent = readableType + ' Notification';
            dateEl.textContent = item.dataset.created || '-';
            messageEl.textContent = item.dataset.message || '';

            let link = item.dataset.link || '';

            // Sanction notifications should deep-link to the Sanction Record tab.
            if (type === 'sanction') {
                link = "{{ route('student.record') }}#sanctionRecord";
                linkLabelEl.textContent = 'View Sanction Record';
            } else if (type === 'violation') {
                linkLabelEl.textContent = 'View Violation Record';
            } else if (type === 'announcement') {
                linkLabelEl.textContent = 'View Announcements';
            } else {
                linkLabelEl.textContent = 'View';
            }

            if (link) {
                linkEl.href = link;
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