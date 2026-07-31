@extends('layouts.security')

@section('title', 'Students with Violations')

@push('styles')
<style>
    .student-card {
        border: 1px solid rgba(128, 0, 0, 0.12);
        border-radius: 0.95rem;
        transition: all 0.2s ease;
        overflow: hidden;
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.15rem 1.25rem;
        margin-bottom: 1rem;
        background: linear-gradient(180deg, #fffefa 0%, #fff8e8 100%);
        box-shadow: 0 8px 20px rgba(128, 0, 0, 0.08);
    }

    .student-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 24px rgba(128, 0, 0, 0.12);
    }

    .violation-badge {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, #800000 0%, #5f0000 100%);
        color: white;
        font-weight: 700;
        font-size: 1.3rem;
    }

    .student-name {
        font-weight: 800;
        color: #5f0000;
        margin-bottom: 0.25rem;
    }

    .student-meta {
        font-size: 0.85rem;
        color: #67585c;
    }

    .page-header {
        margin-bottom: 1.5rem;
    }

    .search-container {
        margin-bottom: 1.5rem;
    }

    .action-btn-static {
        border-radius: 0.8rem;
        font-weight: 700;
        transition: none !important;
    }

    .action-btn-static-primary,
    .action-btn-static-primary:hover,
    .action-btn-static-primary:focus,
    .action-btn-static-primary:active {
        background: #8a0000 !important;
        border: 1px solid #8a0000 !important;
        color: #fff !important;
        box-shadow: none !important;
    }

    .action-btn-static-outline,
    .action-btn-static-outline:hover,
    .action-btn-static-outline:focus,
    .action-btn-static-outline:active {
        background: rgba(255, 255, 255, 0.2) !important;
        border: 1px solid rgba(255, 255, 255, 0.35) !important;
        color: #fff !important;
        box-shadow: none !important;
    }

    .report-option {
        border: 1px solid rgba(128, 0, 0, 0.15);
        border-radius: 0.8rem;
        padding: 0.75rem 0.9rem;
        transition: border-color 0.2s ease, background-color 0.2s ease;
        cursor: pointer;
    }

    .report-option input[type="radio"] {
        margin-top: 0.2rem;
    }

    .report-option.active {
        border-color: #8a0000;
        background: rgba(138, 0, 0, 0.06);
    }

    .filter-grid {
        display: grid;
        grid-template-columns: 1.5fr 1fr 1fr auto auto;
        gap: 0.75rem;
        align-items: end;
    }

    @media (max-width: 991.98px) {
        .filter-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')

<div class="container-fluid py-4">

    {{-- Page Header --}}
    <div class="portal-hero page-header-modern">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <h1 class="fw-bold mb-1">Students with Violations</h1>
                <p class="mb-0">View detailed violation records for students.</p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button class="btn action-btn-static action-btn-static-primary" type="button" data-bs-toggle="modal" data-bs-target="#recordViolationModal">
                    <i class="bi bi-plus-circle me-2"></i>Record Violation
                </button>

                <button class="btn action-btn-static action-btn-static-primary" type="button" data-bs-toggle="modal" data-bs-target="#reportOptionsModal">
                    <i class="bi bi-file-earmark-pdf me-2"></i>Generate Report
                </button>
            </div>
        </div>
    </div>

    {{-- Search and Filters --}}
    <div class="search-container">
        <div class="card portal-card">
            <div class="card-body">
                <form method="GET" action="{{ route('security.violations.students') }}" class="filter-grid">
                    <div>
                        <label for="search" class="form-label mb-1">Search</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0">
                                <i class="bi bi-search"></i>
                            </span>
                            <input
                                type="text"
                                class="form-control border-0 bg-light"
                                id="search"
                                name="search"
                                value="{{ $search }}"
                                placeholder="Search by student number or name...">
                        </div>
                    </div>

                    <div>
                        <label for="program_id" class="form-label mb-1">Program</label>
                        <select class="form-select" id="program_id" name="program_id">
                            <option value="">All Programs</option>
                            @foreach($programs as $program)
                                <option value="{{ $program->program_id }}" {{ (string) $programId === (string) $program->program_id ? 'selected' : '' }}>
                                    {{ $program->program_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="year_id" class="form-label mb-1">Year</label>
                        <select class="form-select" id="year_id" name="year_id">
                            <option value="">All Years</option>
                            @foreach($years as $year)
                                <option value="{{ $year->year_id }}" {{ (string) $yearId === (string) $year->year_id ? 'selected' : '' }}>
                                    Year {{ $year->year }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <button type="submit" class="btn portal-btn w-100">Apply</button>
                    </div>

                    <div>
                        <a href="{{ route('security.violations.students') }}" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Students List --}}
    <div id="managementTableContainer" class="card portal-card">
        <div class="card-body p-0">
            @forelse($students as $student)
            <a href="{{ route('security.violations.show', $student->student_number) }}" class="text-decoration-none text-dark">
                <div class="student-card">
                    <div class="violation-badge">
                        {{ $student->violations_count }}
                    </div>
                    <div class="student-info">
                        <div class="student-name">
                            {{ $student->student_number }} - {{ $student->last_name }}, {{ $student->first_name }}
                        </div>
                        <div class="student-meta">
                            <i class="bi bi-building"></i> {{ optional(optional($student->studentInfo)->program)->program_name ?? 'N/A' }}
                            @if(optional($student->studentInfo)->year)
                            | <i class="bi bi-calendar"></i> Year {{ optional(optional($student->studentInfo)->year)->year }}
                            @endif
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="portal-badge danger">
                            {{ $student->violations_count }} violation{{ $student->violations_count !== 1 ? 's' : '' }}
                        </span>
                        <div style="font-size: 0.8rem; color: #67585c; margin-top: 0.5rem;">
                            Latest: {{ $student->violations->first()?->violation_date?->format('M d, Y') ?? 'N/A' }}
                        </div>
                    </div>
                </div>
            </a>
            @empty
            <div class="text-center py-5">
                <i class="bi bi-inbox display-4" style="color: var(--portal-goldenrod);"></i>
                <p class="mt-3" style="color: #67585c;">No students with violations found.</p>
            </div>
            @endforelse
        </div>
    </div>

    {{-- Pagination --}}
    @if($students->hasPages())
    <div class="mt-4">
        {{ $students->links() }}
    </div>
    @endif

</div>

<div class="modal fade" id="reportOptionsModal" tabindex="-1" aria-labelledby="reportOptionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background: linear-gradient(135deg, #800000 0%, #5f0000 100%); color: #fff;">
                <h5 class="modal-title fw-bold" id="reportOptionsModalLabel">
                    <i class="bi bi-file-earmark-bar-graph me-2"></i>Generate Violation Report
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="GET" action="{{ route('security.violations.report') }}" target="_blank">
                <div class="modal-body">
                    <p class="mb-3 text-muted">Select the report range you want to generate.</p>

                    <div class="d-grid gap-2 mb-3">
                        <label class="report-option active" data-option="last7">
                            <div class="d-flex align-items-start gap-2">
                                <input class="form-check-input" type="radio" name="period" value="last7" checked>
                                <div>
                                    <div class="fw-semibold">Last 7 Days</div>
                                    <small class="text-muted">Only records created within the last week.</small>
                                </div>
                            </div>
                        </label>

                        <label class="report-option" data-option="last30">
                            <div class="d-flex align-items-start gap-2">
                                <input class="form-check-input" type="radio" name="period" value="last30">
                                <div>
                                    <div class="fw-semibold">Last 30 Days</div>
                                    <small class="text-muted">Monthly report snapshot.</small>
                                </div>
                            </div>
                        </label>

                        <label class="report-option" data-option="all">
                            <div class="d-flex align-items-start gap-2">
                                <input class="form-check-input" type="radio" name="period" value="all">
                                <div>
                                    <div class="fw-semibold">All My Recorded Violations</div>
                                    <small class="text-muted">Complete report of your encoded records.</small>
                                </div>
                            </div>
                        </label>

                        <label class="report-option" data-option="custom">
                            <div class="d-flex align-items-start gap-2">
                                <input class="form-check-input" type="radio" name="period" value="custom">
                                <div>
                                    <div class="fw-semibold">Custom Date Range</div>
                                    <small class="text-muted">Generate report for specific start and end dates.</small>
                                </div>
                            </div>
                        </label>
                    </div>

                    <div id="customDateRangeFields" class="row g-2 d-none">
                        <div class="col-6">
                            <label class="form-label mb-1" for="reportDateFrom">From</label>
                            <input type="date" id="reportDateFrom" name="date_from" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label mb-1" for="reportDateTo">To</label>
                            <input type="date" id="reportDateTo" name="date_to" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn action-btn-static action-btn-static-primary">
                        <i class="bi bi-printer me-1"></i>Generate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('security.violations.partials.record-violation')

@push('scripts')
<script>
window.ViolationRoutes = {
    searchStudent: "{{ route('security.search.student') }}",
    violationTypes: "{{ route('security.violations.types') }}",
    previewViolation: "{{ route('security.violations.preview') }}",
    store: "{{ route('security.violations.store') }}",
};

document.addEventListener('DOMContentLoaded', function () {
    const options = document.querySelectorAll('.report-option');
    const customFields = document.getElementById('customDateRangeFields');
    const dateFrom = document.getElementById('reportDateFrom');
    const dateTo = document.getElementById('reportDateTo');

    const applyOptionState = function () {
        let selected = 'last7';

        options.forEach(function (option) {
            const radio = option.querySelector('input[type="radio"]');
            const isChecked = !!radio && radio.checked;
            option.classList.toggle('active', isChecked);

            if (isChecked) {
                selected = option.getAttribute('data-option') || selected;
            }
        });

        const isCustom = selected === 'custom';
        if (customFields) {
            customFields.classList.toggle('d-none', !isCustom);
        }

        if (dateFrom) {
            dateFrom.required = isCustom;
        }

        if (dateTo) {
            dateTo.required = isCustom;
        }
    };

    options.forEach(function (option) {
        option.addEventListener('click', function () {
            const radio = option.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
            }

            applyOptionState();
        });
    });

    applyOptionState();
});
</script>
<script src="{{ asset('js/security/record-violation.js') }}"></script>
@endpush

@endsection
