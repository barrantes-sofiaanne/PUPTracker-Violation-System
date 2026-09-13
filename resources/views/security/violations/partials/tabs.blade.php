{{--
    Security → Violations primary tabs.

    Exactly two tabs, in this order:

        1. Student Record        (default)
        2. Submitted Violations

    Both panels live on the SAME page (`security.violations.students`) and are
    switched client-side by Bootstrap, so there is no page reload and no second
    URL. The former standalone "Submitted Violations" page has been removed.

    The Submitted Violations tab is a real link carrying `?tab=submitted`, so it
    still works without JavaScript (and keeps `status` / `search` /
    `highlight_violation` when present) and so notification deep links land on
    the tracker. With JavaScript, Bootstrap's tab plugin switches the pane in
    place.
--}}
@php
    $activeViolationsTab = $activeViolationsTab ?? 'students';

    // Carry the current Submitted Violations filters through the tab link, plus
    // any notification highlight the officer arrived with.
    $submittedParams = ['tab' => 'submitted'];
    foreach (['status', 'submitted_search'] as $preserved) {
        if (request()->filled($preserved)) {
            $submittedParams[$preserved] = request()->input($preserved);
        }
    }
    if ((int) request()->integer('highlight_violation') > 0) {
        $submittedParams['highlight_violation'] = (int) request()->integer('highlight_violation');
    }
@endphp

<ul class="nav nav-tabs violations-tabs mb-4" id="securityViolationsTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <a
            href="{{ route('security.violations.students') }}"
            class="nav-link {{ $activeViolationsTab === 'students' ? 'active' : '' }}"
            id="studentRecordTab"
            data-bs-toggle="tab"
            data-bs-target="#studentRecord"
            role="tab"
            aria-controls="studentRecord"
            aria-selected="{{ $activeViolationsTab === 'students' ? 'true' : 'false' }}">
            <i class="bi bi-person-lines-fill me-1" aria-hidden="true"></i>Student Record
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a
            href="{{ route('security.violations.students', $submittedParams) }}"
            class="nav-link {{ $activeViolationsTab === 'submitted' ? 'active' : '' }}"
            id="submittedViolationsTab"
            data-bs-toggle="tab"
            data-bs-target="#submittedViolations"
            role="tab"
            aria-controls="submittedViolations"
            aria-selected="{{ $activeViolationsTab === 'submitted' ? 'true' : 'false' }}">
            <i class="bi bi-send-fill me-1" aria-hidden="true"></i>Submitted Violations
        </a>
    </li>
</ul>
