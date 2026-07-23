<!-- ========================================================= -->
<!-- Record Violation Modal -->
<!-- ========================================================= -->

<div class="modal fade"
     id="addViolationModal"
     tabindex="-1"
     aria-labelledby="addViolationModalLabel"
     aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-scrollable">

        <div class="modal-content shadow-lg border-0">

            <!-- =============================================== -->
            <!-- Header -->
            <!-- =============================================== -->

            <div class="modal-header bg-danger text-white">

                <div>

                    <h4
                        class="modal-title fw-bold"
                        id="addViolationModalLabel">

                        <i class="bi bi-shield-exclamation me-2"></i>

                        Record Student Violation

                    </h4>

                    <small class="opacity-75">

                        Create a new violation record for a student.

                    </small>

                </div>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <form id="violationForm">

                @csrf

                <input
                    type="hidden"
                    id="selectedStudentNumber"
                    name="student_number">

                <div class="modal-body">

                    <!-- =========================================== -->
                    <!-- Validation -->
                    <!-- =========================================== -->

                    <!-- =========================================== -->
                    <!-- Wizard -->
                    <!-- =========================================== -->

                    <div class="row text-center mb-5">

                        <div class="col">

                            <div
                                id="studentStepIndicator"
                                class="rounded-circle bg-danger text-white mx-auto d-flex align-items-center justify-content-center fw-bold"
                                style="width:50px;height:50px;">

                                1

                            </div>

                            <div class="mt-2 fw-semibold">

                                Student

                            </div>

                        </div>

                        <div class="col">

                            <div
                                id="violationStepIndicator"
                                class="rounded-circle bg-secondary text-white mx-auto d-flex align-items-center justify-content-center fw-bold"
                                style="width:50px;height:50px;">

                                2

                            </div>

                            <div class="mt-2 fw-semibold">

                                Violation

                            </div>

                        </div>

                    </div>

                    <!-- =========================================== -->
                    <!-- STEP 1 -->
                    <!-- =========================================== -->

                    <div id="studentStep">

                        <div class="card border-0 shadow-sm">

                            <div class="card-header bg-white">

                                <h5 class="mb-0">

                                    <i class="bi bi-search me-2 text-danger"></i>

                                    Search Student

                                </h5>

                            </div>

                            <div class="card-body">

                                <div class="position-relative">

                                    <input
                                        id="studentSearch"
                                        type="text"
                                        class="form-control form-control-lg"
                                        placeholder="Search by Student Number or Student Name">

                                    <div
                                        id="studentSearchLoader"
                                        class="spinner-border spinner-border-sm text-danger position-absolute top-50 end-0 translate-middle-y me-3 d-none">
                                    </div>

                                </div>

                                <div
                                    id="studentResults"
                                    class="list-group mt-3">
                                </div>

                            </div>

                        </div>

                        <!-- ======================================= -->
                        <!-- Selected Student -->
                        <!-- ======================================= -->

                        <div class="card border-0 shadow-sm mt-4">

                            <div class="card-header bg-white">

                                <h5 class="mb-0">

                                    <i class="bi bi-person-badge me-2 text-danger"></i>

                                    Selected Student

                                </h5>

                            </div>

                            <div class="card-body">

                                <div
                                    id="noStudentSelected"
                                    class="text-center py-5 text-muted">

                                    <i
                                        class="bi bi-person-x display-5 d-block mb-3">
                                    </i>

                                    No student selected.

                                </div>

                                <div
                                    id="selectedStudentInfo"
                                    class="d-none">

                                    <div class="row g-3">

                                        <div class="col-md-6">

                                            <label class="form-label">

                                                Student Number

                                            </label>

                                            <input
                                                id="studentNumber"
                                                class="form-control"
                                                readonly>

                                        </div>

                                        <div class="col-md-6">

                                            <label class="form-label">

                                                Student Name

                                            </label>

                                            <input
                                                id="studentName"
                                                class="form-control"
                                                readonly>

                                        </div>

                                        <div class="col-md-4">

                                            <label class="form-label">

                                                Program

                                            </label>

                                            <input
                                                id="studentProgram"
                                                class="form-control"
                                                readonly>

                                        </div>

                                        <div class="col-md-4">

                                            <label class="form-label">

                                                Year Level

                                            </label>

                                            <input
                                                id="studentYear"
                                                class="form-control"
                                                readonly>

                                        </div>

                                        <div class="col-md-4">

                                            <label class="form-label">

                                                Section

                                            </label>

                                            <input
                                                id="studentSection"
                                                class="form-control"
                                                readonly>

                                        </div>
                                        <div class="col-md-4">

    <label class="form-label">

        Student Status

    </label>

    <input
        id="studentStatus"
        class="form-control"
        readonly>

</div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- =========================================== -->
                    <!-- STEP 2 STARTS BELOW -->
                    <!-- =========================================== -->

                    <div
                        id="violationStep"
                        class="d-none">
                        <!-- =========================================== -->
<!-- Violation Details -->
<!-- =========================================== -->

