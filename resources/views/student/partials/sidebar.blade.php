<aside class="portal-sidebar" id="portalSidebar">
    {{-- Mobile-only sidebar close (×). See `.sidebar-close` in admin.css. --}}
    <button type="button" class="sidebar-close" id="portalSidebarClose" aria-label="Close sidebar">
        <i class="bi bi-x-lg" aria-hidden="true"></i>
    </button>

    <div class="portal-logo-wrap">
        <img src="{{ asset('assets/images/SystemLogo_w/name.png') }}" alt="PUPTracker logo" class="portal-logo">
        <h5>PUPTracker</h5>
        <small>Violation System</small>
        <span class="portal-module-label">Student Portal</span>
    </div>

    <ul class="portal-nav-list">
        <li><a href="{{ route('student.dashboard') }}" class="{{ request()->routeIs('student.dashboard') ? 'active' : '' }}"><i class="bi bi-house-door"></i>Dashboard</a></li>
        <li><a href="{{ route('student.record') }}" class="{{ request()->routeIs('student.record') ? 'active' : '' }}"><i class="bi bi-file-earmark-text"></i>My Record</a></li>
        <li><a href="{{ route('student.announcements') }}" class="{{ request()->routeIs('student.announcements') ? 'active' : '' }}"><i class="bi bi-megaphone"></i>Announcements</a></li>
        <li><a href="{{ route('student.notifications') }}" class="{{ request()->routeIs('student.notifications') ? 'active' : '' }}"><i class="bi bi-bell"></i>Notifications<span class="portal-nav-badge" data-nav-unread hidden></span></a></li>
    </ul>

    <div class="portal-sidebar-footer" aria-hidden="true">
        <span class="portal-sidebar-dash"></span>
        <p>Mula sa'yo,<br>Para sa Bayan</p>
    </div>
</aside>