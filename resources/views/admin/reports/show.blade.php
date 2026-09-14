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
                        href="{{ route('admin.reports', array_merge(request()->except('tab'), ['tab' => 'ai-program'])) }}"
                        class="nav-link {{ $activeReportsTab === 'ai-program' ? 'active' : '' }}"
                        id="ai-program-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#ai-program"
                        role="tab"
                        aria-selected="{{ $activeReportsTab === 'ai-program' ? 'true' : 'false' }}">
                        <i class="bi bi-stars me-1"></i>
                        AI Program Report
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

                {{-- ===================================================== --}}
                {{-- Violation Reports — the original Reports page content --}}
                {{-- ===================================================== --}}
                <div
                    class="tab-pane fade {{ $activeReportsTab === 'violation-reports' ? 'show active' : '' }}"
                    id="violation-reports"
                    role="tabpanel"
                    aria-labelledby="violation-reports-tab">

                    @if($activeReportsTab === 'violation-reports')
                        @include('admin.reports.tabs.violation-reports')
                    @endif

                </div>

                {{-- ===================================================== --}}
                {{-- AI Program Report --}}
                {{-- ===================================================== --}}
                <div
                    class="tab-pane fade {{ $activeReportsTab === 'ai-program' ? 'show active' : '' }}"
                    id="ai-program"
                    role="tabpanel"
                    aria-labelledby="ai-program-tab">

                    @if($activeReportsTab === 'ai-program')
                        @include('admin.reports.tabs.ai-program-report')
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

                    @if($activeReportsTab === 'history')
                        {{-- The page already uses `$reports` for the violation-report
                             paginator, so the history dataset travels as
                             `$programReports` and is handed to the partial as
                             `$reports` — keeping the moved markup unchanged. --}}
                        @include('admin.reports.tabs.report-history', ['reports' => $programReports])
                    @endif

                </div>

            </div>

        </div>

    </div>

</div>

@endsection

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

@if($activeReportsTab === 'ai-program')
@include('admin.reports.tabs._ai-program-report-scripts')
@endif
