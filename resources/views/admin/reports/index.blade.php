@extends('layouts.admin')

@section('title', 'Violation Reports')

@php
    $selectedCourse = request('course');
    $selectedYear = request('year');
    $selectedCategory = request('category');
    $selectedViolationType = request('violation_type');
@endphp

@section('content')

<div class="container-fluid">

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-4">

        <div>

            <h2 class="fw-bold mb-1">

                Violation Reports

            </h2>

            <p class="text-muted mb-0">

                Generate, analyze, and export student disciplinary reports.

            </p>

        </div>

        <div>

            <button
                class="btn btn-outline-primary me-2"
                id="printReport">

                <i class="bi bi-printer"></i>

                Print

            </button>

            <button
                class="btn btn-success me-2"
                id="exportExcel">

                <i class="bi bi-file-earmark-excel"></i>

                Excel

            </button>

            <button
                class="btn btn-danger"
                id="exportPdf">

                <i class="bi bi-file-earmark-pdf"></i>

                PDF

            </button>

        </div>

    </div>
    <div class="row mb-4">

    <div class="col-lg-3 col-md-6 mb-3">

        <div class="card shadow-sm border-0">

            <div class="card-body">

                <small class="text-muted">

                    Total Violations

                </small>

                <h2
                    class="fw-bold text-danger"
                    id="totalViolations">

                    {{ $statistics['total'] }}

                </h2>

            </div>

        </div>

    </div>

    <div class="col-lg-3 col-md-6 mb-3">

        <div class="card shadow-sm border-0">

            <div class="card-body">

                <small class="text-muted">

                    Minor Violations

                </small>

                <h2
                    class="fw-bold text-warning"
                    id="minorViolations">

                    {{ $statistics['minor'] }}

                </h2>

            </div>

        </div>

    </div>

    <div class="col-lg-3 col-md-6 mb-3">

        <div class="card shadow-sm border-0">

            <div class="card-body">

                <small class="text-muted">

                    Major Violations

                </small>

                <h2
                    class="fw-bold text-danger"
                    id="majorViolations">

                    {{ $statistics['major'] }}

                </h2>

            </div>

        </div>

    </div>

    <div class="col-lg-3 col-md-6 mb-3">

        <div class="card shadow-sm border-0">

            <div class="card-body">

                <small class="text-muted">

                    Repeat Offenders

                </small>

                <h2
                    class="fw-bold"
                    id="repeatOffenders">

                    {{ $statistics['repeat_offenders'] }}

                </h2>

            </div>

        </div>

    </div>

</div>  
<div class="card border-0 shadow-sm mb-4">

    <div class="card-header bg-white">

        <h5 class="fw-bold mb-0">

            Report Filters

        </h5>

    </div>

    <div class="card-body">

        <form method="GET" action="{{ route('admin.reports') }}" id="reportFilterForm">

            <div class="row">

                <div class="col-lg-3 mb-3">

                    <label class="form-label">

                        Course

                    </label>

                    <select
                        class="form-select"
                        name="course"
                        id="course">

                        <option value="">

                            All Courses

                        </option>

                        @foreach($courses as $course)
                        <option value="{{ $course->program_id ?? $course->id }}" {{ (string) $selectedCourse === (string) ($course->program_id ?? $course->id) ? 'selected' : '' }}>
                            {{ $course->course_name }}
                        </option>
                        @endforeach

                    </select>

                </div>

                <div class="col-lg-3 mb-3">

                    <label class="form-label">

                        Year

                    </label>

                    <select
                        class="form-select"
                        name="year"
                        id="year">

                        <option value="">

                            All Years

                        </option>

                        @foreach($years as $year)
                        <option value="{{ $year->year_id ?? $year->id }}" {{ (string) $selectedYear === (string) ($year->year_id ?? $year->id) ? 'selected' : '' }}>
                            {{ $year->year }}
                        </option>
                        @endforeach

                    </select>

                </div>

                <div class="col-lg-3 mb-3">

                    <label class="form-label">

                        Category

                    </label>

                    <select
                        class="form-select"
                        id="category"
                        name="category">

                        <option value="">

                            All Categories

                        </option>

                        @foreach($categories as $category)
                        <option value="{{ $category->violation_category_id ?? $category->id }}" {{ (string) $selectedCategory === (string) ($category->violation_category_id ?? $category->id) ? 'selected' : '' }}>
                            {{ $category->category_name }}
                        </option>
                        @endforeach

                    </select>

                </div>

                <div class="col-lg-3 mb-3">

                    <label class="form-label">

                        Violation Type

                    </label>

                    <select
                        class="form-select"
                        id="violationType"
                        name="violation_type">

                        <option value="">

                            All Violations

                        </option>

                        @foreach($violationTypes as $type)
                        <option value="{{ $type->violation_type_id ?? $type->id }}" {{ (string) $selectedViolationType === (string) ($type->violation_type_id ?? $type->id) ? 'selected' : '' }}>
                            {{ $type->violation_type }}
                        </option>
                        @endforeach

                    </select>

                </div>

            </div>
            <div class="row">

    <div class="col-lg-3 mb-3">

        <label class="form-label">

            Start Date

        </label>

        <input
            type="date"
            class="form-control"
            name="start_date"
            value="{{ request('start_date') }}">

    </div>

    <div class="col-lg-3 mb-3">

        <label class="form-label">

            End Date

        </label>

        <input
            type="date"
            class="form-control"
            name="end_date"
            value="{{ request('end_date') }}">

    </div>

    <div class="col-lg-6 mb-3">

        <label class="form-label">

            Search Student

        </label>

        <input
            type="text"
            class="form-control"
            id="searchStudent"
            name="search_student"
            placeholder="Student Number or Name"
            value="{{ request('search_student') }}">

    </div>

