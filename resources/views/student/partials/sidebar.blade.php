<aside class="portal-sidebar" id="portalSidebar">
    <button type="button" class="btn btn-sm btn-light d-lg-none mb-2" id="portalSidebarClose">
        <i class="bi bi-x-lg"></i>
    </button>

    <div class="portal-logo-wrap">
        <img src="{{ asset('assets/images/SystemLogo_w/name.png') }}" alt="PUPTracker logo" class="portal-logo">
        <h5>PUPTracker</h5>
        <small>Student Module</small>
    </div>

    <ul class="portal-nav-list">
        <li><a href="{{ route('student.dashboard') }}" class="{{ request()->routeIs('student.dashboard') ? 'active' : '' }}"><i class="bi bi-house-door-fill"></i>Dashboard</a></li>
        <li><a href="{{ route('student.record') }}" class="{{ request()->routeIs('student.record') ? 'active' : '' }}"><i class="bi bi-journal-text"></i>My Record</a></li>
        <li><a href="{{ route('student.announcements') }}" class="{{ request()->routeIs('student.announcements') ? 'active' : '' }}"><i class="bi bi-megaphone-fill"></i>Announcements</a></li>
        <li><a href="{{ route('student.notifications') }}" class="{{ request()->routeIs('student.notifications') ? 'active' : '' }}"><i class="bi bi-bell-fill"></i>Notifications</a></li>
    </ul>
</aside>