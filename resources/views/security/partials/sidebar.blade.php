<aside class="portal-sidebar" id="portalSidebar">
    <button type="button" class="btn btn-sm btn-light d-lg-none mb-2" id="portalSidebarClose">
        <i class="bi bi-x-lg"></i>
    </button>

    <div class="portal-logo-wrap">
        <img src="{{ asset('assets/images/SystemLogo_w/name.png') }}" alt="PUPTracker logo" class="portal-logo-main">
        <img src="{{ asset('assets/images/PUPTracker_name_only_transparent_clean.png') }}" alt="PUPTracker" class="portal-logo-name">
        <small>Security Module</small>
    </div>

    <ul class="portal-nav-list">
        <li class="sidebar-group">
            <button type="button" class="sidebar-group-toggle" data-sidebar-group="security-overview" aria-expanded="true">
                <span><i class="bi bi-grid"></i> Overview</span>
                <i class="bi bi-chevron-down sidebar-group-caret"></i>
            </button>
            <div class="sidebar-group-items" id="sidebar-group-security-overview">
                <a href="{{ route('security.dashboard') }}" class="{{ request()->routeIs('security.dashboard') ? 'active' : '' }}"><i class="bi bi-shield-lock-fill"></i>Dashboard</a>
            </div>
        </li>
        <li class="sidebar-group">
            <button type="button" class="sidebar-group-toggle" data-sidebar-group="security-discipline" aria-expanded="true">
                <span><i class="bi bi-exclamation-triangle"></i> Discipline</span>
                <i class="bi bi-chevron-down sidebar-group-caret"></i>
            </button>
            <div class="sidebar-group-items" id="sidebar-group-security-discipline">
                <a href="{{ route('security.violations.students') }}" class="{{ request()->routeIs('security.violations.*') ? 'active' : '' }}"><i class="bi bi-exclamation-triangle-fill"></i>Violations</a>
            </div>
        </li>
        <li class="sidebar-group">
            <button type="button" class="sidebar-group-toggle" data-sidebar-group="security-notifications" aria-expanded="true">
                <span><i class="bi bi-bell"></i> Notifications</span>
                <i class="bi bi-chevron-down sidebar-group-caret"></i>
            </button>
            <div class="sidebar-group-items" id="sidebar-group-security-notifications">
                <a href="{{ route('security.notifications') }}" class="{{ request()->routeIs('security.notifications') ? 'active' : '' }}"><i class="bi bi-bell-fill"></i>Notifications</a>
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