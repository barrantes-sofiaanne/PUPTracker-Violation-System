@extends('layouts.admin')

@section('title', 'Program Sanction Summary Report')

@section('content')

<div class="container-fluid">

    <div class="page-header-modern d-flex justify-content-between align-items-center flex-wrap mb-4">
        <div>
            <h2 class="fw-bold mb-1">Program Sanction Summary Report</h2>
            <p class="mb-0">Generate a factual program sanction report, with optional AI-assisted observations for graduating cohorts.</p>
        </div>
        <a href="{{ route('admin.program-report.history') }}" class="admin-btn admin-btn-neutral">
            <i class="bi bi-clock-history"></i> Report History
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.program-report.generate') }}" class="row g-3" data-submit-guard="Generating report...">
                @csrf

                <div class="col-md-4">
                    <label class="form-label">Report Type</label>
                    <select name="report_mode" id="reportMode" class="form-select" required>
                        <option value="regular">Program Sanction Report</option>
                        <option value="graduating">Graduating Students</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Program</label>
                    <select name="program_id" id="programSelect" class="form-select" required>
                        <option value="">Select Program</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->program_id }}" data-degree-type="{{ $program->normalizedDegreeType() ?: '' }}" data-graduating-year="{{ $program->graduatingYear() ?: '' }}">{{ $program->program_name }}</option>
                        @endforeach
                    </select>
                    @error('program_id')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4" id="academicYearWrap">
                    <label class="form-label">Graduating Cohort</label>
                    <input type="text" name="academic_year" class="form-control" placeholder="e.g. 2026" required>
                    @error('academic_year')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">Result Filter</label>
                    <select name="report_type" class="form-select">
                        <option value="full">Entire Program</option>
                        <option value="violations">Students With Violations</option>
                        <option value="outstanding">Students With Outstanding Sanctions</option>
                        <option value="manual_review">Students Requiring Manual Review</option>
                    </select>
                </div>

                <div class="col-md-8 d-none" id="graduatingCriteria" aria-live="polite">
                    <div class="border rounded-3 p-3 bg-light-subtle">
                        <div class="admin-filter-label mb-1">Graduating Student Criteria</div>
                        <strong id="degreeTypeText">Select a classified program</strong>
                        <span class="text-muted" id="graduatingYearText"></span>
                    </div>
                </div>

                <div class="col-12">
                    <button type="submit" class="admin-btn admin-btn-primary">
                        <i class="bi bi-stars"></i> Generate Program Report
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const mode = document.getElementById('reportMode');
    const program = document.getElementById('programSelect');
    const yearWrap = document.getElementById('academicYearWrap');
    const yearInput = yearWrap?.querySelector('input[name="academic_year"]');
    const criteria = document.getElementById('graduatingCriteria');
    const degree = document.getElementById('degreeTypeText');
    const year = document.getElementById('graduatingYearText');

    function updateMode() {
        const graduating = mode?.value === 'graduating';
        yearWrap?.classList.toggle('d-none', graduating);
        if (yearInput) yearInput.required = !graduating;
        criteria?.classList.toggle('d-none', !graduating);
        const option = program?.selectedOptions[0];
        const degreeType = option?.dataset.degreeType || '';
        const graduatingYear = option?.dataset.graduatingYear || '';
        if (degree) degree.textContent = degreeType ? degreeType + ' Degree' : 'Program classification unavailable';
        if (year) year.textContent = graduatingYear ? ' · ' + graduatingYear + (graduatingYear === '3' ? 'rd' : 'th') + ' Year Students' : ' · Classification required';
    }

    mode?.addEventListener('change', updateMode);
    program?.addEventListener('change', updateMode);
    updateMode();
});
</script>
@endpush
