@extends('layouts.admin')

@section('title', 'AI Program Report')

@section('content')

<div class="container-fluid">

    <div class="page-header-modern d-flex justify-content-between align-items-center flex-wrap mb-4">

        <div>
            <h2 class="fw-bold mb-1">
                AI Program Report
            </h2>
            <p class="mb-0">
                Build the graduating cohort — automatically by year level, or by adding specific students — then generate the report.
            </p>
        </div>

    </div>

    {{--
        Single source of truth.

        The cohort builder (automatic year-level eligibility, manual student
        search/add, ladderized filter and the graduating preview) lives in the
        Reports tab partial so this page and
        Admin → Reports → Graduation AI Report can never diverge.

        The page-level <h2> header above is intentionally NOT part of the partial:
        the tabbed Reports page supplies its own single header.
    --}}
    @include('admin.reports.tabs.ai-program-report')

</div>

@endsection

@include('admin.reports.tabs._ai-program-report-scripts')
