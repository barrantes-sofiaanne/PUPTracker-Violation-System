<style>
    .security-record-modal .modal-dialog {
        max-width: 1160px;
    }

    .security-record-modal .modal-content {
        border-radius: 1rem;
        overflow: hidden;
        max-height: calc(100vh - 2rem);
    }

    .security-record-modal .modal-header {
        background: linear-gradient(135deg, #800000 0%, #5f0000 100%);
        border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        padding: 1rem 1.2rem;
    }

    .security-record-modal .modal-body {
        background: linear-gradient(180deg, #fffefa 0%, #fff7e3 100%);
        overflow-y: auto;
        max-height: calc(100vh - 220px);
    }

    .security-record-modal .modal-footer {
        background: #fffdf6;
        border-top: 1px solid rgba(128, 0, 0, 0.12);
        padding: 0.8rem 1rem;
    }

    .security-record-modal .wizard-bubble {
        width: 52px;
        height: 52px;
        border-radius: 999px;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 6px 12px rgba(128, 0, 0, 0.15);
    }

    .security-record-modal .wizard-card {
        border: 1px solid rgba(128, 0, 0, 0.12);
        border-radius: 0.95rem;
        box-shadow: 0 8px 20px rgba(128, 0, 0, 0.08);
    }

    .security-record-modal .wizard-card .card-header {
        background: linear-gradient(180deg, #fffefa 0%, #fff5d9 100%);
        border-bottom: 1px solid rgba(128, 0, 0, 0.12);
    }

    .security-record-modal .preview-box {
        border: 1px solid rgba(128, 0, 0, 0.1);
        border-radius: 0.9rem;
        background: #fffdf7;
    }

    .security-record-modal .preview-box strong {
        color: #5f0000;
    }
</style>

<div class="modal fade security-record-modal"
     id="recordViolationModal"
     tabindex="-1"
     aria-labelledby="recordViolationModalLabel"
     aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-scrollable">

        <div class="modal-content border-0 shadow-lg">

            <div class="modal-header text-white">
                <div>
                    <h4 class="modal-title fw-bold" id="recordViolationModalLabel">
                        <i class="bi bi-shield-exclamation me-2"></i>
                        Record Student Violation
                    </h4>
                    <small class="opacity-75">Search a student and record a disciplinary violation.</small>
                </div>

                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form id="violationForm">
                @csrf

                <input type="hidden" id="selectedStudentNumber" name="student_number">

                <div class="modal-body">
                    <div class="row text-center mb-4">
                        <div class="col">
                            <div id="studentStepIndicator" class="wizard-bubble bg-danger text-white mx-auto">1</div>
                            <div class="mt-2 fw-semibold">Student</div>
                        </div>
                        <div class="col">
                            <div id="violationStepIndicator" class="wizard-bubble bg-secondary text-white mx-auto">2</div>
                            <div class="mt-2 fw-semibold">Violation</div>
                        </div>
                    </div>

                    <div id="studentStep">
                        <div class="card wizard-card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-search me-2" style="color:#800000;"></i>Search Student</h5>
                            </div>
                            <div class="card-body">
                                <div class="position-relative">
                                    <input id="studentSearch" type="text" class="form-control form-control-lg" placeholder="Search by Student Number or Student Name">
                                </div>

                                <div id="studentResults" class="list-group mt-3"></div>
                            </div>
                        </div>

                        <div class="card wizard-card mt-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-person-badge me-2" style="color:#800000;"></i>Selected Student</h5>
                            </div>
                            <div class="card-body">
                                <div id="noStudentSelected" class="text-center py-5 text-muted">
                                    <i class="bi bi-person-x display-5 d-block mb-3"></i>
                                    No student selected.
                                </div>

                                <div id="selectedStudentInfo" class="d-none">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Student Number</label>
                                            <input id="studentNumber" class="form-control" readonly>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Student Name</label>
                                            <input id="studentName" class="form-control" readonly>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Program</label>
                                            <input id="studentProgram" class="form-control" readonly>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Year Level</label>
                                            <input id="studentYear" class="form-control" readonly>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Section</label>
                                            <input id="studentSection" class="form-control" readonly>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Student Status</label>
                                            <input id="studentStatus" class="form-control" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="violationStep" class="d-none">
                        <div class="card wizard-card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-exclamation-octagon me-2" style="color:#800000;"></i>Violation Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label for="violationCategory" class="form-label fw-semibold">Violation Category <span class="text-danger">*</span></label>
                                        <select id="violationCategory" class="form-select">
                                            <option value="">Select Category</option>
                                            @foreach($categories as $category)
                                                <option value="{{ $category->violation_category_id }}">{{ $category->category_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="violationType" class="form-label fw-semibold">Violation Type <span class="text-danger">*</span></label>
                                        <select id="violationType" class="form-select" disabled>
                                            <option value="">Select Category First</option>
                                        </select>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Violation Date <span class="text-danger">*</span></label>
                                        <input id="violationDate" type="datetime-local" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}">
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                                        <textarea id="description" class="form-control" rows="4" placeholder="Enter violation description"></textarea>
                                    </div>

                                    <div class="col-12">
                                        <div class="preview-box">
                                            <div class="card-body">
                                                <h6 class="fw-bold mb-3">Preview</h6>
                                                <div class="row g-3">
                                                    <div class="col-md-4">
                                                        <small class="text-muted d-block">Category</small>
                                                        <strong id="previewCategory">-</strong>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <small class="text-muted d-block">Violation Type</small>
                                                        <strong id="previewViolationType">-</strong>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <small class="text-muted d-block">Offense Level</small>
                                                        <strong id="previewOffenseLevelDisplay">-</strong>
                                                        <input type="hidden" id="previewOffenseLevel">
                                                    </div>
                                                    <div class="col-12">
                                                        <small class="text-muted d-block">Sanction</small>
                                                        <strong id="previewSanction">-</strong>
                                                    </div>
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
                    <button type="button" class="btn btn-outline-secondary d-none" id="backStep">Back</button>
                    <button type="button" class="btn btn-danger" id="continueStep" disabled>Continue</button>
                    <button type="button" class="btn btn-danger d-none" id="saveViolation">Save Violation</button>
                </div>
            </form>
        </div>
    </div>
</div>