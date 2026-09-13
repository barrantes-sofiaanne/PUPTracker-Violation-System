{{--
    Security → Violations primary tabs.

    Exactly two tabs, in this order:

        1. Student Record        (default)
        2. Submitted Violations

    Both underlying pages render this same partial so the tab structure cannot
    drift, and each marks its own tab active via `$activeViolationsTab`.

    The Submitted Violations link carries the record-specific highlight query
    parameter through when the officer arrived from a notification, so the
    highlight survives the tab switch.
--}}
@php
    $activeViolationsTab = $activeViolationsTab ?? 'students';

    $submittedParams = [];
    if ((int) request()->integer('highlight_violation') > 0) {
        $submittedParams['highlight_violation'] = (int) request()->integer('highlight_violation');
    }
@endphp

<ul class="nav nav-tabs mb-4" id="securityViolationsTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <a
            href="{{ route('security.violations.students') }}"
            class="nav-link {{ $activeViolationsTab === 'students' ? 'active' : '' }}"
            id="studentRecordTab"
            role="tab"
            aria-controls="studentRecord"
            aria-selected="{{ $activeViolationsTab === 'students' ? 'true' : 'false' }}">
            <i class="bi bi-person-lines-fill me-1" aria-hidden="true"></i>Student Record
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a
            href="{{ route('security.violations.submitted', $submittedParams) }}"
            class="nav-link {{ $activeViolationsTab === 'submitted' ? 'active' : '' }}"
            id="submittedViolationsTab"
            role="tab"
            aria-controls="submittedViolations"
            aria-selected="{{ $activeViolationsTab === 'submitted' ? 'true' : 'false' }}">
            <i class="bi bi-send-fill me-1" aria-hidden="true"></i>Submitted Violations
        </a>
    </li>
</ul>
