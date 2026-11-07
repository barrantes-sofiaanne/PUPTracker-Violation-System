
@php
    $securityUser = auth('security')->user();

    $securityDisplayName = $securityUser?->email ?? 'Security';
    $securityDisplaySub = $securityUser?->email ?? '';

    if ($securityUser) {
        $securityInfo = $securityUser->securityInfo;

        $securityName = trim(
            ($securityInfo?->firstname ?? '') . ' ' .
            ($securityInfo?->lastname ?? '')
        );

        if ($securityName !== '') {
            $securityDisplayName = $securityName;
        }
    }
@endphp

<nav class="navbar portal-topbar">
    <div class="container-fluid px-3 px-md-4">

        <div class="d-flex align-items-center gap-2">
            <button
                class="btn btn-sm btn-outline-secondary"
                id="portalSidebarToggle"
                type="button"
                aria-label="Toggle navigation"
                aria-controls="portalSidebar"
                aria-expanded="false">

                <i class="bi bi-list"></i>
            </button>

            <div class="portal-topbar-brand">
                <img
                    src="{{ asset('assets/images/Tracker-logo.png') }}"
                    alt="Tracker logo">

                <div>
                    <p class="portal-title mb-0">PUPTracker</p>
                    <small>Security Portal</small>
                </div>
            </div>
        </div>

        <div class="security-topbar-actions d-flex align-items-center gap-2">

            @php
                // Security notification bell data.
                // Guarded for environments without the notification table.

                $securityRecentNotifications = collect();
                $securityUnreadCount = 0;

                try {
                    if (\Illuminate\Support\Facades\Schema::hasTable('notifications_tbl')) {

                        $securityId = auth('security')->id();

                        $securityNotificationQuery = \App\Models\Notification::query()
                            ->forRecipient('security', $securityId);

                        $securityRecentNotifications = (
                            clone $securityNotificationQuery
                        )
                            ->latest('created_at')
                            ->take(5)
                            ->get();

                        $securityUnreadCount = (
                            clone $securityNotificationQuery
                        )
                            ->where('is_read', false)
                            ->count();
                    }
                } catch (\Throwable $e) {
                    // Keep default values if notification loading fails.
                }
            @endphp

            {{-- Notification Dropdown --}}
            <div class="dropdown admin-notif-bell">

                <button
                    class="btn btn-sm btn-outline-secondary position-relative admin-notif-bell-btn"
                    type="button"
                    data-bs-toggle="dropdown"
                    data-bs-auto-close="true"
                    aria-expanded="false"
                    aria-label="Notifications">

                    <i class="bi bi-bell"></i>

                    @if($securityUnreadCount > 0)
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notif-bell-badge">
                            {{ $securityUnreadCount > 9 ? '9+' : $securityUnreadCount }}
                        </span>
                    @endif

                </button>

                <div
                    class="dropdown-menu dropdown-menu-end admin-notif-dropdown"
                    data-unread-count="{{ $securityUnreadCount }}">

                    <div class="admin-notif-head">

                        <div>
                            <div class="admin-notif-title">
                                <i class="bi bi-bell-fill" aria-hidden="true"></i>
                                Notifications
                            </div>

                            @if($securityUnreadCount > 0)
                                <span class="admin-notif-count notif-dropdown-count">
                                    {{ $securityUnreadCount > 9 ? '9+' : $securityUnreadCount }}
                                    unread
                                </span>
                            @endif
                        </div>

                        <a
                            href="{{ route('security.notifications') }}"
                            class="admin-notif-view-all">

                            View all
                            <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                        </a>

                    </div>

                    <div class="admin-notif-list">

                        @forelse($securityRecentNotifications as $notif)

                            <a
                                href="{{ $notif->target_type
                                    ? route('security.notifications.open', $notif)
                                    : route('security.notifications') }}"
                                class="admin-notif-item {{ $notif->is_read ? '' : 'unread' }}"
                                data-notif-id="{{ $notif->notification_id }}"
                                data-notif-unread="{{ $notif->is_read ? '0' : '1' }}">

                                <span
                                    class="admin-notif-icon {{ $notif->notification_type === 'violation' ? 'danger' : 'default' }}"
                                    aria-hidden="true">

                                    @if($notif->notification_type === 'violation')
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                    @else
                                        <i class="bi bi-bell-fill"></i>
                                    @endif

                                </span>

                                <span class="admin-notif-content">

                                    <span class="admin-notif-msg">
                                        {{ \Illuminate\Support\Str::limit($notif->message, 85) }}
                                    </span>

                                    <small>
                                        {{ optional($notif->created_at)->diffForHumans() }}
                                    </small>

                                </span>

                                @if(! $notif->is_read)
                                    <span
                                        class="admin-notif-unread-dot"
                                        aria-label="Unread">
                                    </span>
                                @endif

                            </a>

                        @empty

                            <div class="text-center text-muted py-4 small">
                                No notifications yet.
                            </div>

                        @endforelse

                    </div>
                </div>
            </div>

            {{-- Avatar-only user menu --}}
            @include('partials.user-menu', [
                'menuUserName' => $securityDisplayName,
                'menuUserSub' => $securityDisplaySub,
                'menuItems' => [
                    [
                        'label' => 'Profile',
                        'url' => route('security.profile'),
                        'icon' => 'bi-person-vcard'
                    ],
                ],
                'menuLogoutUrl' => route('security.logout'),
                'menuAvatarOnly' => true,
            ])

        </div>
    </div>
</nav>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    const notifUrl = "{{ route('security.notifications.unread-count') }}";

    function renderUnreadCount(unread) {

        const bell = document.querySelector('.notif-bell-badge');
        const badge = document.querySelector('.notif-dropdown-count');

        if (unread > 0) {

            if (bell) {
                bell.textContent = unread > 9 ? '9+' : unread;
                bell.classList.remove('d-none');
            }

            if (badge) {
                badge.textContent =
                    (unread > 9 ? '9+' : unread) + ' unread';

                badge.classList.remove('d-none');
            }

        } else {

            if (bell) {
                bell.classList.add('d-none');
            }

            if (badge) {
                badge.classList.add('d-none');
            }
        }
    }

    document.querySelectorAll(
        '.admin-notif-item[data-notif-unread="1"]'
    ).forEach(function (item) {

        item.addEventListener('click', function () {

            const id = item.dataset.notifId;

            if (!id) {
                return;
            }

            item.classList.remove('unread');
            item.dataset.notifUnread = '0';

            item.querySelector('.admin-notif-unread-dot')?.remove();

            fetch(notifUrl.replace(/unread-count$/, '') + id + '/read', {
                method: 'POST',

                headers: {
                    'X-CSRF-TOKEN': document.querySelector(
                        'meta[name="csrf-token"]'
                    ).content,

                    'Accept': 'application/json',
                },

            }).catch(function () {
                // Ignore transient errors.
            });

        });

    });

});
</script>
@endpush
