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
            <form action="{{ route('admin.logout') }}" method="POST" class="mb-0">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i>
                    Logout
                </button>
            </form>
        </div>
    </div>
</nav>