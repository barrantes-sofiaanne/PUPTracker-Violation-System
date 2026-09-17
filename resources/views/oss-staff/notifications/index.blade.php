@extends('layouts.oss-staff')

@section('title', 'OSS Staff Notifications')

@section('content')
<div class="container-fluid py-1">

    {{-- Header (same design as the Admin module) --}}
    <div class="page-header-modern d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">OSS Staff Notifications</h2>
            <p class="text-muted mb-0">You have <strong>{{ $unreadCount }}</strong> unread notification(s). Monitor confirmations and updates on the violations you recorded.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('oss-staff.dashboard') }}" class="admin-btn admin-btn-neutral">
                <i class="bi bi-speedometer2 me-1"></i>Back to Dashboard
            </a>
        </div>
    </div>

    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Result count --}}
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <span class="text-muted small">
            Showing {{ $notifications->firstItem() ?? 0 }}–{{ $notifications->lastItem() ?? 0 }} of {{ $notifications->total() }} notifications
        </span>
    </div>

    {{-- Mark all as read --}}
    <div class="d-flex flex-wrap gap-2 mb-3">
        <form action="{{ route('oss-staff.notifications.mark-all-read') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="admin-btn admin-btn-view admin-btn-sm">
                <i class="bi bi-check2-all me-1"></i>Mark All as Read
            </button>
        </form>
        <span class="align-self-center text-muted small">{{ $unreadCount }} unread</span>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            @if($notifications->count())
                <div class="list-group list-group-flush">
                    @foreach($notifications as $notification)
                        <div class="list-group-item d-flex justify-content-between align-items-start gap-2 {{ $notification->is_read ? '' : 'admin-notif-unread-row' }}">
                            <button
                                type="button"
                                class="btn btn-link text-decoration-none p-0 text-start flex-grow-1 admin-notif-trigger"
                                data-message="{{ e($notification->message) }}"
                                data-created="{{ optional($notification->created_at)->format('F d, Y h:i A') }}"
                                data-type="{{ $notification->notification_type }}"
                                data-target-type="{{ $notification->target_type }}"
                                data-link="{{ $notification->target_type ? route('oss-staff.notifications.open', $notification) : ($notification->link ?: '') }}"
                                data-title="{{ $notification->title ?: '' }}"
                                data-id="{{ $notification->notification_id }}">
                                <div class="{{ $notification->is_read ? 'admin-notif-center-msg' : 'admin-notif-center-msg fw-bold' }}">
                                    @if ($notification->notification_type === 'violation')
                                        <i class="bi bi-exclamation-triangle-fill text-danger me-1"></i>
                                    @elseif ($notification->notification_type === 'sanction')
                                        <i class="bi bi-shield-check text-primary me-1"></i>
                                    @else
                                        <i class="bi bi-bell-fill text-muted me-1"></i>
                                    @endif
                                    {{ \Illuminate\Support\Str::limit($notification->message, 90) }}
                                </div>
                                <small class="text-muted">{{ optional($notification->created_at)->format('F d, Y h:i A') }}</small>
                            </button>

                            <div class="d-flex align-items-center gap-2">
                                <x-admin.badge :status="$notification->is_read ? 'read' : 'unread'" />
                                @if(! $notification->is_read)
                                    <form action="{{ route('oss-staff.notifications.read', $notification) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="admin-btn admin-btn-view admin-btn-sm" title="Mark as read" aria-label="Mark as read">
                                            <i class="bi bi-check2"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 d-flex justify-content-center p-3">
                    {{ $notifications->links() }}
                </div>
            @else
                <x-admin.empty-state
                    icon="bi-bell-slash"
                    title="No notifications found"
                    message="Once notifications are generated they will appear here."
                    :clearUrl="route('oss-staff.notifications')" />
            @endif
        </div>
    </div>
</div>

{{-- Notification detail modal (admin-style) --}}
<div class="modal fade admin-modal" id="ossStaffNotificationModal" tabindex="-1" aria-labelledby="ossStaffNotificationModalLabel" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content admin-modal-content">
            <div class="modal-header admin-modal-header">
                <h5 class="modal-title admin-modal-title" id="ossStaffNotificationModalLabel">Notification</h5>
                <button type="button" class="btn-close admin-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body admin-modal-body">
                <div class="mb-3">
                    <span class="admin-filter-label d-block">Received</span>
                    <strong id="ossStaffNotificationModalDate"></strong>
                </div>
                <div class="mb-0">
                    <span class="admin-filter-label d-block">Message</span>
                    <p class="mb-0" style="white-space: pre-line;" id="ossStaffNotificationModalMessage"></p>
                </div>
            </div>
            <div class="modal-footer admin-modal-footer">
                <a href="#" class="admin-btn admin-btn-primary d-none" id="ossStaffNotificationModalLink">
                    <i class="bi bi-box-arrow-up-right me-1"></i>
                    <span id="ossStaffNotificationModalLinkLabel">Open</span>
                </a>
                <button type="button" class="admin-btn admin-btn-neutral" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('ossStaffNotificationModal');
    if (!modalElement) {
        return;
    }

    const modal = new bootstrap.Modal(modalElement);
    const titleEl = document.getElementById('ossStaffNotificationModalLabel');
    const dateEl = document.getElementById('ossStaffNotificationModalDate');
    const messageEl = document.getElementById('ossStaffNotificationModalMessage');
    const linkEl = document.getElementById('ossStaffNotificationModalLink');
    const linkLabelEl = document.getElementById('ossStaffNotificationModalLinkLabel');

    document.querySelectorAll('.admin-notif-trigger').forEach(function (item) {
        item.addEventListener('click', function () {
            titleEl.textContent = item.dataset.title || 'OSS Staff Notification';
            dateEl.textContent = item.dataset.created || '';
            messageEl.textContent = item.dataset.message || '';

            // Record-specific label, mirroring the Student Module: when the
            // notification points at an exact record the action names that
            // record instead of a generic "Open".
            const targetType = (item.dataset.targetType || '').toLowerCase();
            if (targetType === 'violation' || targetType === 'sanction') {
                linkLabelEl.textContent = 'View Record';
            } else {
                linkLabelEl.textContent = 'Open';
            }

            const link = item.dataset.link || '';
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
});
</script>
@endpush
