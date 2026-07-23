<aside class="portal-sidebar" id="portalSidebar">
    <button type="button" class="btn btn-sm btn-light d-lg-none mb-2" id="portalSidebarClose">
        <i class="bi bi-x-lg"></i>
    </button>

    <div class="portal-logo-wrap">
        <img src="{{ asset('assets/images/Tracker-logo.png') }}" alt="Tracker logo" class="portal-logo">
        <h5>PUPTracker</h5>
        <small>Security Module</small>
    </div>

    <ul class="portal-nav-list">
        <li><a href="{{ route('security.dashboard') }}" class="{{ request()->routeIs('security.dashboard') ? 'active' : '' }}"><i class="bi bi-shield-lock-fill"></i>Dashboard</a></li>
        <li><a href="{{ route('security.violations.students') }}" class="{{ request()->routeIs('security.violations.*') ? 'active' : '' }}"><i class="bi bi-exclamation-triangle-fill"></i>Violations</a></li>
        <li><a href="{{ route('security.notifications') }}" class="{{ request()->routeIs('security.notifications') ? 'active' : '' }}"><i class="bi bi-bell-fill"></i>Notifications</a></li>
    </ul>

    <form action="{{ route('security.logout') }}" method="POST" class="portal-logout-form">
        @csrf
        <button type="submit" class="btn btn-outline-light w-100">
            <i class="bi bi-box-arrow-right me-2"></i>Logout
        </button>
    </form>
</aside>