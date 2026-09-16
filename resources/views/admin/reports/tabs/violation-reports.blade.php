{{--
    Admin → Reports → "Violation Reports" tab.

    Content moved verbatim from the former standalone
    `admin/reports/index.blade.php` page when Reports became tabbed. Only the
    page-level header was dropped (the parent Reports page supplies the single
    header). The PDF action is kept because it belongs to this report.

    Expects: $reports, $statistics, $courses, $years, $categories, $violationTypes
--}}

@php
    // These were declared at the top of the former standalone
    // `admin/reports/index.blade.php` page, above the markup that was moved
    // here. They must travel with the moved content.
    $selectedCourse = request('course');
    $selectedYear = request('year');
    $selectedCategory = request('category');
    $selectedViolationType = request('violation_type');
@endphp

<div class="d-flex justify-content-end mb-3">
    <button class="admin-btn admin-btn-neutral" id="exportPdf">
        <i class="bi bi-file-earmark-pdf"></i>
        PDF
    </button>
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

    {{--
        The Minor Violations and Major Violations cards were removed
        deliberately. The summary is now Total Violations + Repeat Offenders
        only; no replacement cards were added.
    --}}

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

<div class="card border-0 shadow-sm mb-4 admin-filter-card">

    <div class="card-header bg-white">

        <h5 class="fw-bold mb-0">

            Report Filters

        </h5>

    </div>

    <div class="card-body">

        <form method="GET" action="{{ route('admin.reports') }}" id="reportFilterForm" class="admin-filter-form" data-submit-guard="Generating...">

            <div class="row">

                <div class="col-lg-3 mb-3">

                    <label class="admin-filter-label">

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

                    <label class="admin-filter-label">

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

                    <label class="admin-filter-label">

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

                    <label class="admin-filter-label">

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

        <label class="admin-filter-label">

            Start Date

        </label>

        <input
            type="date"
            class="form-control"
            name="start_date"
            value="{{ request('start_date') }}">

    </div>

    <div class="col-lg-3 mb-3">

        <label class="admin-filter-label">

            End Date

        </label>

        <input
            type="date"
            class="form-control"
            name="end_date"
            value="{{ request('end_date') }}">

    </div>

    <div class="col-lg-6 mb-3">

        <label class="admin-filter-label">

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
        class="admin-btn admin-btn-neutral me-2">

        Reset

    </button>

    <button
        type="submit"
        class="admin-btn admin-btn-primary">

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
            class="d-flex justify-content-between align-items-center flex-wrap gap-2">

            <div>

                <h5 class="fw-bold mb-0">

                    Report Results

                </h5>

                <small class="text-muted">

                    Filtered disciplinary records

                </small>

            </div>

            <div class="text-end">

                <span
                    class="badge bg-primary"
                    id="recordCount">

                    {{ $reports->total() }}

                    Record(s)

                </span>

                <div class="small text-muted mt-1">
                    Showing {{ $reports->firstItem() ?? 0 }}–{{ $reports->lastItem() ?? 0 }} of {{ $reports->total() }}
                </div>

            </div>

        </div>

    </div>

    <div class="table-responsive report-results">
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
    </div>

<div class="card-footer bg-white">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <label for="reportPerPage" class="text-muted small mb-0">Rows per page:</label>
            <select id="reportPerPage" class="form-select form-select-sm" style="width:auto;" onchange="if(this.value){ const u=new URL(window.location.href); u.searchParams.set('per_page', this.value); u.searchParams.delete('page'); window.location.href=u.toString(); }">
                <option value="25" @selected((int) request('per_page', 25) === 25)>25</option>
                <option value="50" @selected((int) request('per_page') === 50)>50</option>
                <option value="100" @selected((int) request('per_page') === 100)>100</option>
            </select>
        </div>
        <div>
            {{ $reports->links() }}
        </div>
    </div>

</div>

</div>

