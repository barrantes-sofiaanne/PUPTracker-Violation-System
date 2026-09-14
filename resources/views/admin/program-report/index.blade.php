@extends('layouts.admin')

@section('title', 'AI Program Report')

@section('content')

<div class="container-fluid">

    <div class="page-header-modern d-flex justify-content-between align-items-center flex-wrap mb-4">
        <div>
            <h2 class="fw-bold mb-1">AI Program Report</h2>
            <p class="mb-0">Generate a factual graduating-cohort report for a program, with optional AI-assisted observations.</p>
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

                {{--
                    The "Program Sanction Report" mode has been retired from this
                    workflow: only the Graduating Students report is offered here.
                    The report_mode value is still posted explicitly so the existing
                    controller contract, validation and stored-report columns are
                    untouched — nothing about the graduating workflow changes.
                --}}
                <input type="hidden" name="report_mode" id="reportMode" value="graduating">

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
                {{-- Graduating mode: auto by year level + manual specific students --}}
                {{-- ============================================= --}}
                <div class="col-12 d-none" id="graduatingYearsWrap">
                    <div class="border rounded-3 p-3 bg-light-subtle">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-mortarboard text-primary fs-5"></i>
                            <div>
                                <label class="admin-filter-label mb-1">Graduation Eligibility (Automatic)</label>
                                <p class="text-muted small mb-0">
                                    Students are automatically eligible based on their <strong>year level</strong>, which is
                                    derived from the selected program's degree type:
                                </p>
                                <ul class="text-muted small mb-0 mt-1 ps-4">
                                    <li><strong>Bachelor / Ladderized Bachelor:</strong> 4th Year</li>
                                    <li><strong>Diploma:</strong> 3rd Year</li>
                                </ul>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-3" id="selectedGraduatingYears">
                            {{-- No admission-year chips: eligibility is auto-derived from the program. --}}
                        </div>

                        {{-- Add a specific student by search (manual override) --}}
                        <div class="mt-4 border-top pt-3">
                            <label class="admin-filter-label mb-1">
                                <i class="bi bi-person-plus me-1"></i> Add a Specific Student
                            </label>
                            <p class="text-muted small mb-2">
                                Manually add a student by number or name within the selected program (e.g. returnees /
                                re-admits who should be reviewed even if they are not in the automatic year level above).
                                Added students are always included in the graduating list.
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
                                For {{ implode(' / ', $ladderizedPrograms) }}, filter the automatically eligible
                                students by their ladderized status. Select <strong>Yes</strong> to include only
                                ladderized students, or <strong>No</strong> for only non-ladderized students.
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
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="graduatingPreviewBody">
                                        {{-- Preview rows injected via JS --}}
                                    </tbody>
                                </table>
                            </div>
                            <p class="text-muted small mt-2 mb-0">
                                <i class="bi bi-info-circle me-1"></i>
                                Removing a student only excludes them from <strong>this</strong> report.
                                Their student record is not changed or deleted. Re-running the preview restores the
                                full eligible list.
                            </p>

                            {{-- Students removed from the preview. Submitted with the form so the
                                 generated report uses the modified list. Re-authorised server-side. --}}
                            <div id="excludedStudentsInputs" aria-hidden="true"></div>
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
    const program = document.getElementById('programSelect');
    const yearWrap = document.getElementById('academicYearWrap');
    const yearInput = yearWrap?.querySelector('input[name="academic_year"]');
    const graduatingWrap = document.getElementById('graduatingYearsWrap');
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
    const previewUrl = previewBtn.dataset.previewUrl || '';
    const searchStudentsUrl = previewBtn.dataset.searchUrl || '';
    let ladderizedPrograms = [];
    try {
        ladderizedPrograms = JSON.parse(program.dataset.ladderizedPrograms || '[]') || [];
    } catch (e) {
        ladderizedPrograms = [];
    }
    ladderizedPrograms = ladderizedPrograms.map(function (name) { return String(name).toUpperCase(); });

    let selectedStudents = []; // { student_number, label }

    // Students removed from the graduating preview for THIS report only.
    // Preview-only state — the underlying student records are untouched.
    let excludedStudents = [];
    const excludedInputs = document.getElementById('excludedStudentsInputs');

    function renderExcludedInputs() {
        if (!excludedInputs) return;
        excludedInputs.innerHTML = excludedStudents.map(function (n) {
            return '<input type="hidden" name="excluded_students[]" value="' + n + '">';
        }).join('');
    }

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
        // Only the graduating workflow is offered on this page, so the year /
        // graduating field groups are toggled once rather than per mode change.
        const graduating = true;
        yearWrap?.classList.toggle('d-none', graduating);
        if (yearInput) yearInput.required = !graduating;
        graduatingWrap?.classList.toggle('d-none', !graduating);
        // Ladderized radio shows only for BSOA/BSIT in graduating mode.
        if (ladderizedWrap) {
            ladderizedWrap.classList.toggle('d-none', !(graduating && isLadderizedProgram()));
        }
    }

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
                    additional_students: selectedStudents.map(function (s) { return s.student_number; }),
                    ladderized: getSelectedLadderized(),                      exclude_students: excludedStudents,                }),
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Unable to preview students.');
            }

            const ladderizedLabel = data.ladderized_filter === 1 ? ' (Ladderized: Yes)' : (data.ladderized_filter === 0 ? ' (Ladderized: No)' : '');
              const removedLabel = excludedStudents.length > 0 ? (', ' + excludedStudents.length + ' removed') : '';
              previewSummary.textContent = data.total + ' student(s) in the graduating list (automatic year level + specific students)' + ladderizedLabel + removedLabel + '.';
            if (data.total === 0) {
                previewBody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted">No students match the automatic year level for this program / specific students.</td></tr>';
                return;
            }

            previewBody.innerHTML = data.students.map(function (s) {
                return '<tr>' +
                    '<td>' + s.student_number + '</td>' +
                    '<td>' + s.name + '</td>' +
                    '<td>' + s.program + '</td>' +
                    '<td>' + (s.year || '-') + ' / ' + (s.section || '-') + '</td>' +
                    '<td>' + (s.status || '-') + '</td>' +                      '<td class="text-end">' +
                          '<button type="button" class="admin-btn admin-btn-danger admin-btn-sm remove-preview-student" ' +
                              'data-student="' + s.student_number + '" ' +
                              'aria-label="Remove ' + (s.name || s.student_number) + ' from this report">' +
                              '<i class="bi bi-x-lg me-1"></i>Remove' +
                          '</button>' +
                      '</td>' +                '</tr>';
            }).join('');
        } catch (error) {
            console.error(error);
            previewBody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-danger">' + error.message + '</td></tr>';
        } finally {
            previewBtn.disabled = false;
            previewBtn.innerHTML = '<i class="bi bi-search me-1"></i> Preview Students';
        }
    });

    // Remove a student from the preview. Preview-only: nothing is deleted, and
    // the server re-authorises the exclusion when the report is generated.
    previewBody?.addEventListener('click', function (event) {
        const btn = event.target.closest('.remove-preview-student');
        if (!btn) return;
        const studentNumber = btn.dataset.student;
        if (!studentNumber) return;
        if (!excludedStudents.includes(studentNumber)) {
            excludedStudents.push(studentNumber);
        }
        renderExcludedInputs();
        // Re-request the preview so the count and list reflect the removal.
        previewBtn?.click();
    });

    // report_mode is a fixed hidden value now, so only the program selection
    // can change the graduating/ladderized visibility.
    program?.addEventListener('change', updateMode);
    updateMode();
});
</script>
@endpush
