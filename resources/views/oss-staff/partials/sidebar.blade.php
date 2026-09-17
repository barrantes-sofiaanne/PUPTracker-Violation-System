<aside class="portal-sidebar" id="portalSidebar">
    {{-- Mobile-only sidebar close (×). See `.sidebar-close` in admin.css. --}}
    <button type="button" class="sidebar-close" id="portalSidebarClose" aria-label="Close sidebar">
        <i class="bi bi-x-lg" aria-hidden="true"></i>
    </button>

    <div class="portal-logo-wrap">
        <img src="{{ asset('assets/images/SystemLogo_w/name.png') }}" alt="PUPTracker logo" class="portal-logo">
        <h5>PUPTracker</h5>
        <small>OSS Staff Module</small>
    </div>

    <ul class="portal-nav-list">
        <li><a href="{{ route('oss-staff.dashboard') }}" class="{{ request()->routeIs('oss-staff.dashboard') ? 'active' : '' }}"><i class="bi bi-speedometer2"></i>Dashboard</a></li>
        <li><a href="{{ route('oss-staff.violations.students') }}" class="{{ request()->routeIs('oss-staff.violations.students') || request()->routeIs('oss-staff.violations.submitted') || request()->routeIs('oss-staff.violations.show') ? 'active' : '' }}"><i class="bi bi-exclamation-triangle-fill"></i>Violations</a></li>
        <li><a href="{{ route('oss-staff.sanction-requests') }}" class="{{ request()->routeIs('oss-staff.sanction-requests*') ? 'active' : '' }}"><i class="bi bi-clipboard-check-fill"></i>Sanction Requests</a></li>
        <li><a href="{{ route('oss-staff.announcements') }}" class="{{ request()->routeIs('oss-staff.announcements') ? 'active' : '' }}"><i class="bi bi-megaphone-fill"></i>Announcements</a></li>
        <li><a href="{{ route('oss-staff.notifications') }}" class="{{ request()->routeIs('oss-staff.notifications') ? 'active' : '' }}"><i class="bi bi-bell-fill"></i>Notifications</a></li>
        <li><a href="{{ route('oss-staff.profile') }}" class="{{ request()->routeIs('oss-staff.profile') ? 'active' : '' }}"><i class="bi bi-person-vcard-fill"></i>Profile</a></li>
    </ul>
</aside>
