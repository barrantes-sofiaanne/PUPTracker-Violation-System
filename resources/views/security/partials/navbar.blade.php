@php
    $securityUser = auth('security')->user();
    $securityDisplayName = $securityUser?->email ?? 'Security';
    $securityDisplaySub = $securityUser?->email ?? '';

    // Guarded lookup: security profile tables may not exist in some environments/tests.
    try {
        if ($securityUser && \Illuminate\Support\Facades\Schema::hasTable('security_info')) {
            $profile = $securityUser->securityProfile;
            $securityDisplayName = trim(
                ($profile->firstname ?? '') . ' ' . ($profile->lastname ?? '')
            ) ?: $securityDisplayName;
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
                    <small>Security Portal</small>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            @include('partials.user-menu', [
                'menuUserName' => $securityDisplayName,
                'menuUserSub' => $securityDisplaySub,
                'menuItems' => [
                    ['label' => 'Profile', 'url' => route('security.profile'), 'icon' => 'bi-person-vcard'],
                ],
                'menuLogoutUrl' => route('security.logout'),
            ])
        </div>
    </div>
</nav>