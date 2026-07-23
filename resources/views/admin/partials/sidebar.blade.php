<aside class="sidebar">

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

    <ul>

        <li>

            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">

                <i class="bi bi-speedometer2"></i>

                Dashboard

            </a>

        </li>

        <li>

            <a href="{{ route('admin.students') }}" class="{{ request()->routeIs('admin.students*') ? 'active' : '' }}">

                <i class="bi bi-people"></i>

                Students

            </a>

        </li>

        <li>

            <a href="{{ route('admin.violations.index') }}" class="{{ request()->routeIs('admin.violations*') ? 'active' : '' }}">

                <i class="bi bi-exclamation-octagon"></i>

                Violations

            </a>

        </li>

        <li>

            <a href="{{ route('admin.announcements') }}" class="{{ request()->routeIs('admin.announcements*') ? 'active' : '' }}">

                <i class="bi bi-megaphone"></i>

                Announcements

            </a>

        </li>

        <li>

            <a href="{{ route('admin.reports') }}" class="{{ request()->routeIs('admin.reports*') ? 'active' : '' }}">

                <i class="bi bi-file-earmark-text"></i>

                Reports

            </a>

        </li>

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

    </ul>

</aside>