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

        <div class="d-flex align-items-center">
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