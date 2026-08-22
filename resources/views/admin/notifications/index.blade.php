@extends('layouts.admin')

@section('title', 'Notifications')

@section('content')

<div class="page-header-modern d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h2 class="fw-bold mb-1">Admin Notifications</h2>
        <p class="text-muted mb-0">View the latest notifications for administrative actions.</p>
    </div>
    <a href="{{ route('admin.announcements') }}" class="admin-btn admin-btn-neutral">
        <i class="bi bi-megaphone" aria-hidden="true"></i>
        Announcements
    </a>
</div>

{{-- Filters --}}
<div class="card shadow-sm border-0 mb-3 admin-filter-card">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.notifications') }}" class="row g-3 align-items-end admin-filter-form">
            <div class="col-md-3">
                <label class="admin-filter-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="read" {{ $status === 'read' ? 'selected' : '' }}>Read</option>
                    <option value="unread" {{ $status === 'unread' ? 'selected' : '' }}>Unread</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="admin-filter-label">Type</label>
                <select name="type" class="form-select">
                    <option value="">All Types</option>
                    @foreach($types as $t)
                        <option value="{{ $t }}" {{ (string) $type === (string) $t ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $t)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="admin-filter-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search message..." value="{{ request('search') }}">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="admin-btn admin-btn-primary w-100">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <a href="{{ route('admin.notifications') }}" class="admin-btn admin-btn-neutral" title="Reset filters" aria-label="Reset filters">
                    <i class="bi bi-arrow-repeat"></i>
                </a>
            </div>
        </form>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <form action="{{ route('admin.notifications.mark-all-read') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="admin-btn admin-btn-view admin-btn-sm">
                    <i class="bi bi-check2-all me-1"></i>Mark All as Read
                </button>
            </form>
            <span class="align-self-center text-muted small">{{ $unreadCount }} unread</span>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        @if($notifications->count())
            <div class="list-group list-group-flush">
                @foreach($notifications as $notification)
                    <div class="list-group-item d-flex justify-content-between align-items-start gap-2 {{ $notification->is_read ? '' : 'list-group-item-warning' }}">
                        <button
                            type="button"
                            class="btn btn-link text-decoration-none p-0 text-start flex-grow-1 admin-notif-trigger"
                            data-message="{{ e($notification->message) }}"
                            data-created="{{ optional($notification->created_at)->format('F d, Y h:i A') }}"
                            data-type="{{ $notification->notification_type }}"
                            data-link="{{ $notification->link ?: '' }}"
                            data-id="{{ $notification->admin_notification_id }}">
                            <div class="fw-semibold">
                                @if ($notification->notification_type === 'system_email_failure')
                                    <i class="bi bi-exclamation-octagon-fill text-danger me-1"></i>
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
                                <form action="{{ route('admin.notifications.read', $notification) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="admin-btn admin-btn-view admin-btn-sm" title="Mark as read" aria-label="Mark as read">
                                        <i class="bi bi-check2"></i>
                                    </button>
                                </form>
                            @else
                                <form action="{{ route('admin.notifications.unread', $notification) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="admin-btn admin-btn-neutral admin-btn-sm" title="Mark as unread" aria-label="Mark as unread">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 d-flex justify-content-center p-3">
                {{ $notifications->appends(request()->query())->links() }}
            </div>
        @else
            <x-admin.empty-state
                icon="bi-bell-slash"
                title="No notifications found"
                message="Try adjusting your filters, or once notifications are generated they will appear here."
                :clearUrl="route('admin.notifications')" />
        @endif
    </div>
</div>

{{-- Notification detail modal --}}
<div class="modal fade admin-modal" id="adminNotificationModal" tabindex="-1" aria-labelledby="adminNotificationModalLabel" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content admin-modal-content">
            <div class="modal-header admin-modal-header">
                <h5 class="modal-title admin-modal-title" id="adminNotificationModalLabel">Notification</h5>
                <button type="button" class="btn-close admin-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body admin-modal-body">
                <small class="text-muted d-block mb-3" id="adminNotificationModalDate"></small>
                <p class="mb-0" style="white-space: pre-line;" id="adminNotificationModalMessage"></p>
            </div>
            <div class="modal-footer admin-modal-footer">
                <a href="#" class="admin-btn admin-btn-primary d-none" id="adminNotificationModalLink">
                    <i class="bi bi-box-arrow-up-right me-1"></i>
                    <span id="adminNotificationModalLinkLabel">Open</span>
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
    const modalElement = document.getElementById('adminNotificationModal');
    if (!modalElement) {
        return;
    }

    const modal = new bootstrap.Modal(modalElement);
    const titleEl = document.getElementById('adminNotificationModalLabel');
    const dateEl = document.getElementById('adminNotificationModalDate');
    const messageEl = document.getElementById('adminNotificationModalMessage');
    const linkEl = document.getElementById('adminNotificationModalLink');
    const linkLabelEl = document.getElementById('adminNotificationModalLinkLabel');

    document.querySelectorAll('.admin-notif-trigger').forEach(function (item) {
        item.addEventListener('click', function () {
            const type = item.dataset.type || 'notification';
            const readableType = type.charAt(0).toUpperCase() + type.replace(/_/g, ' ');
            titleEl.textContent = readableType + ' Notification';
            dateEl.textContent = item.dataset.created || '-';
            messageEl.textContent = item.dataset.message || '';

            const link = item.dataset.link || '';
            if (type === 'sanction') {
                linkLabelEl.textContent = 'View Sanction';
            } else if (type === 'system_email_failure') {
                linkLabelEl.textContent = 'View Details';
            } else {
                linkLabelEl.textContent = 'Open';
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
});
</script>
@endpush