</div>
<div class="d-flex justify-content-end">

    <button
        type="reset"
        class="btn btn-outline-secondary me-2">

        Reset

    </button>

    <button
        type="submit"
        class="btn btn-danger">

        <i class="bi bi-search"></i>

        Generate Report

    </button>

</div>

</form>

</div>

</div>
<div class="row mb-4">

    {{-- Monthly Trend --}}
    <div class="col-lg-8 mb-4">

        <div class="card border-0 shadow-sm h-100">

            <div class="card-header bg-white">

                <h5 class="fw-bold mb-0">

                    Monthly Violation Trend

                </h5>

            </div>

            <div class="card-body">

                <canvas
                    id="monthlyTrendChart"
                    height="120">
                </canvas>

            </div>

        </div>

    </div>

    {{-- Category Distribution --}}
    <div class="col-lg-4 mb-4">

        <div class="card border-0 shadow-sm h-100">

            <div class="card-header bg-white">

                <h5 class="fw-bold mb-0">

                    Category Distribution

                </h5>

            </div>

            <div class="card-body">

                <canvas
                    id="categoryChart"
                    height="240">
                </canvas>

            </div>

        </div>

    </div>

</div>
<div class="card border-0 shadow-sm mb-4">

    <div class="card-header bg-white">

        <h5 class="fw-bold mb-0">

            Top 10 Most Common Violations

        </h5>

    </div>

    <div class="card-body">

        <canvas
            id="topViolationChart"
            height="100">
        </canvas>

    </div>

</div>
<div class="card border-0 shadow-sm">

    <div class="card-header bg-white">

        <div
            class="d-flex justify-content-between align-items-center">

            <div>

                <h5 class="fw-bold mb-0">

                    Report Results

                </h5>

                <small class="text-muted">

                    Filtered disciplinary records

                </small>

            </div>

            <span
                class="badge bg-primary"
                id="recordCount">

                {{ $reports->total() }}

                Record(s)

            </span>

        </div>

    </div>

    <div class="table-responsive">
        
    </div>
    <table
    class="table table-hover align-middle mb-0">

    <thead class="table-light">

    <tr>

        <th>Student</th>

        <th>Course</th>

        <th>Violation</th>

        <th>Category</th>

        <th>Offense</th>

        <th>Sanction</th>

        <th>Date</th>

        <th>Recorded By</th>

        <th width="90">

            Action

        </th>

    </tr>

    </thead>

    <tbody id="reportTable">
        @include('admin.reports.partials.table-body')
    </tbody>

</table>
<div class="card-footer bg-white">

    {{ $reports->links() }}

</div>

</div>

</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
window.ReportRoutes = {
    filter: "{{ route('admin.reports.filter') }}",
    excel: "{{ route('admin.reports.export') }}",
    pdf: "{{ route('admin.reports.print') }}"
};
</script>
<script src="{{ asset('js/admin/reports.js') }}"></script>
@endpush