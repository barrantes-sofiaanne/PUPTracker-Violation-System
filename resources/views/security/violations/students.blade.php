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
                <button class="btn portal-btn" type="button" data-bs-toggle="modal" data-bs-target="#recordViolationModal">
                    <i class="bi bi-plus-circle me-2"></i>Record Violation
                </button>

                <a href="{{ route('security.violations.report') }}" target="_blank" class="btn portal-btn-outline">
                    <i class="bi bi-file-earmark-pdf me-2"></i>Generate Report
                </a>
            </div>
        </div>
    </div>

    {{-- Search Bar --}}
    <div class="search-container">
        <div class="card portal-card">
            <div class="card-body">
                <div class="input-group">
                    <span class="input-group-text bg-light border-0">
                        <i class="bi bi-search"></i>
                    </span>
                    <input
                        type="text"
                        class="form-control border-0 bg-light"
                        id="securityStudentSearch"
                        placeholder="Search by student number or name...">
                </div>
            </div>
        </div>
    </div>

    {{-- Students List --}}
    <div class="card portal-card">
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

@include('security.violations.partials.record-violation')

@push('scripts')
<script>
window.ViolationRoutes = {
    searchStudent: "{{ route('security.search.student') }}",
    violationTypes: "{{ route('security.violations.types') }}",
    previewViolation: "{{ route('security.violations.preview') }}",
    store: "{{ route('security.violations.store') }}",
};
</script>
<script src="{{ asset('js/security/record-violation.js') }}"></script>
@endpush

@endsection
