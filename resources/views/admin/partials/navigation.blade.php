<div class="mb-4">
    <ul class="nav nav-pills violation-nav">

        <li class="nav-item">
            <a
                href="{{ route('admin.violations.index') }}"
                class="nav-link {{ request()->routeIs('admin.violations.index') ? 'active' : '' }}">

                <i class="bi bi-people-fill me-2"></i>

                Management

            </a>
        </li>

        <li class="nav-item">
            <a
                href="{{ route('admin.violations.configuration') }}"
                class="nav-link {{ request()->routeIs('admin.violations.configuration*') ? 'active' : '' }}">

                <i class="bi bi-gear-fill me-2"></i>

                Configuration

            </a>
        </li>

        <li class="nav-item">
            <a
                href="{{ route('admin.violations.history') }}"
                class="nav-link {{ request()->routeIs('admin.violations.history') ? 'active' : '' }}">

                <i class="bi bi-clock-history me-2"></i>

                History

            </a>
        </li>

    </ul>
</div>