<div class="card border-0 shadow-sm">

    <div class="card-header bg-white">

        <h5 class="mb-0">

            <i class="bi bi-exclamation-octagon me-2 text-danger"></i>

            Violation Information

        </h5>

    </div>

    <div class="card-body">

        <div class="row g-4">

            <!-- Category -->

            <div class="col-md-6">

                <label
                    for="violationCategory"
                    class="form-label fw-semibold">

                    Violation Category
                    <span class="text-danger">*</span>

                </label>

               <select
    id="violationCategory"
    name="category_id"
    class="form-select"
    required>

    <option value="">Select Category</option>

    @foreach($categories as $category)
        <option value="{{ $category->violation_category_id }}">
            {{ $category->category_name }} ({{ $category->violation_category_id }})
        </option>
    @endforeach

</select>

            </div>

            <!-- Type -->

            <div class="col-md-6">

                <label
                    for="violationType"
                    class="form-label fw-semibold">

                    Violation Type
                    <span class="text-danger">*</span>

                </label>

                <select
                    id="violationType"
                    name="violation_type"
                    class="form-select"
                    disabled
                    required>

                    <option value="">
                        Select Violation Type
                    </option>

                </select>

            </div>

            <!-- Date -->

            <div class="col-md-6">

                <label
                    for="violationDate"
                    class="form-label fw-semibold">

                    Date & Time
                    <span class="text-danger">*</span>

                </label>

                <input
                    type="datetime-local"
                    id="violationDate"
                    name="violation_date"
                    class="form-control"
                    value="{{ now()->format('Y-m-d\TH:i') }}"
                    required>

            </div>

            <!-- Preview Offense -->

            <div class="col-md-6">

                <label
                    class="form-label fw-semibold">

                    Current Offense Level

                </label>

                <input
                    id="previewOffenseLevel"
                    type="text"
                    class="form-control bg-light"
                    value="-"
                    readonly>

            </div>

            <!-- Description -->

            <div class="col-12">

                <label
                    for="description"
                    class="form-label fw-semibold">

                    Incident Description
                    <span class="text-danger">*</span>

                </label>

                <textarea
                    id="description"
                    name="description"
                    rows="6"
                    maxlength="1000"
                    class="form-control"
                    placeholder="Provide a detailed description of the incident..."
                    required></textarea>

                <div
                    class="d-flex justify-content-end mt-2">

                    <small
                        id="descriptionCounter"
                        class="text-muted">

                        0/1000

                    </small>

                </div>

            </div>

        </div>

    </div>

</div>

<!-- =========================================== -->
<!-- Preview -->
<!-- =========================================== -->

<div class="card border-0 shadow-sm mt-4">

    <div class="card-header bg-white">

        <h5 class="mb-0">

            <i class="bi bi-eye me-2 text-danger"></i>

            Violation Preview

        </h5>

    </div>

    <div class="card-body">

        <div class="row gy-3">

            <div class="col-md-6">

                <label class="text-muted small">

                    Category

                </label>

                <div
                    id="previewCategory"
                    class="fw-semibold">

                    -

                </div>

            </div>

            <div class="col-md-6">

                <label class="text-muted small">

                    Violation

                </label>

                <div
                    id="previewViolationType"
                    class="fw-semibold">

                    -

                </div>

            </div>

            <div class="col-md-6">

                <label class="text-muted small">

                    Offense Level

                </label>

                <div
                    id="previewOffenseLevelCard"
                    class="fw-semibold text-warning">

                    <span id="previewOffenseLevelDisplay">

                        -

                    </span>

                </div>

            </div>

            <div class="col-md-6">

                <label class="text-muted small">

                    Sanction

                </label>

                <div
                    id="previewSanction"
                    class="fw-semibold text-danger">

                    No disciplinary sanction available.

                </div>

            </div>

        </div>

    </div>

</div>
                    </div>
                    <!-- End #violationStep -->

                </div>
                <!-- End Modal Body -->

                <!-- =========================================== -->
                <!-- Footer -->
                <!-- =========================================== -->

                <div class="modal-footer justify-content-between">

                    <button
                        type="button"
                        id="backStep"
                        class="btn btn-outline-secondary d-none">

                        <i class="bi bi-arrow-left me-2"></i>

                        Back

                    </button>

                    <div class="ms-auto">

                        <button
                            type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal">

                            Cancel

                        </button>

                        <button
                            type="button"
                            id="continueStep"
                            class="btn btn-danger"
                            disabled>

                            Continue

                            <i class="bi bi-arrow-right ms-2"></i>

                        </button>

                        <button
                            type="submit"
                            id="saveViolation"
                            class="btn btn-success d-none">

                            <i class="bi bi-check-circle me-2"></i>

                            Record Violation

                        </button>

                    </div>

                </div>

           </form>

        </div>
 
    </div>

</div>

<!-- ========================================================= -->
<!-- Success Toast -->
<!-- ========================================================= -->

<div
    class="toast-container position-fixed bottom-0 end-0 p-3">

    <div
        id="successToast"
        class="toast align-items-center text-bg-success border-0"
        role="alert"
        aria-live="assertive"
        aria-atomic="true">

        <div class="d-flex">

            <div
                id="successToastMessage"
                class="toast-body">

                Violation successfully recorded.

            </div>

            <button
                type="button"
                class="btn-close btn-close-white me-2 m-auto"
                data-bs-dismiss="toast">
            </button>

        </div>

    </div>

</div>