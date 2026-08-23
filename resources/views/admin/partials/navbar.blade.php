@php
    $adminUser = auth('admin')->user();
    $adminDisplayName = $adminUser?->name ?? ($adminUser?->username ?? 'Admin');
    $adminDisplaySub = $adminUser?->email ?? '';

    // Guarded lookup: admin_info_tbl may not exist in some environments/tests.
    try {
        if ($adminUser && \Illuminate\Support\Facades\Schema::hasTable('admin_info_tbl')) {
            $adminInfo = $adminUser->adminInfo;
            $adminDisplayName = trim(
                ($adminInfo->firstname ?? '') . ' ' . ($adminInfo->lastname ?? '')
            ) ?: $adminDisplayName;
        }
    } catch (\Throwable $e) {
        // keep defaults
    }
@endphp

<nav class="navbar admin-topbar">
    <div class="container-fluid px-3 px-md-4">
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-secondary" id="sidebarToggle" type="button" aria-label="Toggle navigation" aria-controls="adminSidebar" aria-expanded="false">
                <i class="bi bi-list"></i>
            </button>
            <div class="topbar-brand">
                <img src="{{ asset('assets/images/Tracker-logo.png') }}" alt="Tracker logo">
                <div class="topbar-brand-text">
                    <p class="title">PUPTracker</p>
                    <p class="subtitle">{{ request()->routeIs('admin.super-admin.*') ? 'Super Admin Module' : 'Admin Module' }}</p>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            @php
                // Fetch the 5 most recent admin notifications + unread count.
                // Guarded: admin_notifications_tbl may not exist in some environments/tests.
                $adminRecentNotifications = collect();
                $adminUnreadCount = 0;
                try {
                    if (\Illuminate\Support\Facades\Schema::hasTable('admin_notifications_tbl')) {
                        $adminRecentNotifications = \App\Models\AdminNotification::orderBy('created_at', 'desc')->take(5)->get();
                        $adminUnreadCount = \App\Models\AdminNotification::where('is_read', false)->count();
                    }
                } catch (\Throwable $e) {
                    // keep empty defaults
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
                    @if($adminUnreadCount > 0)
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notif-bell-badge">
                            {{ $adminUnreadCount > 9 ? '9+' : $adminUnreadCount }}
                        </span>
                    @endif
                </button>

                <div class="dropdown-menu dropdown-menu-end admin-notif-dropdown">
                    <div class="admin-notif-head">
                        <div>
                            <div class="admin-notif-title"><i class="bi bi-bell-fill" aria-hidden="true"></i>Notifications</div>
                            @if($adminUnreadCount > 0)
                                <span class="admin-notif-count notif-dropdown-count">{{ $adminUnreadCount > 9 ? '9+' : $adminUnreadCount }} unread</span>
                            @endif
                        </div>
                        <a href="{{ route('admin.notifications') }}" class="admin-notif-view-all">View all <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                    </div>
                    <div class="admin-notif-list">
                        @forelse($adminRecentNotifications as $notif)
                            <a href="{{ $notif->link ?: route('admin.notifications') }}" class="admin-notif-item {{ $notif->is_read ? '' : 'unread' }}" data-notif-id="{{ $notif->admin_notification_id }}" data-notif-unread="{{ $notif->is_read ? '0' : '1' }}">
                                <span class="admin-notif-icon {{ $notif->notification_type === 'system_email_failure' ? 'danger' : ($notif->notification_type === 'sanction' ? 'warning' : 'default') }}" aria-hidden="true">
                                    @if($notif->notification_type === 'system_email_failure')
                                        <i class="bi bi-exclamation-octagon-fill"></i>
                                    @elseif($notif->notification_type === 'sanction')
                                        <i class="bi bi-shield-check"></i>
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
                'menuUserName' => $adminDisplayName,
                'menuUserSub' => $adminDisplaySub,
                'menuItems' => [
                    ['label' => 'Profile', 'url' => route('admin.profile'), 'icon' => 'bi-person-vcard'],
                    ['label' => 'Settings', 'url' => route('admin.settings'), 'icon' => 'bi-gear'],
                ],
                'menuLogoutUrl' => route('admin.logout'),
            ])
        </div>
    </div>
</nav>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Refresh the header bell unread badge + dropdown unread label.
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

    // Listen for unread-count changes (e.g. from the notifications page).
    document.addEventListener('puptracker:unread-updated', function (event) {
        if (event.detail && typeof event.detail.unread === 'number') {
            renderUnreadCount(event.detail.unread);
        }
    });

    // Mark an unread notification as read when it is clicked in the dropdown.
    const notifUrl = "{{ route('admin.notifications.unread-count') }}";
    let unreadCount = {{ $adminUnreadCount }};

    document.querySelectorAll('.admin-notif-item[data-notif-unread="1"]').forEach(function (item) {
        item.addEventListener('click', function () {
            const id = item.dataset.notifId;
            if (!id) { return; }

            // Optimistic UI: clear the unread styling immediately.
            item.classList.remove('unread');
            item.dataset.notifUnread = '0';
            item.querySelector('.admin-notif-unread-dot')?.remove();
            unreadCount = Math.max(0, unreadCount - 1);
            document.dispatchEvent(new CustomEvent('puptracker:unread-updated', {
                detail: { unread: unreadCount },
            }));

            fetch(notifUrl.replace(/unread-count$/, '') + id + '/read', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            }).then(function (res) { return res.json(); }).then(function (data) {
                // Re-fetch the true unread count for accuracy.
                return fetch(notifUrl).then(function (r) { return r.json(); });
            }).then(function (data) {
                if (data && typeof data.unread_count === 'number') {
                    renderUnreadCount(data.unread_count);
                }
            }).catch(function () { /* ignore transient errors */ });
        });
    });
});
</script>
@endpush