@php
    /**
     * OSS Staff top bar.
     *
     * The authenticated account is an ADMIN account holding the OSS Staff role,
     * so every identity lookup here goes through the `admin` guard and the
     * Admin model's `adminInfo` relation. It must never touch the `security`
     * guard or a Security profile table: OSS Staff is not a Security account.
     */
    $ossStaffUser = auth('admin')->user();
    $ossStaffDisplayName = $ossStaffUser?->email ?? 'OSS Staff';
    $ossStaffDisplaySub = 'OSS Staff';

    // Guarded lookup: admin_info_tbl may not exist in every environment/test.
    try {
        if ($ossStaffUser && \Illuminate\Support\Facades\Schema::hasTable('admin_info_tbl')) {
            $info = $ossStaffUser->adminInfo;

            $ossStaffDisplayName = trim(
                ($info->firstname ?? '') . ' ' . ($info->middlename ?? '') . ' ' . ($info->lastname ?? '')
            ) ?: $ossStaffDisplayName;
        }
    } catch (\Throwable $e) {
        // keep defaults
    }
@endphp

<nav class="navbar portal-topbar">
    <div class="container-fluid px-3 px-md-4">
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-secondary" id="portalSidebarToggle" type="button" aria-label="Toggle navigation" aria-controls="portalSidebar" aria-expanded="false">
                <i class="bi bi-list"></i>
            </button>
            <div class="portal-topbar-brand">
                <img src="{{ asset('assets/images/Tracker-logo.png') }}" alt="Tracker logo">
                <div>
                    <p class="portal-title mb-0">PUPTracker</p>
                    <small>OSS Staff Portal</small>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            @php
                // OSS Staff notifications are ADMIN notifications addressed to
                // this exact admin account, so the bell uses the admin
                // notification audience scope (never the security inbox).
                $ossStaffRecentNotifications = collect();
                $ossStaffUnreadCount = 0;
                try {
                    if ($ossStaffUser && \Illuminate\Support\Facades\Schema::hasTable('admin_notifications_tbl')) {
                        $ossStaffNotificationQuery = \App\Models\AdminNotification::query()
    ->where('recipient_type', \App\Models\AdminNotification::AUDIENCE_ADMIN)
    ->where('recipient_id', (int) $ossStaffUser->id);

                        $ossStaffRecentNotifications = (clone $ossStaffNotificationQuery)
                            ->latest('created_at')
                            ->take(5)
                            ->get();

                        $ossStaffUnreadCount = (clone $ossStaffNotificationQuery)
                            ->where('is_read', false)
                            ->count();
                    }
                } catch (\Throwable $e) {
                    // keep defaults
                }
            @endphp

            <div class="dropdown admin-notif-bell">
                <button
                    class="btn btn-sm btn-outline-secondary position-relative admin-notif-bell-btn"
                    type="button"
                    data-bs-toggle="dropdown"
                    data-bs-auto-close="true"
                    aria-expanded="false"
                    aria-label="Notifications">
                    <i class="bi bi-bell"></i>
                    @if($ossStaffUnreadCount > 0)
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notif-bell-badge">
                            {{ $ossStaffUnreadCount > 9 ? '9+' : $ossStaffUnreadCount }}
                        </span>
                    @endif
                </button>

                <div class="dropdown-menu dropdown-menu-end admin-notif-dropdown" data-unread-count="{{ $ossStaffUnreadCount }}">
                    <div class="admin-notif-head">
                        <div>
                            <div class="admin-notif-title"><i class="bi bi-bell-fill" aria-hidden="true"></i>Notifications</div>
                            @if($ossStaffUnreadCount > 0)
                                <span class="admin-notif-count notif-dropdown-count">{{ $ossStaffUnreadCount > 9 ? '9+' : $ossStaffUnreadCount }} unread</span>
                            @endif
                        </div>
                        <a href="{{ route('oss-staff.notifications') }}" class="admin-notif-view-all">View all <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                    </div>
                    <div class="admin-notif-list">
                        @forelse($ossStaffRecentNotifications as $notif)
                            <a href="{{ $notif->target_type ? route('oss-staff.notifications.open', $notif) : route('oss-staff.notifications') }}" class="admin-notif-item {{ $notif->is_read ? '' : 'unread' }}" data-notif-id="{{ $notif->admin_notification_id }}" data-notif-unread="{{ $notif->is_read ? '0' : '1' }}">
                                <span class="admin-notif-icon {{ $notif->notification_type === 'violation' || $notif->notification_type === 'violation_confirmation' ? 'danger' : 'default' }}" aria-hidden="true">
                                    @if($notif->notification_type === 'violation' || $notif->notification_type === 'violation_confirmation')
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                    @else
                                        <i class="bi bi-bell-fill"></i>
                                    @endif
                                </span>
                                <span class="admin-notif-content">
                                    <span class="admin-notif-msg">{{ \Illuminate\Support\Str::limit($notif->message, 85) }}</span>
                                    <small>{{ optional($notif->created_at)->diffForHumans() }}</small>
                                </span>
                                @if(! $notif->is_read)<span class="admin-notif-unread-dot" aria-label="Unread"></span>@endif
                            </a>
                        @empty
                            <div class="text-center text-muted py-4 small">No notifications yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            @include('partials.user-menu', [
                'menuUserName' => $ossStaffDisplayName,
                'menuUserSub' => $ossStaffDisplaySub,
                'menuItems' => [],
                // OSS Staff accounts authenticate on the ADMIN guard, so the
                // logout endpoint is the admin one.
                'menuLogoutUrl' => route('admin.logout'),
            ])
        </div>
    </div>
</nav>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const notifUrl = "{{ route('oss-staff.notifications.unread-count') }}";

    function renderUnreadCount(unread) {
        const bell = document.querySelector('.notif-bell-badge');
        const badge = document.querySelector('.notif-dropdown-count');
        if (unread > 0) {
            if (bell) {
                bell.textContent = unread > 9 ? '9+' : unread;
                bell.classList.remove('d-none');
            }
            if (badge) {
                badge.textContent = (unread > 9 ? '9+' : unread) + ' unread';
                badge.classList.remove('d-none');
            }
        } else {
            if (bell) { bell.classList.add('d-none'); }
            if (badge) { badge.classList.add('d-none'); }
        }
    }

    document.querySelectorAll('.admin-notif-item[data-notif-unread="1"]').forEach(function (item) {
        item.addEventListener('click', function () {
            const id = item.dataset.notifId;
            if (!id) { return; }
            item.classList.remove('unread');
            item.dataset.notifUnread = '0';
            item.querySelector('.admin-notif-unread-dot')?.remove();

            fetch(notifUrl.replace(/unread-count$/, '') + id + '/read', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            }).then(function (response) {
                if (!response.ok) { return; }
                return response.json().catch(function () { return null; });
            }).then(function (data) {
                if (data && typeof data.unread_count === 'number') {
                    renderUnreadCount(data.unread_count);
                }
            }).catch(function () {
                // A failed "mark read" must never block the navigation.
            });
        });
    });
});
</script>
@endpush
