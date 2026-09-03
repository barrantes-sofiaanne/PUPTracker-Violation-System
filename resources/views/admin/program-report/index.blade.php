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
                    <select name="program_id" id="programSelect" class="form-select" data-ladderized-programs='@json($ladderizedPrograms)' required>
                        <option value="">Select Program</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->program_id }}" data-program-name="{{ $program->program_name }}" data-degree-type="{{ $program->normalizedDegreeType() ?: '' }}" @selected(old('program_id') == $program->program_id)>{{ $program->program_name }}</option>
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
                                <select id="graduatingYearSelect" class="form-select" data-default-year="{{ $defaultGraduatingYear }}">
                                    @foreach ($availableAdmissionYears as $year)
                                        <option value="{{ $year }}">{{ $year }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="admin-btn admin-btn-neutral w-100" id="addGraduatingYearBtn">
                                    <i class="bi bi-plus-lg me-1"></i> Add More Years
                                </button>
                            </div>
                            <div class="col-md-4 text-muted small align-self-center">
                                <i class="bi bi-info-circle me-1"></i>Pick a year from the list, then click <strong>Add More Years</strong>.
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-3" id="selectedGraduatingYears">
                            {{-- Selected admission years render here as removable chips + hidden inputs --}}
                        </div>

                        {{-- Add a specific student by search --}}
                        <div class="mt-4 border-top pt-3">
                            <label class="admin-filter-label mb-1">
                                <i class="bi bi-person-plus me-1"></i> Add a Specific Student
                            </label>
                            <p class="text-muted small mb-2">
                                Search a student by number or name within the selected program and add them to the graduating list
                                (e.g. returnees / re-admits whose number does not start with the admission years above).
                            </p>
                            <div class="row g-2 align-items-center">
                                <div class="col-md-6">
                                    <input type="text" class="form-control" id="studentSearchInput" placeholder="Search student number or name..." autocomplete="off">
                                    <div class="list-group mt-1 d-none" id="studentSearchResults"></div>
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="admin-btn admin-btn-neutral w-100" id="addSpecificStudentBtn">
                                        <i class="bi bi-person-plus me-1"></i> Add Student
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="admin-btn admin-btn-neutral w-100" id="clearStudentSearchBtn">
                                        <i class="bi bi-x-lg me-1"></i> Clear
                                    </button>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2 mt-3" id="selectedSpecificStudents">
                                {{-- Selected specific students render here as removable chips + hidden inputs --}}
                            </div>
                        </div>

                        {{-- Ladderized filter — shown only for BSOA/BSIT in graduating mode --}}
                        <div class="mt-3 d-none" id="ladderizedWrap">
                            <label class="admin-filter-label mb-1">Ladderized</label>
                            <p class="text-muted small mb-2">
                                For {{ implode(' / ', $ladderizedPrograms) }}, the last digit of the student number encodes
                                ladderized (ends in <strong>1</strong>) vs non-ladderized (ends in <strong>0</strong>).
                                Select <strong>Yes</strong> to include only students whose number ends in 1.
                            </p>
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="ladderized" id="ladderizedAll" value="" @checked(!in_array((string) old('ladderized'), ['0', '1'], true))>
                                    <label class="form-check-label" for="ladderizedAll">All (Both)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="ladderized" id="ladderizedYes" value="1" @checked(old('ladderized') === '1')>
                                    <label class="form-check-label" for="ladderizedYes">Yes</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="ladderized" id="ladderizedNo" value="0" @checked(old('ladderized') === '0')>
                                    <label class="form-check-label" for="ladderizedNo">No</label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2 mt-3 flex-wrap">
                            <button type="button" class="admin-btn admin-btn-primary" id="previewGraduatingBtn" data-preview-url="{{ route('admin.program-report.preview-graduating') }}" data-search-url="{{ route('admin.program-report.search-students') }}">
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
    const selectedYearsContainer = document.getElementById('selectedGraduatingYears');
    const previewBtn = document.getElementById('previewGraduatingBtn');
    const previewWrap = document.getElementById('graduatingPreviewWrap');
    const previewBody = document.getElementById('graduatingPreviewBody');
    const previewSummary = document.getElementById('previewSummary');
    const ladderizedWrap = document.getElementById('ladderizedWrap');

    // Specific-student search controls.
    const studentSearchInput = document.getElementById('studentSearchInput');
    const studentSearchResults = document.getElementById('studentSearchResults');
    const addSpecificStudentBtn = document.getElementById('addSpecificStudentBtn');
    const clearStudentSearchBtn = document.getElementById('clearStudentSearchBtn');
    const selectedSpecificStudentsContainer = document.getElementById('selectedSpecificStudents');

    // Read server-side data from data-* attributes (no Blade directives in JS).
    const defaultGraduatingYear = yearSelect.dataset.defaultYear || '2022';
    const previewUrl = previewBtn.dataset.previewUrl || '';
    const searchStudentsUrl = previewBtn.dataset.searchUrl || '';
    let ladderizedPrograms = [];
    try {
        ladderizedPrograms = JSON.parse(program.dataset.ladderizedPrograms || '[]') || [];
    } catch (e) {
        ladderizedPrograms = [];
    }
    ladderizedPrograms = ladderizedPrograms.map(function (name) { return String(name).toUpperCase(); });

    // Seed the default admission year (2022) as a selected chip.
    const seededYear = String(defaultGraduatingYear);
    let selectedYears = [];
    let selectedStudents = []; // { student_number, label }

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

    // Load any previously selected specific students from old input.
    document.querySelectorAll('#selectedSpecificStudents input[name="additional_students[]"]').forEach(function (input) {
        if (input.value && !selectedStudents.some(function (s) { return s.student_number === input.value; })) {
            selectedStudents.push({ student_number: input.value, label: input.value });
        }
    });
    renderSelectedStudents();

    function isLadderizedProgram() {
        const option = program?.selectedOptions[0];
        const name = (option?.dataset.programName || '').toUpperCase();
        return ladderizedPrograms.indexOf(name) !== -1;
    }

    function getSelectedLadderized() {
        if (!ladderizedWrap || ladderizedWrap.classList.contains('d-none')) {
            return null;
        }
        const checked = document.querySelector('input[name="ladderized"]:checked');
        if (!checked || checked.value === '') {
            return null;
        }
        return checked.value;
    }

    function updateMode() {
        const graduating = mode?.value === 'graduating';
        yearWrap?.classList.toggle('d-none', graduating);
        if (yearInput) yearInput.required = !graduating;
        graduatingWrap?.classList.toggle('d-none', !graduating);
        // Ladderized radio shows only for BSOA/BSIT in graduating mode.
        if (ladderizedWrap) {
            ladderizedWrap.classList.toggle('d-none', !(graduating && isLadderizedProgram()));
        }
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
        candidateYears.push(String(defaultGraduatingYear));
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
        yearSelect.value = '';
    }

    addYearBtn?.addEventListener('click', function () {
        const fromSelect = yearSelect.value;
        addYear(fromSelect);
    });

    selectedYearsContainer?.addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-year');
        if (!btn) return;
        const year = btn.dataset.year;
        selectedYears = selectedYears.filter(function (y) { return y !== year; });
        renderSelectedYears();
        // Keep at least one year.
        if (selectedYears.length === 0) {
            selectedYears.push(String(defaultGraduatingYear));
            renderSelectedYears();
        }
    });

    // ---- Specific student chips (search & add) ----
    function renderSelectedStudents() {
        if (!selectedSpecificStudentsContainer) return;
        selectedSpecificStudentsContainer.innerHTML = '';
        selectedStudents.forEach(function (s) {
            const chip = document.createElement('span');
            chip.className = 'badge rounded-pill text-bg-secondary px-3 py-2 d-inline-flex align-items-center gap-2';
            chip.innerHTML = '<i class="bi bi-person-fill"></i>' + s.label +
                ' <button type="button" class="btn-close btn-close-white remove-specific-student" style="font-size:0.6rem" data-student="' + s.student_number + '" aria-label="Remove ' + s.label + '"></button>';

            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'additional_students[]';
            hidden.value = s.student_number;

            chip.appendChild(hidden);
            selectedSpecificStudentsContainer.appendChild(chip);
        });
    }

    function addSpecificStudent(student) {
        if (!student || !student.student_number) return;
        if (selectedStudents.some(function (s) { return s.student_number === student.student_number; })) {
            alert('Student ' + student.student_number + ' is already in the list.');
            return;
        }
        selectedStudents.push({ student_number: student.student_number, label: (student.name ? student.name + ' (' + student.student_number + ')' : student.student_number) });
        renderSelectedStudents();
        studentSearchInput.value = '';
        studentSearchResults.classList.add('d-none');
        studentSearchResults.innerHTML = '';
    }

    let studentSearchTimer = null;
    studentSearchInput?.addEventListener('input', function () {
        clearTimeout(studentSearchTimer);
        const keyword = this.value.trim();
        if (keyword.length < 2) {
            studentSearchResults.classList.add('d-none');
            studentSearchResults.innerHTML = '';
            return;
        }
        studentSearchTimer = setTimeout(function () {
            const programId = program.value;
            if (!programId) {
                alert('Select a program first.');
                return;
            }
            fetch(searchStudentsUrl + '?program_id=' + encodeURIComponent(programId) + '&search=' + encodeURIComponent(keyword), {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            })
                .then(function (resp) { return resp.json(); })
                .then(function (data) {
                    const list = data.students || [];
                    studentSearchResults.innerHTML = '';
                    if (list.length === 0) {
                        studentSearchResults.innerHTML = '<button type="button" class="list-group-item list-group-item-action text-muted" disabled>No matching students.</button>';
                    } else {
                        list.forEach(function (s) {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'list-group-item list-group-item-action py-1 px-2 small';
                            btn.innerHTML = '<strong>' + s.student_number + '</strong> &mdash; ' + s.name + ' <span class="text-muted">(' + s.program + ' &middot; ' + (s.year || '-') + ')</span>';
                            btn.addEventListener('click', function () { addSpecificStudent(s); });
                            studentSearchResults.appendChild(btn);
                        });
                    }
                    studentSearchResults.classList.remove('d-none');
                })
                .catch(function () {
                    studentSearchResults.innerHTML = '<button type="button" class="list-group-item list-group-item-action text-muted" disabled>Search failed.</button>';
                    studentSearchResults.classList.remove('d-none');
                });
        }, 300);
    });

    addSpecificStudentBtn?.addEventListener('click', function () {
        const keyword = studentSearchInput.value.trim();
        if (keyword.length === 0) {
            alert('Type a student number or name to search first.');
            return;
        }
        // If the user typed an exact student number, add it directly.
        if (/^\S+$/.test(keyword)) {
            const programId = program.value;
            if (!programId) { alert('Select a program first.'); return; }
            fetch(searchStudentsUrl + '?program_id=' + encodeURIComponent(programId) + '&search=' + encodeURIComponent(keyword), {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            })
                .then(function (resp) { return resp.json(); })
                .then(function (data) {
                    const matches = (data.students || []).filter(function (s) {
                        return s.student_number === keyword || (s.name || '').toLowerCase().indexOf(keyword.toLowerCase()) !== -1;
                    });
                    if (matches.length === 0) {
                        alert('No matching student found. Check the program and student number/name.');
                        return;
                    }
                    matches.forEach(addSpecificStudent);
                })
                .catch(function () { alert('Search failed. Please try again.'); });
            return;
        }
        alert('Search results appear below. Click a result to add it to the list.');
    });

    clearStudentSearchBtn?.addEventListener('click', function () {
        studentSearchInput.value = '';
        studentSearchResults.classList.add('d-none');
        studentSearchResults.innerHTML = '';
    });

    selectedSpecificStudentsContainer?.addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-specific-student');
        if (!btn) return;
        const studentNumber = btn.dataset.student;
        selectedStudents = selectedStudents.filter(function (s) { return s.student_number !== studentNumber; });
        renderSelectedStudents();
    });

    previewBtn?.addEventListener('click', async function () {
        const programId = program.value;
        if (!programId) { alert('Select a program first.'); return; }
        if (selectedYears.length === 0 && selectedStudents.length === 0) {
            alert('Add at least one admission year or one specific student.');
            return;
        }

        previewBtn.disabled = true;
        previewBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Loading...';
        previewWrap.classList.remove('d-none');
        previewBody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted">Loading students...</td></tr>';
        previewSummary.textContent = '';

        try {
            const response = await fetch(previewUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    program_id: programId,
                    graduating_years: selectedYears,
                    additional_students: selectedStudents.map(function (s) { return s.student_number; }),
                    ladderized: getSelectedLadderized(),
                }),
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Unable to preview students.');
            }

            const ladderizedLabel = data.ladderized_filter === 1 ? ' (Ladderized: Yes)' : (data.ladderized_filter === 0 ? ' (Ladderized: No)' : '');
            previewSummary.textContent = data.total + ' student(s) in the graduating list (admission years + specific students)' + ladderizedLabel + '.';

            if (data.total === 0) {
                previewBody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted">No students match the selected admission years / specific students.</td></tr>';
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
