<aside class="sidebar" id="adminSidebar">

    <button type="button" class="btn btn-sm btn-light mobile-only mb-2" id="sidebarClose">
        <i class="bi bi-x-lg"></i>
    </button>

    <div class="sidebar-logo">
        <img
            src="{{ asset('assets/images/SystemLogo_w/name.png') }}"
            alt="PUPTracker logo"
            class="sidebar-logo-main">
        <img
            src="{{ asset('assets/images/PUPTracker_name_only_transparent_clean.png') }}"
            alt="PUPTracker"
            class="sidebar-logo-name">
        <small>Administrator</small>
    </div>

    @php
        $isItAdministrator = auth('admin')->check() && auth('admin')->user()->isItAdministrator();
    @endphp

    <ul class="sidebar-nav">

        @unless($isItAdministrator)
        <li class="sidebar-group">
            <button type="button" class="sidebar-group-toggle" data-sidebar-group="overview" aria-expanded="true">
                <span><i class="bi bi-grid"></i> Overview</span>
                <i class="bi bi-chevron-down sidebar-group-caret"></i>
            </button>
            <div class="sidebar-group-items" id="sidebar-group-overview">
                <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2"></i>
                    Dashboard
                </a>
            </div>
        </li>
        @endunless

        <li class="sidebar-group">
            <button type="button" class="sidebar-group-toggle" data-sidebar-group="accounts" aria-expanded="true">
                <span><i class="bi bi-people"></i> Accounts</span>
                <i class="bi bi-chevron-down sidebar-group-caret"></i>
            </button>
            <div class="sidebar-group-items" id="sidebar-group-accounts">
                <a href="{{ route('admin.students') }}" class="{{ request()->routeIs('admin.students*') ? 'active' : '' }}">
                    <i class="bi bi-people-fill"></i>
                    User Management
                </a>
            </div>
        </li>

        @unless($isItAdministrator)
        <li class="sidebar-group">
            <button type="button" class="sidebar-group-toggle" data-sidebar-group="discipline" aria-expanded="true">
                <span><i class="bi bi-shield-check"></i> Discipline</span>
                <i class="bi bi-chevron-down sidebar-group-caret"></i>
            </button>
            <div class="sidebar-group-items" id="sidebar-group-discipline">
                <a href="{{ route('admin.violations.index') }}" class="{{ request()->routeIs('admin.violations*') ? 'active' : '' }}">
                    <i class="bi bi-exclamation-octagon"></i>
                    Violations
                </a>
                <a href="{{ route('admin.sanctions.index') }}" class="{{ request()->routeIs('admin.sanctions*') || request()->routeIs('admin.disciplinary-sanctions*') ? 'active' : '' }}">
                    <i class="bi bi-shield-check"></i>
                    Sanctions
                </a>
            </div>
        </li>
        @endunless

        <li class="sidebar-group">
            <button type="button" class="sidebar-group-toggle" data-sidebar-group="communication" aria-expanded="true">
                <span><i class="bi bi-megaphone"></i> Communication</span>
                <i class="bi bi-chevron-down sidebar-group-caret"></i>
            </button>
            <div class="sidebar-group-items" id="sidebar-group-communication">
                <a href="{{ route('admin.announcements') }}" class="{{ request()->routeIs('admin.announcements*') ? 'active' : '' }}">
                    <i class="bi bi-megaphone-fill"></i>
                    Announcements
                </a>
                <a href="{{ route('admin.notifications') }}" class="{{ request()->routeIs('admin.notifications*') ? 'active' : '' }}">
                    <i class="bi bi-bell"></i>
                    Notifications
                </a>
            </div>
        </li>

        <li class="sidebar-group">
            <button type="button" class="sidebar-group-toggle" data-sidebar-group="reporting" aria-expanded="true">
                <span><i class="bi bi-file-earmark-bar-graph"></i> Reporting</span>
                <i class="bi bi-chevron-down sidebar-group-caret"></i>
            </button>
            <div class="sidebar-group-items" id="sidebar-group-reporting">
                <a href="{{ route('admin.reports') }}" class="{{ request()->routeIs('admin.reports*') ? 'active' : '' }}">
                    <i class="bi bi-file-earmark-text"></i>
                    Violation Reports
                </a>
                <a href="{{ route('admin.program-report.index') }}" class="{{ request()->routeIs('admin.program-report.index') ? 'active' : '' }}">
                    <i class="bi bi-file-earmark-bar-graph"></i>
                    Program Sanction Reports
                </a>
                <a href="{{ route('admin.program-report.history') }}" class="{{ request()->routeIs('admin.program-report.history') ? 'active' : '' }}">
                    <i class="bi bi-clock-history"></i>
                    Report History
                </a>
            </div>
        </li>

        <li class="sidebar-group">
            <button type="button" class="sidebar-group-toggle" data-sidebar-group="records" aria-expanded="true">
                <span><i class="bi bi-journal-text"></i> Records &amp; Security</span>
                <i class="bi bi-chevron-down sidebar-group-caret"></i>
            </button>
            <div class="sidebar-group-items" id="sidebar-group-records">
                <a href="{{ route('admin.audit-logs') }}" class="{{ request()->routeIs('admin.audit-logs*') ? 'active' : '' }}">
                    <i class="bi bi-journal-text"></i>
                    Audit Logs
                </a>
                <a href="{{ route('admin.user-management-history') }}" class="{{ request()->routeIs('admin.user-management-history*') ? 'active' : '' }}">
                    <i class="bi bi-person-lines-fill"></i>
                    User History
                </a>
            </div>
        </li>

        @if($isItAdministrator)
        <li class="sidebar-group">
            <button type="button" class="sidebar-group-toggle" data-sidebar-group="superadmin" aria-expanded="true">
                <span><i class="bi bi-shield-lock"></i> Super Admin</span>
                <i class="bi bi-chevron-down sidebar-group-caret"></i>
            </button>
            <div class="sidebar-group-items" id="sidebar-group-superadmin">
                <a href="{{ route('admin.super-admin.dashboard') }}" class="{{ request()->routeIs('admin.super-admin.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-shield-lock"></i>
                    Dashboard
                </a>
                <a href="{{ route('admin.super-admin.audit-trail') }}" class="{{ request()->routeIs('admin.super-admin.audit-trail') ? 'active' : '' }}">
                    <i class="bi bi-clipboard-data"></i>
                    Audit Trail
                </a>
                <a href="{{ route('admin.super-admin.maintenance') }}" class="{{ request()->routeIs('admin.super-admin.maintenance*') ? 'active' : '' }}">
                    <i class="bi bi-tools"></i>
                    Maintenance Config
                </a>
                <a href="{{ route('admin.super-admin.maintenance-records.index') }}" class="{{ request()->routeIs('admin.super-admin.maintenance-records*') ? 'active' : '' }}">
                    <i class="bi bi-calendar-check"></i>
                    Maintenance History
                </a>
                <a href="{{ route('admin.super-admin.email-incidents.index') }}" class="{{ request()->routeIs('admin.super-admin.email-incidents*') ? 'active' : '' }}">
                    <i class="bi bi-exclamation-octagon"></i>
                    System Logs
                </a>
                <a href="{{ route('admin.super-admin.audit-control-plan') }}" class="{{ request()->routeIs('admin.super-admin.audit-control-plan') ? 'active' : '' }}">
                    <i class="bi bi-shield-check"></i>
                    Audit &amp; Risk Plan
                </a>
            </div>
        </li>
        @endif

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