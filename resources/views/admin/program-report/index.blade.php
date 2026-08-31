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

    @if ($errors->has('graduating_years'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ $errors->first('graduating_years') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.program-report.generate') }}" class="row g-3" data-submit-guard="Generating report..." id="programReportForm">
                @csrf

                <div class="col-md-4">
                    <label class="form-label">Report Type</label>
                    <select name="report_mode" id="reportMode" class="form-select" required>
                        <option value="regular" @selected(old('report_mode') === 'regular' || old('report_mode') === null)>Program Sanction Report</option>
                        <option value="graduating" @selected(old('report_mode') === 'graduating')>Graduating Students</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Program</label>
                    <select name="program_id" id="programSelect" class="form-select" required>
                        <option value="">Select Program</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->program_id }}" data-degree-type="{{ $program->normalizedDegreeType() ?: '' }}" @selected(old('program_id') == $program->program_id)>{{ $program->program_name }}</option>
                        @endforeach
                    </select>
                    @error('program_id')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4" id="academicYearWrap">
                    <label class="form-label">Academic Year</label>
                    <input type="text" name="academic_year" class="form-control" placeholder="e.g. 2026" value="{{ old('academic_year') }}" required>
                    @error('academic_year')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                {{-- ============================================= --}}
                {{-- Graduating mode: admission year(s) selection  --}}
                {{-- ============================================= --}}
                <div class="col-12 d-none" id="graduatingYearsWrap">
                    <div class="border rounded-3 p-3 bg-light-subtle">
                        <label class="admin-filter-label mb-1">Admission Year(s) of Graduating Students</label>
                        <p class="text-muted small mb-2">
                            The first four digits of the student number is the year the student was admitted.
                            Students whose student number starts with the selected year(s) are treated as graduating
                            (e.g. 2022 → <code>2022-XXXX</code>). Add more years for returnees with a different start year.
                        </p>

                        <div class="row g-2 align-items-center">
                            <div class="col-md-5">
                                <select id="graduatingYearSelect" class="form-select">
                                    @foreach ($availableAdmissionYears as $year)
                                        <option value="{{ $year }}">{{ $year }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="admin-btn admin-btn-neutral w-100" id="addGraduatingYearBtn">
                                    <i class="bi bi-plus-lg me-1"></i> Add Year
                                </button>
                            </div>
                            <div class="col-md-4">
                                <input type="number" class="form-control" id="customGraduatingYear" min="1900" max="{{ now()->year }}" placeholder="Manual year (e.g. 2019)">
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-3" id="selectedGraduatingYears">
                            {{-- Selected admission years render here as removable chips + hidden inputs --}}
                        </div>

                        <div class="d-flex align-items-center gap-2 mt-3 flex-wrap">
                            <button type="button" class="admin-btn admin-btn-primary" id="previewGraduatingBtn">
                                <i class="bi bi-search me-1"></i> Preview Students
                            </button>
                            <span class="text-muted small" id="previewSummary"></span>
                        </div>

                        <div class="mt-3 d-none" id="graduatingPreviewWrap">
                            <div class="table-responsive border rounded-3 bg-white">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Student Number</th>
                                            <th>Name</th>
                                            <th>Program</th>
                                            <th>Year / Section</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="graduatingPreviewBody">
                                        {{-- Preview rows injected via JS --}}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
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
    const graduatingWrap = document.getElementById('graduatingYearsWrap');
    const yearSelect = document.getElementById('graduatingYearSelect');
    const addYearBtn = document.getElementById('addGraduatingYearBtn');
    const customYearInput = document.getElementById('customGraduatingYear');
    const selectedYearsContainer = document.getElementById('selectedGraduatingYears');
    const previewBtn = document.getElementById('previewGraduatingBtn');
    const previewWrap = document.getElementById('graduatingPreviewWrap');
    const previewBody = document.getElementById('graduatingPreviewBody');
    const previewSummary = document.getElementById('previewSummary');

    // Seed the default admission year (2022) as a selected chip.
    const seededYear = String({{ $defaultGraduatingYear }});
    let selectedYears = [];

    // Load any previously selected years from old input (validation redirect).
    document.querySelectorAll('#selectedGraduatingYears input[name="graduating_years[]"]').forEach(function (input) {
        if (input.value && !selectedYears.includes(input.value)) {
            selectedYears.push(input.value);
        }
    });
    if (selectedYears.length === 0) {
        selectedYears.push(seededYear);
    }
    renderSelectedYears();

    function updateMode() {
        const graduating = mode?.value === 'graduating';
        yearWrap?.classList.toggle('d-none', graduating);
        if (yearInput) yearInput.required = !graduating;
        graduatingWrap?.classList.toggle('d-none', !graduating);
    }

    function renderSelectedYears() {
        selectedYearsContainer.innerHTML = '';
        selectedYears.forEach(function (year) {
            const chip = document.createElement('span');
            chip.className = 'badge rounded-pill text-bg-dark px-3 py-2 d-inline-flex align-items-center gap-2';
            chip.innerHTML = year + ' <button type="button" class="btn-close btn-close-white remove-year" style="font-size:0.6rem" data-year="' + year + '" aria-label="Remove ' + year + '"></button>';

            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'graduating_years[]';
            hidden.value = year;

            chip.appendChild(hidden);
            selectedYearsContainer.appendChild(chip);
        });
        updateYearSelectOptions();
    }

    function updateYearSelectOptions() {
        // Remove already-selected years from the dropdown.
        const current = yearSelect.value;
        yearSelect.innerHTML = '';
        const candidateYears = [];
        // Default candidates: the config default plus any manually added ones.
        candidateYears.push(String({{ $defaultGraduatingYear }}));
        selectedYears.forEach(function (y) { if (!candidateYears.includes(y)) candidateYears.push(y); });
        // Also allow any recent year as a quick option.
        const thisYear = new Date().getFullYear();
        for (let y = thisYear; y >= thisYear - 8; y--) {
            if (!candidateYears.includes(String(y))) candidateYears.push(String(y));
        }
        candidateYears.sort((a, b) => b - a).forEach(function (y) {
            if (selectedYears.includes(y)) return;
            const opt = document.createElement('option');
            opt.value = y;
            opt.textContent = y;
            yearSelect.appendChild(opt);
        });
        if (selectedYears.includes(current)) {
            // pick first non-selected
        } else if (yearSelect.options.length > 0) {
            yearSelect.value = yearSelect.options[0].value;
        }
    }

    function addYear(year) {
        const y = String(parseInt(year, 10));
        if (!y || isNaN(parseInt(y, 10))) { alert('Enter a valid year.'); return; }
        if (parseInt(y, 10) < 1900 || parseInt(y, 10) > new Date().getFullYear()) { alert('Year must be between 1900 and ' + new Date().getFullYear() + '.'); return; }
        if (selectedYears.includes(y)) { alert('Year ' + y + ' is already added.'); return; }
        selectedYears.push(y);
        renderSelectedYears();
        customYearInput.value = '';
    }

    addYearBtn?.addEventListener('click', function () {
        const fromSelect = yearSelect.value;
        const fromCustom = customYearInput.value.trim();
        addYear(fromCustom || fromSelect);
    });

    customYearInput?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addYear(customYearInput.value.trim());
        }
    });

    selectedYearsContainer?.addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-year');
        if (!btn) return;
        const year = btn.dataset.year;
        selectedYears = selectedYears.filter(function (y) { return y !== year; });
        renderSelectedYears();
        // Keep at least one year.
        if (selectedYears.length === 0) {
            selectedYears.push(String({{ $defaultGraduatingYear }}));
            renderSelectedYears();
        }
    });

    previewBtn?.addEventListener('click', async function () {
        const programId = program.value;
        if (!programId) { alert('Select a program first.'); return; }
        if (selectedYears.length === 0) { alert('Add at least one admission year.'); return; }

        previewBtn.disabled = true;
        previewBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Loading...';
        previewWrap.classList.remove('d-none');
        previewBody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted">Loading students...</td></tr>';
        previewSummary.textContent = '';

        try {
            const response = await fetch("{{ route('admin.program-report.preview-graduating') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    program_id: programId,
                    graduating_years: selectedYears,
                }),
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Unable to preview students.');
            }

            previewSummary.textContent = data.total + ' student(s) match the selected admission year(s).';

            if (data.total === 0) {
                previewBody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted">No students match the selected admission year(s).</td></tr>';
                return;
            }

            previewBody.innerHTML = data.students.map(function (s) {
                return '<tr>' +
                    '<td>' + s.student_number + '</td>' +
                    '<td>' + s.name + '</td>' +
                    '<td>' + s.program + '</td>' +
                    '<td>' + (s.year || '-') + ' / ' + (s.section || '-') + '</td>' +
                    '<td>' + (s.status || '-') + '</td>' +
                '</tr>';
            }).join('');
        } catch (error) {
            console.error(error);
            previewBody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-danger">' + error.message + '</td></tr>';
        } finally {
            previewBtn.disabled = false;
            previewBtn.innerHTML = '<i class="bi bi-search me-1"></i> Preview Students';
        }
    });

    mode?.addEventListener('change', updateMode);
    program?.addEventListener('change', updateMode);
    updateMode();
    renderSelectedYears();
});
</script>
@endpush
