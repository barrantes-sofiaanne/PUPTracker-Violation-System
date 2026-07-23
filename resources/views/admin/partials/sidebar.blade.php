<aside class="sidebar">

    <div class="sidebar-logo">

        <img
            src="{{ asset('assets/images/PUP_logo.png') }}"
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

            <a href="{{ route('admin.dashboard') }}">

                <i class="bi bi-speedometer2"></i>

                Dashboard

            </a>

        </li>

        <li>

            <a href="{{ route('admin.students') }}">

                <i class="bi bi-people"></i>

                Students

            </a>

        </li>

        <li>

            <a href="{{ route('admin.violations.index') }}">

                <i class="bi bi-exclamation-octagon"></i>

                Violations

            </a>

        </li>

        <li>

            <a href="{{ route('admin.announcements') }}">

                <i class="bi bi-megaphone"></i>

                Announcements

            </a>

        </li>

        <li>

            <a href="{{ route('admin.reports') }}">

                <i class="bi bi-file-earmark-text"></i>

                Reports

            </a>

        </li>

        <li>

            <a href="{{ route('admin.audit-logs') }}">

                <i class="bi bi-journal-text"></i>

                Audit Logs

            </a>

        </li>

        <li>

            <a href="{{ route('admin.user-management-history') }}">

                <i class="bi bi-person-lines-fill"></i>

                User History

            </a>

        </li>

        <li>

            <a href="{{ route('admin.settings') }}">

                <i class="bi bi-gear"></i>

                Settings

            </a>

        </li>

    </ul>

</aside>