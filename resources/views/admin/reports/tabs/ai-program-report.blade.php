{{--
    Admin → Reports → "Graduation AI Report" tab.

    This is the AI feature's ONLY workflow. It hosts the existing Graduation AI
    Report process, moved here as a shared partial from the former standalone
    `admin/program-report/index.blade.php` page: configure the report, retrieve
    the automatically eligible graduating cohort, add specific students, preview
    the list, remove a student from the preview, then generate.

    The former "Program Sanction Report" (regular) mode is gone from this feature
    entirely, which is why there is no mode selector and no academic-year input.
    Historical report_mode='regular' rows are untouched and remain listable in
    the Report History tab.

    The parent Reports page supplies the single page header; this partial
    deliberately has no <h2> of its own.

    Expects: $programs, $ladderizedPrograms ($ladderizedPrograms is also the
    render guard in reports/show.blade.php, so it must not be renamed).
--}}

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
                    The retired "Program Sanction Report" mode is gone from this
                    feature: there is no mode selector and no academic-year input,
                    because both existed only for regular (program-sanction) mode.
                    `report_mode` is still posted as a fixed hidden value so the
                    existing controller contract, validation and stored-report
                    columns are untouched.

                    Historical report_mode='regular' rows are NOT affected — the
                    Report History tab still lists and filters them.
                --}}
                <input type="hidden" name="report_mode" id="reportMode" value="graduating">

                <div class="col-md-6">
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

                {{-- ============================================= --}}
                {{-- Graduating mode: auto by year level + manual specific students --}}
                {{-- ============================================= --}}
                {{-- Always visible: with the Program Sanction (regular) mode
                     retired there is no mode selector left to toggle this, so
                     hiding it until JS runs would show an incomplete form. --}}
                <div class="col-12" id="graduatingYearsWrap">
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
                                <i class="bi bi-sort-alpha-down me-1"></i>
                                Listed in groups — <strong>Bachelor (Non-Ladderized)</strong>,
                                then <strong>Ladderized</strong>, then <strong>Added Student/s</strong> —
                                and alphabetical by first name within each group.
                            </p>
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
                        <i class="bi bi-stars"></i> Generate Graduation AI Report
                    </button>
                </div>
            </form>
        </div>
    </div>

