<aside class="portal-sidebar" id="portalSidebar">
    {{-- Mobile-only sidebar close (×). See `.sidebar-close` in admin.css. --}}
    <button type="button" class="sidebar-close" id="portalSidebarClose" aria-label="Close sidebar">
        <i class="bi bi-x-lg" aria-hidden="true"></i>
    </button>

    <div class="portal-logo-wrap">
        <img src="{{ asset('assets/images/SystemLogo_w/name.png') }}" alt="PUPTracker logo" class="portal-logo">
        <h5>PUPTracker</h5>
        <small>Security Module</small>
    </div>

    <ul class="portal-nav-list">
        <li><a href="{{ route('security.dashboard') }}" class="{{ request()->routeIs('security.dashboard') ? 'active' : '' }}"><i class="bi bi-shield-lock-fill"></i>Dashboard</a></li>
        <li><a href="{{ route('security.violations.students') }}" class="{{ request()->routeIs('security.violations.students') || request()->routeIs('security.violations.submitted') || request()->routeIs('security.violations.show') ? 'active' : '' }}"><i class="bi bi-exclamation-triangle-fill"></i>Violations</a></li>
        <li><a href="{{ route('security.announcements') }}" class="{{ request()->routeIs('security.announcements') ? 'active' : '' }}"><i class="bi bi-megaphone-fill"></i>Announcements</a></li>
        <li><a href="{{ route('security.notifications') }}" class="{{ request()->routeIs('security.notifications') ? 'active' : '' }}"><i class="bi bi-bell-fill"></i>Notifications</a></li>
    </ul>
</aside>