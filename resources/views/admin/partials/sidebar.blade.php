<aside class="sidebar" id="adminSidebar">

    {{-- Mobile-only sidebar close (×). Uses `.sidebar-close` rather than Bootstrap's
         light button variant, which painted a solid white pill over the maroon
         sidebar gradient. --}}
    <button type="button" class="sidebar-close" id="sidebarClose" aria-label="Close sidebar">
        <i class="bi bi-x-lg" aria-hidden="true"></i>
    </button>

    @php
        $isItAdministrator = auth('admin')->check() && auth('admin')->user()->isItAdministrator();
        $sidebarRoleLabel = $isItAdministrator ? 'IT Administrator' : 'Administrator';
    @endphp

    <div class="sidebar-logo">

        <img
            src="{{ asset('assets/images/SystemLogo_w/name.png') }}"
            alt="PUPTracker"
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

            <a href="{{ route('admin.sanctions.index') }}" class="{{ request()->routeIs('admin.sanctions*') || request()->routeIs('admin.disciplinary-sanctions*') ? 'active' : '' }}">

                <i class="bi bi-shield-check"></i>

                Sanctions

            </a>

        </li>

        @php
            $pendingCorrectionRequestCount = 0;
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('violation_correction_requests')
                    && \Illuminate\Support\Facades\Schema::hasColumn('violation_correction_requests', 'status')) {
                    $pendingCorrectionRequestCount = \App\Models\ViolationCorrectionRequest::where('status', 'pending')->count();
                }
            } catch (\Throwable $e) {
                $pendingCorrectionRequestCount = 0;
            }
        @endphp

        <li>

            <a href="{{ route('violation-correction-requests') }}" class="{{ request()->routeIs('violation-correction-requests*') ? 'active' : '' }}">

                <i class="bi bi-pencil-square"></i>

                Violation Change Requests

                @if($pendingCorrectionRequestCount > 0)
                    <span class="sidebar-badge ms-auto" title="{{ $pendingCorrectionRequestCount }} correction request(s) awaiting review">{{ $pendingCorrectionRequestCount > 9 ? '9+' : $pendingCorrectionRequestCount }}</span>
                @endif

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
            {{--
                SUPER ADMIN NAVIGATION — mapped onto EXISTING pages. No new table,
                controller or route was created for this sidebar.

                  Dashboard           -> admin.super-admin.dashboard
                  Backup Management   -> admin.super-admin.backups.index (relabelled)
                  Settings            -> admin.super-admin.maintenance (relabelled)
                  Audit Trail         -> the shared "Audit Logs" entry below
                  User Administration -> the shared "User Management" entry above
                  System Health       -> System Logs (below) + Backup Management

                "Audit Logs" and "User Management" are SHARED with regular admins,
                so they keep their existing labels — relabelling them would rename
                the same entry for the other role too.
            --}}
            <li>
                <a href="{{ route('admin.super-admin.backups.index') }}" class="{{ request()->routeIs('admin.super-admin.backups*') ? 'active' : '' }}">
                    <i class="bi bi-database-check"></i>
                    Backup Management
                </a>
            </li>
        @endif

        @if($isItAdministrator)
            <li>
                <a href="{{ route('admin.super-admin.evidence-security-events') }}" class="{{ request()->routeIs('admin.super-admin.evidence-security-events') ? 'active' : '' }}">
                    <i class="bi bi-camera-video"></i>
                    Evidence Security Events
                </a>
            </li>
        @endif

        @if($isItAdministrator)
            {{--
                System Logs is KEPT as its own entry.

                PART 2 permits folding it into the Audit Trail only "if it can be
                properly integrated" — it cannot. System Logs reads the Laravel
                application log FILE; Audit Logs reads the audit_logs TABLE. Two
                different data sources, so merging them would drop data.

                NOT LISTED HERE (removed from the navigation by PART 2):
                  * Email Incidents — the page, route and controller are RETAINED
                    because it is the DESTINATION for system-level email/OTP
                    failure alerts: `system_email_failure` notifications resolve
                    their URL to admin.super-admin.email-incidents.show. Hiding the
                    nav entry keeps the sidebar focused without dead-ending those
                    alerts; the page stays reachable at
                    /admin/super-admin/email-incidents.
                  * Audit & Risk Management — the risk register is not redundant
                    with anything, so its page/route are retained and stay
                    reachable at /admin/super-admin/audit-control-plan.
            --}}
            <li>
                <a href="{{ route('admin.super-admin.system-logs') }}" class="{{ request()->routeIs('admin.super-admin.system-logs') ? 'active' : '' }}">
                    <i class="bi bi-terminal"></i>
                    System Logs
                </a>
            </li>
        @endif

        @if($isItAdministrator)
        <li class="sidebar-section-label">System Maintenance</li>

        <li>
            <a href="{{ route('admin.super-admin.maintenance') }}" class="{{ request()->routeIs('admin.super-admin.maintenance') ? 'active' : '' }}">
                <i class="bi bi-tools"></i>
                Settings
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