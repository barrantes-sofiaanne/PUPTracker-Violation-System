@extends('layouts.admin')

@section('title', 'Reports')

@php
    /*
     * ONE Reports page, three tabs — the existing processes are relocated, not
     * rewritten.
     *
     * The SERVER decides which tab is active, so:
     *   - a direct link / bookmark / redirect (?tab=ai-program) renders the right
     *     tab immediately (and still works with JavaScript disabled);
     *   - only the active tab's dataset is queried, so the other processes do not
     *     pay for work they are not showing.
     *
     * The tab triggers are real links carrying ?tab=..., with `data-bs-toggle`
     * layered on top so Bootstrap still switches them instantly in-page.
     */
    $activeReportsTab = $activeReportsTab ?? 'violation-reports';
@endphp

@section('content')

<div class="container-fluid">

    {{-- Single parent-level header for the whole Reports page. The standalone
         views' own headers were removed so nothing is duplicated. --}}
    <div class="page-header-modern mb-4">
        <h2 class="fw-bold mb-1">Reports</h2>
        <p class="mb-0">Manage and generate administrative reports.</p>
    </div>

    <div class="card shadow-sm">

        <div class="card-header bg-white">

            {{-- Same tab convention as Admin → Violations. --}}
            <ul class="nav nav-tabs" id="reportsTabs" role="tablist">

                <li class="nav-item" role="presentation">
                    <a
                        href="{{ route('admin.reports', array_merge(request()->except('tab'), ['tab' => 'violation-reports'])) }}"
                        class="nav-link {{ $activeReportsTab === 'violation-reports' ? 'active' : '' }}"
                        id="violation-reports-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#violation-reports"
                        role="tab"
                        aria-selected="{{ $activeReportsTab === 'violation-reports' ? 'true' : 'false' }}">
                        <i class="bi bi-file-earmark-bar-graph me-1"></i>
                        Violation Reports
                    </a>
                </li>

                <li class="nav-item" role="presentation">
                    <a
                        href="{{ route('admin.reports', array_merge(request()->except('tab'), ['tab' => 'graduation-ai'])) }}"
                        class="nav-link {{ $activeReportsTab === 'graduation-ai' ? 'active' : '' }}"
                        id="graduation-ai-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#graduation-ai"
                        role="tab"
                        aria-selected="{{ $activeReportsTab === 'graduation-ai' ? 'true' : 'false' }}">
                        <i class="bi bi-mortarboard-fill me-1"></i>
                        Graduation AI Report
                    </a>
                </li>

                <li class="nav-item" role="presentation">
                    <a
                        href="{{ route('admin.reports', ['tab' => 'history']) }}"
                        class="nav-link {{ $activeReportsTab === 'history' ? 'active' : '' }}"
                        id="history-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#history"
                        role="tab"
                        aria-selected="{{ $activeReportsTab === 'history' ? 'true' : 'false' }}">
                        <i class="bi bi-clock-history me-1"></i>
                        Report History
                    </a>
                </li>

            </ul>

        </div>

        <div class="card-body">

            <div class="tab-content">

                {{-- ALL THREE PANES ARE ALWAYS RENDERED.
                     Bootstrap's tab plugin only toggles `display:none`; it never
                     fetches content. If a pane were omitted unless it was the
                     active one, clicking that tab would reveal an EMPTY pane.
                     The controller still only QUERIES the active tab's dataset,
                     and each pane guards its own @include so a missing dataset
                     can never crash the page. --}}

                {{-- ===================================================== --}}
                {{-- Violation Reports — the original Reports page content --}}
                {{-- ===================================================== --}}
                <div
                    class="tab-pane fade {{ $activeReportsTab === 'violation-reports' ? 'show active' : '' }}"
                    id="violation-reports"
                    role="tabpanel"
                    aria-labelledby="violation-reports-tab">

                    @if(isset($reports))
                        @include('admin.reports.tabs.violation-reports')
                    @else
                        @include('admin.reports.tabs._placeholder')
                    @endif

                </div>

                {{-- ===================================================== --}}
                {{-- Graduation AI Report --}}
                {{-- ===================================================== --}}
                <div
                    class="tab-pane fade {{ $activeReportsTab === 'graduation-ai' ? 'show active' : '' }}"
                    id="graduation-ai"
                    role="tabpanel"
                    aria-labelledby="graduation-ai-tab">

                    @if(isset($screened))
                        @include('admin.reports.tabs.graduation-ai-report')
                    @else
                        @include('admin.reports.tabs._placeholder')
                    @endif

                </div>

                {{-- ===================================================== --}}
                {{-- Report History --}}
                {{-- ===================================================== --}}
                <div
                    class="tab-pane fade {{ $activeReportsTab === 'history' ? 'show active' : '' }}"
                    id="history"
                    role="tabpanel"
                    aria-labelledby="history-tab">

                    @if(isset($programReports))
                        {{-- `$reports` is bound explicitly because the page already uses
                             that name for the violation-report paginator, and the
                             history program list travels as `$historyPrograms` so it
                             cannot collide with the AI tab's `$programs`. Both keep
                             the moved markup byte-identical. --}}
                        @include('admin.reports.tabs.report-history', [
                            'reports' => $programReports,
                            'programs' => $historyPrograms,
                        ])
                    @else
                        @include('admin.reports.tabs._placeholder')
                    @endif

                </div>

            </div>

        </div>

    </div>

</div>

@endsection

@push('scripts')
<script>
/*
 * Each tab's dataset is loaded only when that tab is requested (?tab=...).
 * A tab whose data has not been fetched shows a placeholder; this wires its
 * "Load this tab" button to navigate to that tab's URL, which is the same
 * server-side path a normal tab click uses.
 *
 * Delegated on `document` because the placeholder is replaced whenever the
 * page re-renders, so a directly-bound listener would stop working.
 */
document.addEventListener('click', function (event) {
    const trigger = event.target.closest('[data-reports-load-tab]');
    if (!trigger) {
        return;
    }

    const pane = trigger.closest('.tab-pane');
    const link = pane ? document.querySelector('[data-bs-target="#' + pane.id + '"]') : null;

    if (link && link.getAttribute('href')) {
        event.preventDefault();
        window.location.assign(link.getAttribute('href'));
    }
});
</script>
@endpush

@if($activeReportsTab === 'violation-reports')
@push('scripts')
<script>
window.ReportRoutes = {
    filter: "{{ route('admin.reports.filter') }}",
    assistant: "{{ route('admin.reports.assistant') }}",
    pdf: "{{ route('admin.reports.export-pdf') }}"
};
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="{{ asset('js/admin/reports.js') }}"></script>
@endpush
@endif

@if($activeReportsTab === 'graduation-ai')
@include('admin.reports.tabs._graduation-ai-report-scripts')
@endif
