@extends('layouts.admin')

@section('title', 'AI-Assisted Graduation Disciplinary Review')

@section('content')

<div class="container-fluid">

    {{--
        Single source of truth.

        The screening dashboard (batch actions, progress, filters, summary
        counters and the student table) lives in the Reports tab partial so the
        standalone page and Admin -> Reports -> Graduation AI Report can never
        diverge. Extracting this body into a second copy once already dropped the
        Generate/Retry controls from the tab.

        The page-level <h2> header above is intentionally NOT part of the
        partial: the tabbed Reports page supplies its own single header.
    --}}
    @include('admin.reports.tabs.graduation-ai-report')

</div>


@endsection

@include('admin.reports.tabs._graduation-ai-report-scripts')
