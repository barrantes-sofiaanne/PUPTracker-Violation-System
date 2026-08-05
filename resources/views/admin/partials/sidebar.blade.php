<aside class="sidebar" id="adminSidebar">

    <button type="button" class="btn btn-sm btn-light mobile-only mb-2" id="sidebarClose">
        <i class="bi bi-x-lg"></i>
    </button>

    <div class="sidebar-logo">

        <img
            src="{{ asset('assets/images/Tracker-logo.png') }}"
            width="60">

        <h5 class="mt-2">
            PUPTracker
        </h5>

        <small>
            Administrator
        </small>

    </div>

    @php
        $isItAdministrator = auth('admin')->check() && auth('admin')->user()->isItAdministrator();
    @endphp

    <ul>

        @unless($isItAdministrator)
        <li>

            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">

                <i class="bi bi-speedometer2"></i>

                Dashboard

            </a>

        </li>
        @endunless

        <li>

            <a href="{{ route('admin.students') }}" class="{{ request()->routeIs('admin.students*') ? 'active' : '' }}">

                <i class="bi bi-people"></i>

                User Management

            </a>

        </li>

        @unless($isItAdministrator)
        <li>

            <a href="{{ route('admin.violations.index') }}" class="{{ request()->routeIs('admin.violations*') ? 'active' : '' }}">

                <i class="bi bi-exclamation-octagon"></i>

                Violations

            </a>

        </li>
        @endunless

        @unless($isItAdministrator)
        <li>

            <a href="{{ route('admin.sanctions.index') }}" class="{{ request()->routeIs('admin.sanctions*') || request()->routeIs('admin.disciplinary-sanctions*') ? 'active' : '' }}">

                <i class="bi bi-shield-check"></i>

                Sanctions

            </a>

        </li>
        @endunless

        <li>

            <a href="{{ route('admin.announcements') }}" class="{{ request()->routeIs('admin.announcements*') ? 'active' : '' }}">

                <i class="bi bi-megaphone"></i>

                Announcements

            </a>

        </li>

        @unless($isItAdministrator)
        <li>

            <a href="{{ route('admin.reports') }}" class="{{ request()->routeIs('admin.reports*') ? 'active' : '' }}">

                <i class="bi bi-file-earmark-text"></i>

                Reports

            </a>

        </li>
        @endunless

        <li>

            <a href="{{ route('admin.audit-logs') }}" class="{{ request()->routeIs('admin.audit-logs*') ? 'active' : '' }}">

                <i class="bi bi-journal-text"></i>

                Audit Logs

            </a>

        </li>

        <li>

            <a href="{{ route('admin.user-management-history') }}" class="{{ request()->routeIs('admin.user-management-history*') ? 'active' : '' }}">

                <i class="bi bi-person-lines-fill"></i>

                User History

            </a>

        </li>

        <li>

            <a href="{{ route('admin.settings') }}" class="{{ request()->routeIs('admin.settings*') ? 'active' : '' }}">

                <i class="bi bi-gear"></i>

                Settings

            </a>

        </li>

        @if($isItAdministrator)
            <li class="mt-2 px-2 text-uppercase text-muted small">Super Admin</li>

            <li>
                <a href="{{ route('admin.super-admin.dashboard') }}" class="{{ request()->routeIs('admin.super-admin.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-shield-lock"></i>
                    Dashboard
                </a>
            </li>

            <li>
                <a href="{{ route('admin.super-admin.audit-trail') }}" class="{{ request()->routeIs('admin.super-admin.audit-trail') ? 'active' : '' }}">
                    <i class="bi bi-clipboard-data"></i>
                    Audit Trail
                </a>
            </li>

            <li>
                <a href="{{ route('admin.super-admin.maintenance') }}" class="{{ request()->routeIs('admin.super-admin.maintenance*') ? 'active' : '' }}">
                    <i class="bi bi-tools"></i>
                    Maintenance Config
                </a>
            </li>

            <li>
                <a href="{{ route('admin.super-admin.audit-control-plan') }}" class="{{ request()->routeIs('admin.super-admin.audit-control-plan') ? 'active' : '' }}">
                    <i class="bi bi-shield-check"></i>
                    Audit & Risk Plan
                </a>
            </li>
        @endif

    </ul>

</aside>