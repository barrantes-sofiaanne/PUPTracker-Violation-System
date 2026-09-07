<aside class="portal-sidebar" id="portalSidebar">
    <button type="button" class="btn btn-sm btn-light d-lg-none mb-2" id="portalSidebarClose">
        <i class="bi bi-x-lg"></i>
    </button>

    <div class="portal-logo-wrap">
        <img src="{{ asset('assets/images/SystemLogo_w/name.png') }}" alt="PUPTracker logo" class="portal-logo">
        <h5>PUPTracker</h5>
        <small>Security Module</small>
    </div>

    <ul class="portal-nav-list">
        <li><a href="{{ route('security.dashboard') }}" class="{{ request()->routeIs('security.dashboard') ? 'active' : '' }}"><i class="bi bi-shield-lock-fill"></i>Dashboard</a></li>
        <li><a href="{{ route('security.violations.students') }}" class="{{ request()->routeIs('security.violations.students') || request()->routeIs('security.violations.show') ? 'active' : '' }}"><i class="bi bi-exclamation-triangle-fill"></i>Violations</a></li>
        <li><a href="{{ route('security.violations.submitted') }}" class="{{ request()->routeIs('security.violations.submitted') ? 'active' : '' }}"><i class="bi bi-send-fill"></i>Submitted Violations</a></li>
        <li><a href="{{ route('security.announcements') }}" class="{{ request()->routeIs('security.announcements') ? 'active' : '' }}"><i class="bi bi-megaphone-fill"></i>Announcements</a></li>
        <li><a href="{{ route('security.notifications') }}" class="{{ request()->routeIs('security.notifications') ? 'active' : '' }}"><i class="bi bi-bell-fill"></i>Notifications</a></li>
    </ul>
</aside>