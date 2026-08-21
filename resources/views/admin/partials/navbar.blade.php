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

                <div class="dropdown-menu dropdown-menu-end admin-notif-dropdown shadow">
                    <div class="admin-notif-head">
                        <strong>Notifications</strong>
                        <a href="{{ route('admin.notifications') }}" class="small">View All</a>
                    </div>
                    <div class="admin-notif-list">
                        @forelse($adminRecentNotifications as $notif)
                            <a href="{{ $notif->link ?: route('admin.notifications') }}" class="admin-notif-item {{ $notif->is_read ? '' : 'unread' }}">
                                <div class="admin-notif-msg">
                                    @if($notif->notification_type === 'system_email_failure')
                                        <i class="bi bi-exclamation-octagon-fill text-danger me-1"></i>
                                    @else
                                        <i class="bi bi-bell-fill text-muted me-1"></i>
                                    @endif
                                    {{ \Illuminate\Support\Str::limit($notif->message, 70) }}
                                </div>
                                <small class="text-muted">{{ optional($notif->created_at)->diffForHumans() }}</small>
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

@push('styles')
<style>
    .admin-notif-bell-btn {
        width: 38px;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-color: rgba(128, 0, 0, 0.3);
        color: var(--color-maroon);
    }

    .notif-bell-badge {
        font-size: 0.6rem;
    }

    .admin-notif-dropdown {
        width: 340px;
        padding: 0;
        border: 1px solid rgba(128, 0, 0, 0.14);
        border-radius: 0.9rem;
    }

    .admin-notif-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.7rem 0.9rem;
        border-bottom: 1px solid rgba(128, 0, 0, 0.1);
        color: #1f1620;
    }

    .admin-notif-list {
        max-height: 360px;
        overflow-y: auto;
    }

    .admin-notif-item {
        display: block;
        padding: 0.65rem 0.9rem;
        text-decoration: none;
        color: #1f1620;
        border-bottom: 1px solid rgba(128, 0, 0, 0.06);
    }

    .admin-notif-item:last-child {
        border-bottom: none;
    }

    .admin-notif-item:hover {
        background: rgba(255, 223, 0, 0.15);
    }

    .admin-notif-item.unread {
        background: #fff6d6;
    }

    .admin-notif-msg {
        font-size: 0.85rem;
        font-weight: 600;
        line-height: 1.4;
    }

    .admin-notif-item small {
        font-size: 0.72rem;
    }
</style>
@endpush