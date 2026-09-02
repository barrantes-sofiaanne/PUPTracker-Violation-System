<aside class="sidebar" id="adminSidebar">

    <button type="button" class="btn btn-sm btn-light mobile-only mb-2" id="sidebarClose">
        <i class="bi bi-x-lg"></i>
    </button>

    @php
        $isItAdministrator = auth('admin')->check() && auth('admin')->user()->isItAdministrator();
        $sidebarRoleLabel = $isItAdministrator ? 'IT Administrator' : 'Administrator';
    @endphp

    <div class="sidebar-logo">

        <img
            src="{{ asset('assets/images/SystemLogo_w/name.png') }}"
            width="60">

        <h5 class="mt-2">
            PUPTracker
        </h5>

        <small>
            {{ $sidebarRoleLabel }}
        </small>

    </div>

    <ul>

        <li class="sidebar-section-label">Overview</li>

        @if($isItAdministrator)
        <li>

            <a href="{{ route('admin.super-admin.dashboard') }}" class="{{ request()->routeIs('admin.super-admin.dashboard') ? 'active' : '' }}">

                <i class="bi bi-shield-lock"></i>

                Dashboard

            </a>

        </li>
        @else
        <li>

            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">

                <i class="bi bi-speedometer2"></i>

                Dashboard

            </a>

        </li>
        @endif

        <li class="sidebar-section-label">Accounts</li>

        <li>

            <a href="{{ route('admin.students') }}" class="{{ request()->routeIs('admin.students*') ? 'active' : '' }}">

                <i class="bi bi-people"></i>

                User Management

            </a>

        </li>

        @unless($isItAdministrator)
        <li class="sidebar-section-label">Discipline</li>

        @php
            $pendingViolationCount = 0;
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('violation_tbl')
                    && \Illuminate\Support\Facades\Schema::hasColumn('violation_tbl', 'status')) {
                    $pendingViolationCount = \App\Models\Violation::where('status', 'pending')->count();
                }
            } catch (\Throwable $e) {
                $pendingViolationCount = 0;
            }
        @endphp

        <li>

            <a href="{{ route('admin.violations.index') }}" class="{{ request()->routeIs('admin.violations*') ? 'active' : '' }}">

                <i class="bi bi-exclamation-octagon"></i>

                Violations

                @if($pendingViolationCount > 0)
                    <span class="sidebar-badge ms-auto" title="{{ $pendingViolationCount }} violation(s) awaiting confirmation">{{ $pendingViolationCount > 9 ? '9+' : $pendingViolationCount }}</span>
                @endif

            </a>

        </li>

        <li>

            <a href="{{ route('admin.violations.confirmations') }}" class="{{ request()->routeIs('admin.violations.confirmations') ? 'active' : '' }}">

                <i class="bi bi-shield-exclamation"></i>

                Confirmations

                @if($pendingViolationCount > 0)
                    <span class="sidebar-badge ms-auto" title="{{ $pendingViolationCount }} violation(s) awaiting confirmation">{{ $pendingViolationCount > 9 ? '9+' : $pendingViolationCount }}</span>
                @endif

            </a>

        </li>

        <li>

            <a href="{{ route('admin.sanctions.index') }}" class="{{ request()->routeIs('admin.sanctions*') || request()->routeIs('admin.disciplinary-sanctions*') ? 'active' : '' }}">

                <i class="bi bi-shield-check"></i>

                Sanctions

            </a>

        </li>
        @endunless

        <li class="sidebar-section-label">Communication</li>

        <li>

            <a href="{{ route('admin.announcements') }}" class="{{ request()->routeIs('admin.announcements*') ? 'active' : '' }}">

                <i class="bi bi-megaphone"></i>

                Announcements

            </a>

        </li>

        <li>

            <a href="{{ route('admin.notifications') }}" class="{{ request()->routeIs('admin.notifications*') ? 'active' : '' }}">

                <i class="bi bi-bell"></i>

                Notifications

            </a>

        </li>

        @unless($isItAdministrator)
        <li class="sidebar-section-label">Reporting</li>

        <li>

            <a href="{{ route('admin.reports') }}" class="{{ request()->routeIs('admin.reports*') ? 'active' : '' }}">

                <i class="bi bi-file-earmark-bar-graph"></i>

                Reports

            </a>

        </li>

        <li>

            <a href="{{ route('admin.program-report.index') }}" class="{{ request()->routeIs('admin.program-report.index') ? 'active' : '' }}">

                <i class="bi bi-stars"></i>

                AI Program Report

            </a>

        </li>

        <li>

            <a href="{{ route('admin.program-report.history') }}" class="{{ request()->routeIs('admin.program-report.history') ? 'active' : '' }}">

                <i class="bi bi-clock-history"></i>

                Report History

            </a>

        </li>
        @endunless

        <li class="sidebar-section-label">Records & Security</li>

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

        @if($isItAdministrator)
            <li>
                <a href="{{ route('admin.super-admin.backups.index') }}" class="{{ request()->routeIs('admin.super-admin.backups*') ? 'active' : '' }}">
                    <i class="bi bi-database-check"></i>
                    Backup History
                </a>
            </li>
        @endif

        @if($isItAdministrator)
            <li>
                <a href="{{ route('admin.super-admin.email-incidents.index') }}" class="{{ request()->routeIs('admin.super-admin.email-incidents*') ? 'active' : '' }}">
                    <i class="bi bi-exclamation-octagon"></i>
                    System Logs
                </a>
            </li>

            <li>
                <a href="{{ route('admin.super-admin.audit-control-plan') }}" class="{{ request()->routeIs('admin.super-admin.audit-control-plan') ? 'active' : '' }}">
                    <i class="bi bi-shield-check"></i>
                    Audit &amp; Risk Management
                </a>
            </li>
        @endif

        @if($isItAdministrator)
        <li class="sidebar-section-label">System Maintenance</li>

        <li>
            <a href="{{ route('admin.super-admin.maintenance') }}" class="{{ request()->routeIs('admin.super-admin.maintenance') ? 'active' : '' }}">
                <i class="bi bi-tools"></i>
                Maintenance Settings
            </a>
        </li>

        <li>
            <a href="{{ route('admin.super-admin.maintenance-records.index') }}" class="{{ request()->routeIs('admin.super-admin.maintenance-records*') ? 'active' : '' }}">
                <i class="bi bi-calendar-check"></i>
                Maintenance History
            </a>
        </li>
        @endif

    </ul>

</aside>