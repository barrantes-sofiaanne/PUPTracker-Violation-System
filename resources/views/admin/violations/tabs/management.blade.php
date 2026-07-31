{{-- ===================================================== --}}
{{-- Management Header --}}
{{-- ===================================================== --}}
<div id="managementTableContainer">
<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h5 class="mb-1">

            Student Violation Management

        </h5>

        <small class="text-muted">

            Search students and record violations.

        </small>

    </div>

    <button
        type="button"
        class="btn btn-danger"
        data-bs-toggle="modal"
        data-bs-target="#addViolationModal">

        <i class="bi bi-plus-circle me-1"></i>

        Record Violation

    </button>

</div>
<form
    method="GET"
    action="{{ route('admin.violations.index') }}"
    class="mb-4">

    <div class="row g-3">

        {{-- Search --}}
        <div class="col-lg-4">

            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search Student Number or Name"
                value="{{ request('search') }}">

        </div>

        {{-- Program --}}
        <div class="col-lg-2">

            <select
                name="program"
                class="form-select">

                <option value="">

                    Program

                </option>

                @foreach($programs as $program)

                    <option
                        value="{{ $program->program_id }}"
                        @selected(request('program') == $program->program_id)>

                        {{ $program->program_name }}

                    </option>

                @endforeach

            </select>

        </div>

        {{-- Year --}}
        <div class="col-lg-2">

            <select
                name="year"
                class="form-select">

                <option value="">

                    Year

                </option>

                @foreach($years as $year)

                    <option
                        value="{{ $year->year_id }}"
                        @selected(request('year') == $year->year_id)>

                        {{ $year->year }}

                    </option>

                @endforeach

            </select>

        </div>

        {{-- Section --}}
        <div class="col-lg-2">

            <select
                name="section"
                class="form-select">

                <option value="">

                    Section

                </option>

                @foreach($sections as $section)

                    <option
                        value="{{ $section->section_id }}"
                        @selected(request('section') == $section->section_id)>

                        {{ $section->section_name }}

                    </option>

                @endforeach

            </select>

        </div>

        {{-- Student Status --}}
        <div class="col-lg-2">

            <select
                name="student_status"
                class="form-select">

                <option value="">

                    Status

                </option>

                @foreach($studentStatuses as $status)

                    <option
                        value="{{ $status->student_status_id }}"
                        @selected(request('student_status') == $status->student_status_id)>

                        {{ $status->status_name }}

                    </option>

                @endforeach

            </select>

        </div>

        {{-- From --}}
        <div class="col-lg-2">

            <input
                type="date"
                class="form-control"
                name="from_date"
                value="{{ request('from_date') }}">

        </div>

        {{-- To --}}
        <div class="col-lg-2">

            <input
                type="date"
                class="form-control"
                name="to_date"
                value="{{ request('to_date') }}">

        </div>

        {{-- Search --}}
        <div class="col-lg-2">

            <button
                type="submit"
                class="btn btn-primary w-100">

                <i class="bi bi-search me-1"></i>

                Search

            </button>

        </div>

        {{-- Reset --}}
        <div class="col-lg-2">

            <a
                href="{{ route('admin.violations.index') }}"
                class="btn btn-secondary w-100">

                <i class="bi bi-arrow-clockwise me-1"></i>

                Reset

            </a>

        </div>

    </div>

</form>
<div class="table-responsive">

    <table class="table table-hover align-middle">

        <thead class="table-light">

            <tr>

                <th>Student Number</th>

                <th>Name</th>

                <th>Program</th>

                <th>Total Violations</th>

                <th width="120">

                    Action

                </th>

            </tr>

        </thead>

        <tbody>

            @forelse($students as $student)

                <tr>

                    <td>

                        {{ $student->student_number }}

                    </td>

                    <td>

                        {{ $student->last_name }},
                        {{ $student->first_name }}

                        @if($student->middle_name)

                            {{ $student->middle_name }}

                        @endif

                    </td>

                    <td>

                        {{ $student->studentInfo?->program?->program_name ?? '-' }}

                    </td>

                    <td>

                        <span class="badge bg-danger">

                            {{ $student->violations->count() }}

                        </span>

                    </td>

                    <td>
                        <button
                            type="button"
                            class="btn btn-primary btn-sm viewStudent"
                            data-student="{{ $student->student_number }}">

                            <i class="bi bi-eye me-1"></i>

                            View

                        </button>
                    </td>

                </tr>

            @empty

                <tr>

                    <td
                        colspan="5"
                        class="text-center py-5">

                        No students found.

                    </td>

                </tr>

            @endforelse

        </tbody>

    </table>

</div>

<div class="mt-3">

    {{ $students->appends(request()->except('students_page'))->links() }}

</div>
</div>