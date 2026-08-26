@php($studentUser = auth('student')->user())

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
                    <small>Student Portal</small>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            @php
                // Student notification bell data (guarded for environments without the table).
                $studentRecentNotifications = collect();
                $studentUnreadCount = 0;
                try {
                    if (\Illuminate\Support\Facades\Schema::hasTable('notifications_tbl')) {
                        $studentNotificationQuery = \App\Models\Notification::where('student_number', $studentUser->student_number ?? '');
                        $studentRecentNotifications = (clone $studentNotificationQuery)->latest('created_at')->take(5)->get();
                        $studentUnreadCount = (clone $studentNotificationQuery)->where('is_read', false)->count();
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
                    @if($studentUnreadCount > 0)
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notif-bell-badge">
                            {{ $studentUnreadCount > 9 ? '9+' : $studentUnreadCount }}
                        </span>
                    @endif
                </button>

                <div class="dropdown-menu dropdown-menu-end admin-notif-dropdown" data-unread-count="{{ $studentUnreadCount }}">
                    <div class="admin-notif-head">
                        <div>
                            <div class="admin-notif-title"><i class="bi bi-bell-fill" aria-hidden="true"></i>Notifications</div>
                            @if($studentUnreadCount > 0)
                                <span class="admin-notif-count notif-dropdown-count">{{ $studentUnreadCount > 9 ? '9+' : $studentUnreadCount }} unread</span>
                            @endif
                        </div>
                        <a href="{{ route('student.notifications') }}" class="admin-notif-view-all">View all <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                    </div>
                    <div class="admin-notif-list">
                        @forelse($studentRecentNotifications as $notif)
                            <a href="{{ $notif->target_type ? route('student.notifications.open', $notif) : route('student.notifications') }}" class="admin-notif-item {{ $notif->is_read ? '' : 'unread' }}" data-notif-id="{{ $notif->notification_id }}" data-notif-unread="{{ $notif->is_read ? '0' : '1' }}">
                                <span class="admin-notif-icon {{ $notif->notification_type === 'violation' ? 'danger' : ($notif->notification_type === 'sanction' ? 'warning' : 'default') }}" aria-hidden="true">
                                    @if($notif->notification_type === 'violation')
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                    @elseif($notif->notification_type === 'sanction')
                                        <i class="bi bi-shield-check"></i>
                                    @elseif($notif->notification_type === 'announcement')
                                        <i class="bi bi-megaphone-fill"></i>
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
                'menuUserName' => trim(($studentUser->first_name ?? '') . ' ' . ($studentUser->last_name ?? '')) ?: 'Student',
                'menuUserSub' => $studentUser->student_number ?? $studentUser->email ?? '',
                'menuItems' => [
                    ['label' => 'Profile', 'url' => route('student.profile'), 'icon' => 'bi-person-vcard'],
                    ['label' => 'Settings', 'url' => route('student.settings'), 'icon' => 'bi-gear'],
                ],
                'menuLogoutUrl' => route('logout'),
            ])
        </div>
    </div>
</nav>