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