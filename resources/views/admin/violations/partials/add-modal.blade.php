<div class="modal fade"
     id="addViolationModal"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-lg">

        <form action="{{ route('admin.violations.store') }}"
              method="POST">

            @csrf

            <div class="modal-content">

                <div class="modal-header">

                    <h5 class="modal-title">

                        Record Student Violation

                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <div class="row">

                        {{-- Student --}}
                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Student
                            </label>

                            <select
                                id="studentSelect"
                                name="student_number"
                                class="form-select"
                                required>

                                <option value="">
                                    Select Student
                                </option>

                                @foreach($allStudents as $student)

                                    <option
                                        value="{{ $student->student_number }}"
                                        data-name="{{ $student->first_name }} {{ $student->last_name }}"
                                        data-course="{{ $student->course?->course_name }}"
                                        data-year="{{ $student->year?->year }}"
                                        data-section="{{ $student->section?->section_name }}">

                                        {{ $student->student_number }}
                                        -
                                        {{ $student->last_name }},
                                        {{ $student->first_name }}

                                    </option>

                                @endforeach

                            </select>

                        </div>

                        {{-- Violation --}}
                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                Violation Type

                            </label>

                            <select
                                name="violation_type"
                                class="form-select"
                                required>

                                <option value="">
                                    Select Violation
                                </option>

                                @foreach($violationTypes as $type)

                                    <option
                                        value="{{ $type->violation_type }}">

                                        {{ $type->violation_type }}

                                    </option>

                                @endforeach

                            </select>

                        </div>

                    </div>

                    <div class="row">

                        <div class="col-md-4">

                            <label>Name</label>

                            <input
                                id="studentName"
                                class="form-control"
                                readonly>

                        </div>

                        <div class="col-md-3">

                            <label>Course</label>

                            <input
                                id="studentCourse"
                                class="form-control"
                                readonly>

                        </div>

                        <div class="col-md-2">

                            <label>Year</label>

                            <input
                                id="studentYear"
                                class="form-control"
                                readonly>

                        </div>

                        <div class="col-md-3">

                            <label>Section</label>

                            <input
                                id="studentSection"
                                class="form-control"
                                readonly>

                        </div>

                    </div>

                    <div class="mt-3">

                        <label>Description</label>

                        <textarea
                            name="description"
                            class="form-control"
                            rows="4"></textarea>

                    </div>

                </div>
<hr>

<div class="row">

    <div class="col-md-6">

        <label class="fw-bold">

            Offense Level

        </label>

        <input
            id="offenseLevel"
            class="form-control"
            readonly>

    </div>

    <div class="col-md-6">

        <label class="fw-bold">

            Disciplinary Sanction

        </label>

        <textarea
            id="disciplinarySanction"
            class="form-control"
            rows="2"
            readonly></textarea>

    </div>

</div>
                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">

                        Cancel

                    </button>

                    <button
                        class="btn btn-danger">

                        Record Violation

                    </button>

                </div>

            </div>

        </form>

    </div>

</div>