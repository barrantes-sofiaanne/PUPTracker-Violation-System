<div class="modal fade"
     id="addViolationModal"
     tabindex="-1"
     aria-labelledby="addViolationModalLabel"
     aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">

        <div class="modal-content border-0 shadow">

            <form
                id="violationForm"
                method="POST"
                action="{{ route('admin.violations.store') }}">

                @csrf

                <div class="modal-header">

                    <div>

                        <h4
                            class="modal-title fw-bold"
                            id="addViolationModalLabel">

                            Record Student Violation

                        </h4>

                        <small class="text-muted">

                            Search a student and record a disciplinary violation.

                        </small>

                    </div>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <!-- Progress -->

                    <div class="mb-4">

                        <div class="d-flex justify-content-center align-items-center">

                            <div
                                class="step-circle active"
                                id="stepOneIndicator">

                                1

                            </div>

                            <div
                                class="step-line">
                            </div>

                            <div
                                class="step-circle"
                                id="stepTwoIndicator">

                                2

                            </div>

                        </div>

                        <div
                            class="d-flex justify-content-between mt-2">

                            <small>

                                Student

                            </small>

                            <small>

                                Violation

                            </small>

                        </div>

                    </div>

                    <!-- STEP 1 -->

                    <div id="stepOne">

                        <div class="mb-4">

                            <label class="form-label fw-semibold">

                                Search Student

                            </label>

                            <input
                                type="text"
                                id="studentSearch"
                                class="form-control form-control-lg"
                                placeholder="Search student number or full name...">

                        </div>

                        <div
                            id="studentResults"
                            class="list-group">

                        </div>

                    </div>

                    <!-- STEP 2 -->

                    <div
                        id="stepTwo"
                        class="d-none">

                        <div class="row">

                            <!-- LEFT COLUMN -->

                            <div class="col-lg-5">

                                <div class="card border-0 bg-light">

                                    <div class="card-body">

                                        <h5 class="fw-bold mb-3">

                                            Student Information

                                        </h5>

                                        <input
                                            type="hidden"
                                            name="student_number"
                                            id="selectedStudentNumber">

                                        <div class="mb-3">

                                            <label class="form-label">

                                                Student Number

                                            </label>

                                            <input
                                                id="studentNumber"
                                                class="form-control"
                                                readonly>

                                        </div>

                                        <div class="mb-3">

                                            <label class="form-label">

                                                Student Name

                                            </label>

                                            <input
                                                id="studentName"
                                                class="form-control"
                                                readonly>

                                        </div>

                                        <div class="mb-3">

                                            <label class="form-label">

                                                Course

                                            </label>

                                            <input
                                                id="studentCourse"
                                                class="form-control"
                                                readonly>

                                        </div>

                                        <div class="row">

                                            <div class="col">

                                                <label class="form-label">

                                                    Year

                                                </label>

                                                <input
                                                    id="studentYear"
                                                    class="form-control"
                                                    readonly>

                                            </div>

                                            <div class="col">

                                                <label class="form-label">

                                                    Section

                                                </label>

                                                <input
                                                    id="studentSection"
                                                    class="form-control"
                                                    readonly>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>

                            <!-- RIGHT COLUMN -->

                            <div class="col-lg-7">

                                <div class="card border-0">

                                    <div class="card-body">

                                        <h5 class="fw-bold mb-3">

                                            Violation Information

                                        </h5>
                                        {{-- Category --}}
<div class="mb-3">

    <label
        for="violationCategory"
        class="form-label fw-semibold">

        Violation Category
    </label>

    <select
        id="violationCategory"
        class="form-select">

        <option value="">

            Select Category

        </option>

        @foreach($categories as $category)

            <option
                value="{{ $category->category_id }}">

                {{ $category->category }}

            </option>

        @endforeach

    </select>

</div>

{{-- Violation Type --}}
<div class="mb-3">

    <label
        for="violationType"
        class="form-label fw-semibold">

        Violation Type
    </label>

    <select
        id="violationType"
        name="violation_type_id"
        class="form-select"
        disabled>

        <option value="">

            Select Category First

        </option>

    </select>

</div>

{{-- Description --}}
<div class="mb-4">

    <label
        class="form-label fw-semibold">

        Description

    </label>

    <textarea
        name="description"
        id="description"
        rows="4"
        class="form-control"
        placeholder="Enter additional details regarding the incident..."></textarea>

</div>

{{-- Preview Card --}}
<div
    class="card border-primary shadow-sm">

    <div class="card-header bg-primary text-white">

        <strong>

            Violation Preview

        </strong>

    </div>

    <div class="card-body">

        <div class="row">

            <div class="col-md-6 mb-3">

                <label
                    class="text-muted small">

                    Offense Level

                </label>

                <div
                    id="previewOffense"
                    class="fs-5 fw-bold text-warning">

                    —

                </div>

            </div>

            <div class="col-md-6 mb-3">

                <label
                    class="text-muted small">

                    Disciplinary Sanction

                </label>

                <div
                    id="previewSanction"
                    class="fs-6 fw-bold text-danger">

                    —

                </div>

            </div>

        </div>

    </div>

</div>

</div>

</div>

</div>

</div>

<div class="modal-footer">

    <button
        type="button"
        id="backStep"
        class="btn btn-outline-secondary d-none">

        <i class="bi bi-arrow-left"></i>

        Back

    </button>

    <button
        type="button"
        class="btn btn-secondary"
        data-bs-dismiss="modal">

        Cancel

    </button>

    <button
        type="button"
        id="continueStep"
        class="btn btn-primary"
        disabled>

        Continue

        <i class="bi bi-arrow-right"></i>

    </button>

    <button
        type="submit"
        id="saveViolation"
        class="btn btn-danger d-none">

        <i class="bi bi-save"></i>

        Record Violation

    </button>

</div>

</form>

</div>

</div>

</div>