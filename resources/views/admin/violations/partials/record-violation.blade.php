<!-- ========================================================= -->
<!-- Record Violation Modal -->
<!-- ========================================================= -->

<style>
    .admin-record-modal .modal-dialog {
        max-width: 1160px;
    }

    .admin-record-modal .modal-content {
        border: 1px solid rgba(128, 0, 0, 0.12) !important;
        border-radius: 1rem !important;
        max-height: calc(100vh - 2rem);
    }

    .admin-record-modal .modal-header {
        background: linear-gradient(120deg, #800000 0%, #5f0000 65%, #daa520 180%) !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        color: #fff;
    }

    .admin-record-modal .modal-header .modal-title,
    .admin-record-modal .modal-header .modal-title i,
    .admin-record-modal .modal-header small {
        color: #fff !important;
    }

    .admin-record-modal .modal-body {
        background: linear-gradient(180deg, #fffefa 0%, #fff7e3 100%);
        max-height: calc(100vh - 220px);
        overflow-y: auto;
    }

    .admin-record-modal .modal-footer {
        background: #fffdf6;
        border-top: 1px solid rgba(128, 0, 0, 0.12);
    }

    .admin-record-modal .card {
        border: 1px solid rgba(128, 0, 0, 0.12) !important;
        border-radius: 0.95rem;
        box-shadow: 0 8px 20px rgba(128, 0, 0, 0.08) !important;
        overflow: hidden;
    }

    .admin-record-modal .card-header {
        background: linear-gradient(180deg, #fffefa 0%, #fff8e8 100%) !important;
        border-bottom: 1px solid rgba(128, 0, 0, 0.12);
        color: #5f0000;
    }

    .admin-record-modal .form-control,
    .admin-record-modal .form-select {
        border-color: rgba(128, 0, 0, 0.2);
        border-radius: 0.72rem;
    }

    .admin-record-modal .form-control:focus,
    .admin-record-modal .form-select:focus {
        border-color: rgba(128, 0, 0, 0.5);
        box-shadow: 0 0 0 0.2rem rgba(128, 0, 0, 0.12);
    }
</style>

<div class="modal fade admin-record-modal"
     id="addViolationModal"
     tabindex="-1"
     aria-labelledby="addViolationModalLabel"
     aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-scrollable">

        <div class="modal-content shadow-lg border-0 rounded-4 overflow-hidden">

            <!-- =============================================== -->
            <!-- Header -->
            <!-- =============================================== -->

            <div class="modal-header bg-danger text-white py-3">

                <div>

                    <h4
                        class="modal-title fw-bold"
                        id="addViolationModalLabel">

                        <i class="bi bi-shield-exclamation me-2"></i>

                        Record Student Violation

                    </h4>

                    <small class="opacity-75 d-block mt-1">

                        Create a new violation record for a student.

                    </small>

                </div>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                    aria-label="Close">
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

                    <div class="row text-center mb-4 g-2">

                        <div class="col">

                            <div
                                id="studentStepIndicator"
                                class="rounded-circle bg-danger text-white mx-auto d-flex align-items-center justify-content-center fw-bold"
                                style="width:46px;height:46px;">

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
                                style="width:46px;height:46px;">

                                2

                            </div>

                            <div class="mt-2 fw-semibold">

                                Violation

                            </div>

                        </div>

                        <div class="col">

                            <div
                                id="evidenceStepIndicator"
                                class="rounded-circle bg-secondary text-white mx-auto d-flex align-items-center justify-content-center fw-bold"
                                style="width:46px;height:46px;">

                                3

                            </div>

                            <div class="mt-2 fw-semibold">

                                Evidence

                            </div>

                        </div>

                        <div class="col">

                            <div
                                id="reviewStepIndicator"
                                class="rounded-circle bg-secondary text-white mx-auto d-flex align-items-center justify-content-center fw-bold"
                                style="width:46px;height:46px;">

                                4

                            </div>

                            <div class="mt-2 fw-semibold">

                                Review

                            </div>

                        </div>

                    </div>

                    <div class="progress mb-4" style="height: 7px;">
                        <div
                            id="violationWizardProgress"
                            class="progress-bar bg-danger"
                            role="progressbar"
                            style="width: 25%;"
                            aria-valuenow="25"
                            aria-valuemin="0"
                            aria-valuemax="100">
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

                    <!-- =========================================== -->
                    <!-- STEP 3 : EVIDENCE -->
                    <!-- =========================================== -->

                    <div id="evidenceStep" class="d-none">

                        <div class="card border-0 shadow-sm">

                            <div
                                class="card-header bg-white d-flex justify-content-between align-items-center">

                                <h5 class="mb-0">

                                    <i class="bi bi-shield-lock me-2 text-danger"></i>

                                    Evidence

                                </h5>

                                <span
                                    class="badge bg-secondary"
                                    id="evidenceCountBadge">

                                    0 / 5 files

                                </span>

                            </div>

                            <div class="card-body">

                                <div
                                    class="alert alert-warning small mb-3">

                                    <i class="bi bi-shield-exclamation me-1"></i>

                                    <strong>Protected capture:</strong>
                                    photos must be taken through the PUPTracker camera interface so a permanent
                                    watermark can be applied. Photo capture is monitored for suspicious
                                    interruptions (screen capture, focus loss) as a deterrent — these are
                                    recorded as security events requiring review. PDF documents use the secure
                                    upload path. All files are stored privately.
                                </div>

                                <div class="row g-3">

                                    {{-- Camera capture --}}
                                    <div class="col-lg-7">

                                        <div class="border rounded-3 p-3 h-100 bg-white">

                                            <div class="d-flex align-items-center mb-2">

                                                <i class="bi bi-camera-fill fs-4 me-2 text-danger"></i>

                                                <div>

                                                    <strong>Capture Photo</strong>

                                                    <div class="small text-muted">

                                                        Live capture with watermark

                                                    </div>

                                                </div>

                                            </div>

                                            {{-- Camera idle --}}
                                            <div
                                                id="cameraIdlePanel"
                                                class="text-center py-4 text-muted">

                                                <i class="bi bi-camera-video-off display-5 d-block mb-2"></i>

                                                <p class="small mb-3">

                                                    Camera is off. A security notice will be shown before the camera starts.

                                                </p>

                                                <button
                                                    type="button"
                                                    id="openCameraBtn"
                                                    class="admin-btn admin-btn-primary btn-sm">

                                                    <i class="bi bi-camera-video me-1"></i>

                                                    Open Camera

                                                </button>

                                            </div>

                                            {{-- Camera live --}}
                                            <div
                                                id="cameraLivePanel"
                                                class="d-none">

                                                <div
                                                    id="cameraWrap"
                                                    class="position-relative rounded-3 overflow-hidden bg-dark"
                                                    style="aspect-ratio:4/3;">

                                                    <video
                                                        id="cameraPreview"
                                                        autoplay
                                                        playsinline
                                                        muted
                                                        class="w-100 h-100 object-fit-cover"></video>

                                                    <button
                                                        type="button"
                                                        id="flipCameraBtn"
                                                        class="position-absolute top-0 end-0 m-2 btn btn-sm btn-light rounded-circle d-none align-items-center justify-content-center"
                                                        style="width:40px;height:40px;box-shadow:0 2px 8px rgba(0,0,0,0.4);z-index:5;"
                                                        title="Switch camera (front/back)"
                                                        aria-label="Switch camera">

                                                        <i class="bi bi-arrow-repeat" aria-hidden="true"></i>

                                                    </button>

                                                    <div
                                                        id="liveWatermarkOverlay"
                                                        class="position-absolute bottom-0 start-0 end-0 px-2 py-1 text-white small"
                                                        style="background:rgba(0,0,0,0.55);font-family:ui-monospace,monospace;pointer-events:none;">

                                                        PUPTracker &nbsp;|&nbsp;
                                                        <span id="liveWatermarkStudent">&mdash;</span> &nbsp;|&nbsp;
                                                        <span id="liveWatermarkTime">--/--/---- --:--</span>

                                                    </div>

                                                    <div
                                                        id="cameraInterruptedOverlay"
                                                        class="position-absolute top-0 start-0 end-0 bottom-0 d-none align-items-center justify-content-center text-white"
                                                        style="background:rgba(122,15,30,0.85);">

                                                        <div class="text-center px-3">

                                                            <i class="bi bi-eye-slash display-6 d-block mb-2"></i>

                                                            <strong>Camera paused</strong>

                                                            <div class="small">

                                                                Possible screen capture or browser interruption was detected. Return to this window to continue.

                                                            </div>

                                                        </div>

                                                    </div>

                                                </div>

                                                <div class="d-flex align-items-center justify-content-between mt-2">

                                                    <span class="small text-muted">

                                                        <i class="bi bi-shield-check me-1"></i>

                                                        Watermark will be applied on capture

                                                    </span>

                                                    <div class="d-flex gap-2">

                                                        <button
                                                            type="button"
                                                            id="capturePhotoBtn"
                                                            class="admin-btn admin-btn-primary btn-sm"
                                                            disabled>

                                                            <i class="bi bi-camera me-1"></i>

                                                            Capture Photo

                                                        </button>

                                                        <button
                                                            type="button"
                                                            id="closeCameraBtn"
                                                            class="admin-btn admin-btn-neutral btn-sm">

                                                            <i class="bi bi-x-lg me-1"></i>

                                                            Stop

                                                        </button>

                                                    </div>

                                                </div>

                                            </div>

                                            {{-- Capture review --}}
                                            <div
                                                id="captureReviewPanel"
                                                class="d-none">

                                                <div class="rounded-3 overflow-hidden bg-dark mb-2">

                                                    <img
                                                        id="captureReviewImg"
                                                        alt="Captured photo preview"
                                                        class="w-100 d-block">

                                                </div>

                                                <div class="small text-muted mb-2">

                                                    <div>

                                                        Evidence ID:
                                                        <strong id="captureReviewCode">assigned on submit</strong>

                                                    </div>

                                                    <div>

                                                        Type:
                                                        <span id="captureReviewType">JPEG</span>
                                                        &middot;
                                                        Size:
                                                        <span id="captureReviewSize">0 KB</span>

                                                    </div>

                                                </div>

                                                <div class="d-flex gap-2">

                                                    <button
                                                        type="button"
                                                        id="retakePhotoBtn"
                                                        class="admin-btn admin-btn-neutral btn-sm">

                                                        <i class="bi bi-arrow-counterclockwise me-1"></i>

                                                        Retake

                                                    </button>

                                                    <button
                                                        type="button"
                                                        id="usePhotoBtn"
                                                        class="admin-btn admin-btn-primary btn-sm">

                                                        <i class="bi bi-check-lg me-1"></i>

                                                        Use Photo

                                                    </button>

                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                    {{-- PDF upload --}}
                                    <div class="col-lg-5">

                                        <div class="border rounded-3 p-3 h-100 bg-white">

                                            <div class="d-flex align-items-center mb-2">

                                                <i class="bi bi-file-earmark-pdf-fill fs-4 me-2" style="color:#b91c1c;"></i>

                                                <div>

                                                    <strong>Upload PDF Document</strong>

                                                    <div class="small text-muted">

                                                        Secure private upload

                                                    </div>

                                                </div>

                                            </div>

                                            <p class="small text-muted">

                                                For document-type evidence (e.g. written reports). Photos must be captured with the camera above.

                                            </p>

                                            <input
                                                type="file"
                                                id="evidenceInput"
                                                name="evidence[]"
                                                class="form-control"
                                                accept=".pdf,application/pdf">

                                            <small class="text-muted">

                                                Allowed: PDF &mdash; max 5 MB each, up to 5 total files.

                                            </small>

                                        </div>

                                    </div>

                                </div>

                                <hr>

                                <label class="form-label fw-semibold">

                                    Staged Evidence

                                </label>

                                <div
                                    id="evidencePreviewList"
                                    class="d-flex flex-wrap gap-2"></div>

                                <p class="text-muted small mb-0 mt-2">

                                    Each file is stored privately with its own unique Evidence ID (EVD-&hellip;) and is never overwritten.

                                </p>

                            </div>

                        </div>

                    </div>

                    <!-- =========================================== -->
                    <!-- STEP 4 : REVIEW -->
                    <!-- =========================================== -->

                    <div id="reviewStep" class="d-none">

                        <div class="card border-0 shadow-sm">

                            <div class="card-header bg-white">

                                <h5 class="mb-0">

                                    <i class="bi bi-clipboard-check me-2 text-danger"></i>

                                    Review &amp; Submit

                                </h5>

                            </div>

                            <div class="card-body">

                                <div class="row g-3">

                                    <div class="col-md-6">

                                        <div class="border rounded-3 p-3 bg-white h-100">

                                            <h6 class="fw-bold mb-2">

                                                <i class="bi bi-person-badge me-1 text-danger"></i>

                                                Student

                                            </h6>

                                            <div class="small mb-1">

                                                <strong>Number:</strong>
                                                <span id="reviewStudentNumber">&mdash;</span>

                                            </div>

                                            <div class="small mb-1">

                                                <strong>Name:</strong>
                                                <span id="reviewStudentName">&mdash;</span>

                                            </div>

                                            <div class="small mb-1">

                                                <strong>Program:</strong>
                                                <span id="reviewStudentProgram">&mdash;</span>

                                            </div>

                                            <div class="small mb-1">

                                                <strong>Year:</strong>
                                                <span id="reviewStudentYear">&mdash;</span>

                                            </div>

                                            <div class="small">

                                                <strong>Section:</strong>
                                                <span id="reviewStudentSection">&mdash;</span>

                                            </div>

                                        </div>

                                    </div>

                                    <div class="col-md-6">

                                        <div class="border rounded-3 p-3 bg-white h-100">

                                            <h6 class="fw-bold mb-2">

                                                <i class="bi bi-exclamation-octagon me-1 text-danger"></i>

                                                Violation

                                            </h6>

                                            <div class="small mb-1">

                                                <strong>Category:</strong>
                                                <span id="reviewCategory">&mdash;</span>

                                            </div>

                                            <div class="small mb-1">

                                                <strong>Type:</strong>
                                                <span id="reviewViolationType">&mdash;</span>

                                            </div>

                                            <div class="small mb-1">

                                                <strong>Date &amp; Time:</strong>
                                                <span id="reviewDate">&mdash;</span>

                                            </div>

                                            <div class="small mb-1">

                                                <strong>Offense Level:</strong>
                                                <span id="reviewOffenseLevel">&mdash;</span>

                                            </div>

                                            <div class="small">

                                                <strong>Sanction:</strong>
                                                <span id="reviewSanction">&mdash;</span>

                                            </div>

                                        </div>

                                    </div>

                                    <div class="col-12">

                                        <div class="border rounded-3 p-3 bg-white">

                                            <h6 class="fw-bold mb-2">

                                                <i class="bi bi-chat-left-text me-1 text-danger"></i>

                                                Description

                                            </h6>

                                            <div
                                                class="small"
                                                id="reviewDescription"
                                                style="white-space:pre-line;">

                                                &mdash;

                                            </div>

                                        </div>

                                    </div>

                                    <div class="col-12">

                                        <div class="border rounded-3 p-3 bg-white">

                                            <h6 class="fw-bold mb-2">

                                                <i class="bi bi-paperclip me-1 text-danger"></i>

                                                Evidence

                                            </h6>

                                            <div
                                                class="small"
                                                id="reviewEvidenceSummary">

                                                No evidence attached.

                                            </div>

                                        </div>

                                    </div>

                                    <div class="col-12">

                                        <div class="border rounded-3 p-3 bg-white">

                                            <h6 class="fw-bold mb-2">

                                                <i class="bi bi-person-check me-1 text-danger"></i>

                                                Recorded By

                                            </h6>

                                            <div
                                                class="small"
                                                id="reviewRecordedBy">

                                                {{ trim(
                                                    (string) (auth('admin')->user()?->adminInfo?->firstname ?? '') . ' ' .
                                                    (string) (auth('admin')->user()?->adminInfo?->middlename ?? '') . ' ' .
                                                    (string) (auth('admin')->user()?->adminInfo?->lastname ?? '')
                                                ) ?: (auth('admin')->user()?->email ?? 'Current Administrator') }}

                                            </div>

                                        </div>

                                    </div>

                                </div>

                                <div class="alert alert-info small mt-3 mb-0">

                                    <i class="bi bi-shield-check me-1"></i>

                                    The violation will be recorded immediately as confirmed and the student will be notified. Review everything before submitting.

                                </div>

                            </div>

                        </div>

                    </div>

                </div>
                <!-- End Modal Body -->

                <!-- =========================================== -->
                <!-- Footer -->
                <!-- =========================================== -->

                <div class="modal-footer justify-content-between">

                    <button
                        type="button"
                        id="backStep"
                        class="admin-btn admin-btn-neutral d-none">

                        <i class="bi bi-arrow-left me-2"></i>

                        Back

                    </button>

                    <div class="ms-auto">

                        <button
                            type="button"
                            class="admin-btn admin-btn-neutral"
                            data-bs-dismiss="modal"
                            id="cancelViolationBtn">

                            Cancel

                        </button>

                        <button
                            type="button"
                            id="continueStep"
                            class="admin-btn admin-btn-primary">

                            Continue

                            <i class="bi bi-arrow-right ms-2"></i>

                        </button>

                        <button
                            type="button"
                            id="saveViolation"
                            class="admin-btn admin-btn-success d-none">

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
<!-- Security Notice (camera gate) -->
<!-- ========================================================= -->

<div
    class="modal fade"
    id="securityNoticeModal"
    tabindex="-1"
    aria-labelledby="securityNoticeModalLabel"
    aria-hidden="true"
    data-bs-backdrop="static"
    data-bs-keyboard="false">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content shadow-lg border-0 rounded-4">

            <div class="modal-header bg-danger text-white">

                <h5
                    class="modal-title fw-bold"
                    id="securityNoticeModalLabel">

                    <i class="bi bi-shield-lock me-2"></i>

                    Evidence Capture Notice

                </h5>

            </div>

            <div class="modal-body">

                <div class="alert alert-warning small mb-3">

                    <i class="bi bi-exclamation-triangle me-1"></i>

                    Photo evidence must be captured live through the PUPTracker camera. A permanent watermark will be applied, and capture is monitored for suspicious interruptions.
                </div>

                <p class="small text-muted">

                    By starting the camera you agree to the controlled evidence capture policy.
                </p>

                <div class="form-check">

                    <input
                        type="checkbox"
                        class="form-check-input"
                        id="securityNoticeAck">

                    <label
                        class="form-check-label"
                        for="securityNoticeAck">

                        I understand the security notice.

                    </label>

                </div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="admin-btn admin-btn-neutral"
                    id="securityNoticeCancelBtn">

                    Cancel

                </button>

                <button
                    type="button"
                    class="admin-btn admin-btn-primary"
                    id="securityNoticeStartBtn"
                    disabled>

                    <i class="bi bi-camera-video me-1"></i>

                    Start Camera

                </button>

            </div>

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
                data-bs-dismiss="toast"
                aria-label="Close">
            </button>

        </div>

    </div>

</div>