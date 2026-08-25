<aside class="portal-sidebar" id="portalSidebar">
    <button type="button" class="btn btn-sm btn-light d-lg-none mb-2" id="portalSidebarClose">
        <i class="bi bi-x-lg"></i>
    </button>

    <div class="portal-logo-wrap">
        <img src="{{ asset('assets/images/SystemLogo_w/name.png') }}" alt="PUPTracker logo" class="portal-logo-main">
        <img src="{{ asset('assets/images/PUPTracker_name_only_transparent_clean.png') }}" alt="PUPTracker" class="portal-logo-name">
        <small>Student Module</small>
    </div>

    <ul class="portal-nav-list">
        <li class="sidebar-group">
            <button type="button" class="sidebar-group-toggle" data-sidebar-group="student-overview" aria-expanded="true">
                <span><i class="bi bi-grid"></i> Overview</span>
                <i class="bi bi-chevron-down sidebar-group-caret"></i>
            </button>
            <div class="sidebar-group-items" id="sidebar-group-student-overview">
                <a href="{{ route('student.dashboard') }}" class="{{ request()->routeIs('student.dashboard') ? 'active' : '' }}"><i class="bi bi-house-door-fill"></i>Dashboard</a>
            </div>
        </li>
        <li class="sidebar-group">
            <button type="button" class="sidebar-group-toggle" data-sidebar-group="student-record" aria-expanded="true">
                <span><i class="bi bi-journal-text"></i> My Record</span>
                <i class="bi bi-chevron-down sidebar-group-caret"></i>
            </button>
            <div class="sidebar-group-items" id="sidebar-group-student-record">
                <a href="{{ route('student.record') }}" class="{{ request()->routeIs('student.record') ? 'active' : '' }}"><i class="bi bi-journal-text"></i>My Record</a>
            </div>
        </li>
        <li class="sidebar-group">
            <button type="button" class="sidebar-group-toggle" data-sidebar-group="student-communication" aria-expanded="true">
                <span><i class="bi bi-megaphone"></i> Communication</span>
                <i class="bi bi-chevron-down sidebar-group-caret"></i>
            </button>
            <div class="sidebar-group-items" id="sidebar-group-student-communication">
                <a href="{{ route('student.announcements') }}" class="{{ request()->routeIs('student.announcements') ? 'active' : '' }}"><i class="bi bi-megaphone-fill"></i>Announcements</a>
                <a href="{{ route('student.notifications') }}" class="{{ request()->routeIs('student.notifications') ? 'active' : '' }}"><i class="bi bi-bell-fill"></i>Notifications</a>
            </div>
        </li>
    </ul>
</aside>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.sidebar-group-toggle').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            const id = toggle.dataset.sidebarGroup;
            const panel = document.getElementById('sidebar-group-' + id);
            const expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!expanded));
            toggle.classList.toggle('collapsed', expanded);
            if (panel) {
                panel.classList.toggle('open', !expanded);
            }
        });
    });
});
</script>
@endpush