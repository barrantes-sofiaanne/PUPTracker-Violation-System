<aside class="portal-sidebar" id="portalSidebar">
    <button type="button" class="btn btn-sm btn-light d-lg-none mb-2" id="portalSidebarClose">
        <i class="bi bi-x-lg"></i>
    </button>

    <div class="portal-logo-wrap">
        <img src="{{ asset('assets/images/Tracker-logo.png') }}" alt="Tracker logo" class="portal-logo">
        <h5>PUPTracker</h5>
        <small>Student Module</small>
    </div>

    <ul class="portal-nav-list">
        <li><a href="{{ route('student.dashboard') }}" class="{{ request()->routeIs('student.dashboard') ? 'active' : '' }}"><i class="bi bi-house-door-fill"></i>Dashboard</a></li>
        <li><a href="{{ route('student.record') }}" class="{{ request()->routeIs('student.record') ? 'active' : '' }}"><i class="bi bi-journal-text"></i>My Record</a></li>
        <li><a href="{{ route('student.announcements') }}" class="{{ request()->routeIs('student.announcements') ? 'active' : '' }}"><i class="bi bi-megaphone-fill"></i>Announcements</a></li>
        <li><a href="{{ route('student.notifications') }}" class="{{ request()->routeIs('student.notifications') ? 'active' : '' }}"><i class="bi bi-bell-fill"></i>Notifications</a></li>
        <li><a href="{{ route('student.profile') }}" class="{{ request()->routeIs('student.profile') ? 'active' : '' }}"><i class="bi bi-person-vcard-fill"></i>Profile</a></li>
        <li><a href="{{ route('student.settings') }}" class="{{ request()->routeIs('student.settings') ? 'active' : '' }}"><i class="bi bi-gear-fill"></i>Settings</a></li>
    </ul>

    <form action="{{ route('logout') }}" method="POST" class="portal-logout-form">
        @csrf
        <button type="submit" class="btn btn-outline-light w-100">
            <i class="bi bi-box-arrow-right me-2"></i>Logout
        </button>
    </form>
</aside>