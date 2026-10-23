<style>
    .security-record-modal .modal-dialog {
        max-width: 1160px;
    }

    .security-record-modal .modal-content {
        border: 1px solid rgba(122, 15, 30, 0.12);
        border-radius: var(--portal-radius-lg);
        overflow: hidden;
        max-height: calc(100vh - 2rem);
    }

.security-record-modal .modal-header {
    position: relative;
    background: #800000;
    border-bottom: 1px solid rgba(255, 255, 255, 0.15);
    color: #fff;
    padding: 1.1rem 4.5rem 1.1rem 1.25rem;
}

.security-record-modal .modal-header .btn-close {
    position: absolute;
    top: 50%;
    right: 1rem;
    transform: translateY(-50%);

    width: 44px;
    height: 44px;

    margin: 0 !important;
    padding: 0;

    z-index: 10;

    background-color: transparent !important;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 1 1-1.414 1.414L8 9.414l-6.293 6.293-1.414 1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414-1.414z'/%3e%3c/svg%3e") !important;

    background-repeat: no-repeat !important;
    background-position: center !important;
    background-size: 1rem auto !important;

    filter: none !important;
    opacity: 0.9;
}

.security-record-modal .modal-header .btn-close:hover,
.security-record-modal .modal-header .btn-close:focus-visible {
    opacity: 1;
}

    .security-record-modal .modal-header .modal-title,
    .security-record-modal .modal-header .modal-title i,
    .security-record-modal .modal-header small {
        color: #fff !important;
    }

    .security-record-modal .modal-body {
background: #ffffff;
        overflow-y: auto;
        max-height: calc(100vh - 220px);
    }

    .security-record-modal .modal-footer {
        background: #fffdf6;
        border-top: 1px solid rgba(122, 15, 30, 0.12);
        padding: 0.8rem 1rem;
        /* Wrap rather than clip — buttons must never be cut off or overlap. */
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
    }

    /* The body scrolls independently so a tall step never pushes the header or
       footer out of view. */
    .security-record-modal .modal-body {
        overflow-y: auto;
        overscroll-behavior: contain;
    }

    .security-record-modal .modal-content {
        max-height: calc(100vh - 1rem);
    }

    /* Small screens: the primary CTA takes its own full-width row. */
    @media (max-width: 575.98px) {
        .security-record-modal .modal-dialog {
            margin: 0.5rem;
        }

        .security-record-modal .modal-footer > .ms-auto {
            margin-left: 0 !important;
            width: 100%;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .security-record-modal .modal-footer > .ms-auto > .portal-btn,
        .security-record-modal .modal-footer > .ms-auto > .admin-btn {
            flex: 1 1 auto;
        }

        .security-record-modal .modal-footer #saveViolation {
            order: 3;
            flex: 1 1 100%;
        }
    }

    .security-record-modal .wizard-bubble {
        width: 52px;
        height: 52px;
        border-radius: 999px;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 6px 12px rgba(122, 15, 30, 0.15);
        border: 2px solid transparent;
        transition: background 0.15s ease, border-color 0.15s ease;
    }

    /* ------------------------------------------------------------------
       Wizard step states — PUPTracker MAROON + GOLD ONLY.

       These previously used Bootstrap's bg-success (GREEN) and bg-danger
       (Bootstrap red). Replaced with palette-only states so the Security
       wizard matches the Admin wizard exactly.
       ------------------------------------------------------------------ */
    .security-record-modal .wizard-bubble.wizard-step-current {
        background: #7a0f1e;
        border-color: #7a0f1e;
        color: #fff;
        box-shadow: 0 6px 14px rgba(122, 15, 30, 0.3);
    }

    .security-record-modal .wizard-bubble.wizard-step-completed {
        background: #daa520;
        border-color: #daa520;
        color: #4a0209;
    }

    .security-record-modal .wizard-bubble.wizard-step-upcoming {
        background: #fff8e8;
        border-color: rgba(122, 15, 30, 0.28);
        color: #7a0f1e;
    }

    .security-record-modal .wizard-card {
        border: 1px solid rgba(122, 15, 30, 0.12);
        border-radius: var(--portal-radius-lg);
        box-shadow: var(--portal-shadow);
    }

    .security-record-modal .wizard-card .card-header {
        background: linear-gradient(180deg, #fffefa 0%, #fff8e8 100%);
        border-bottom: 1px solid rgba(122, 15, 30, 0.12);
        color: #4a0209;
        padding: 0.9rem 1.1rem;
    }

    .security-record-modal .wizard-card .card-header i {
        color: #7a0f1e !important;
    }

    .security-record-modal .form-control,
    .security-record-modal .form-select {
        border-color: rgba(122, 15, 30, 0.2);
        border-radius: 0.72rem;
    }

    .security-record-modal .form-control:focus,
    .security-record-modal .form-select:focus {
        border-color: rgba(122, 15, 30, 0.5);
        box-shadow: 0 0 0 0.2rem rgba(122, 15, 30, 0.12);
    }

    .security-record-modal .preview-box {
        border: 1px solid rgba(122, 15, 30, 0.1);
        border-radius: 0.9rem;
        background: #fffdf7;
    }

    .security-record-modal .preview-box strong {
        color: #4a0209;
    }

    /* Controlled camera capture */
    .security-record-modal #cameraPreview {
        filter: none;
        transition: filter 0.15s ease;
        background: #000;
    }

    .security-record-modal #cameraPreview.camera-obscured {
        filter: blur(18px) brightness(0.5);
    }

    .security-record-modal #cameraWrap.video-interrupted #cameraPreview {
        filter: blur(18px) brightness(0.5);
    }

    .security-notice-modal .modal-content {
        border-radius: 1rem;
    }

    /* Disabled buttons stay readable — keep the maroon/gold tone visible. */
    .security-record-modal .modal-footer .btn:disabled,
    .security-record-modal .modal-footer .btn.disabled {
        opacity: 0.75;
        filter: saturate(0.85);
        cursor: not-allowed;
    }

    .security-record-modal .modal-footer .portal-btn:disabled {
        color: #fff;
        background: linear-gradient(135deg, #a24a55 0%, #8a3a44 100%);
        border-color: #a24a55;
        box-shadow: none;
    }


/* Unified Record Violation footer: keep Back and Next/Submit side-by-side on phones. */
@media (max-width: 575.98px) {
  .admin-record-modal .modal-footer,
  .oss-staff-record-modal .modal-footer,
  .security-record-modal .modal-footer {
    display: flex; flex-wrap: nowrap; align-items: center; justify-content: flex-end; gap: .5rem;
    padding: .65rem max(.65rem, env(safe-area-inset-right)) calc(.65rem + env(safe-area-inset-bottom)) max(.65rem, env(safe-area-inset-left));
  }
  .admin-record-modal .modal-footer #backStep,
  .oss-staff-record-modal .modal-footer #backStep,
  .security-record-modal .modal-footer #backStep,
  .admin-record-modal .modal-footer #nextStep,
  .oss-staff-record-modal .modal-footer #nextStep,
  .security-record-modal .modal-footer #nextStep,
  .admin-record-modal .modal-footer #saveViolation,
  .oss-staff-record-modal .modal-footer #saveViolation,
  .security-record-modal .modal-footer #saveViolation {
    flex: 0 1 auto !important; width: auto !important; min-width: 0; max-width: 100%; white-space: nowrap;
  }
  .admin-record-modal .modal-footer #saveViolation,
  .oss-staff-record-modal .modal-footer #saveViolation,
  .security-record-modal .modal-footer #saveViolation { order: 0 !important; }
}


/* Final responsive override: Record Violation modal (mobile/tablet safe area).
   Keep the header and action buttons visible; only the form content scrolls. */
@supports (height: 100dvh) {
  .security-record-modal.modal-content { max-height: calc(100dvh - 1rem); }
}
.security-record-modal .modal-content {
  display: flex;
  flex-direction: column;
  min-height: 0;
  overflow: hidden;
}
.security-record-modal .modal-header,
.security-record-modal .modal-footer {
  flex: 0 0 auto;
  position: relative;
  z-index: 2;
}
.security-record-modal .modal-body {
  flex: 1 1 auto;
  min-height: 0;
  max-height: none;
  overflow-y: auto !important;
  overflow-x: hidden;
  -webkit-overflow-scrolling: touch;
  overscroll-behavior-y: contain;
}
.security-record-modal .modal-footer {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: .5rem;
  padding-bottom: calc(.75rem + env(safe-area-inset-bottom, 0px));
  background: #fffdf6;
}
.security-record-modal .modal-footer .btn,
.security-record-modal .modal-header .btn-close {
  min-height: 44px;
  touch-action: manipulation;
}
.security-record-modal .modal-footer .btn { white-space: normal; }

@media (max-width: 767.98px), (max-height: 600px) {
  .security-record-modal.modal { padding: 0 !important; overflow: hidden; }
  .security-record-modal .modal-dialog,
  .security-record-modal .modal-dialog.modal-dialog-scrollable {
    width: calc(100% - 1rem);
    max-width: none;
    height: calc(100dvh - 1rem);
    max-height: calc(100dvh - 1rem);
    margin: .5rem auto;
  }
  .security-record-modal .modal-content {
    height: calc(100dvh - 1rem);
    max-height: calc(100dvh - 1rem);
    border-radius: .9rem;
  }
  .security-record-modal .modal-header {
    padding: .8rem 3.5rem .8rem .9rem;
    align-items: flex-start;
  }
  .security-record-modal .modal-header .modal-title {
    min-width: 0;
    font-size: clamp(1rem, 4.2vw, 1.2rem);
    line-height: 1.25;
    overflow-wrap: anywhere;
  }
  .security-record-modal .modal-header small { line-height: 1.35; }
  .security-record-modal .modal-body { padding: .75rem !important; }
  .security-record-modal .modal-body .row { --bs-gutter-x: .75rem; }
  .security-record-modal .modal-body .card { min-width: 0; }
  .security-record-modal .modal-body input,
  .security-record-modal .modal-body select,
  .security-record-modal .modal-body textarea,
  .security-record-modal .modal-body .form-control,
  .security-record-modal .modal-body .form-select { max-width: 100%; min-width: 0; }
  .security-record-modal .modal-footer {
    flex-wrap: wrap !important;
    justify-content: stretch;
    gap: .45rem;
    padding: .6rem .7rem calc(.6rem + env(safe-area-inset-bottom, 0px));
  }
  .security-record-modal .modal-footer > #backStep {
    flex: 1 1 calc(50% - .45rem);
    width: auto !important;
    margin: 0 !important;
  }
  .security-record-modal .modal-footer > .ms-auto {
    display: flex !important;
    flex: 1 1 100%;
    flex-wrap: wrap !important;
    gap: .45rem;
    width: 100%;
    min-width: 0;
    margin: 0 !important;
  }
  .security-record-modal .modal-footer > .ms-auto > .btn,
  .security-record-modal .modal-footer > .ms-auto > .portal-btn,
  .security-record-modal .modal-footer > .ms-auto > .admin-btn {
    flex: 1 1 calc(50% - .45rem);
    min-width: 0;
    width: auto !important;
    margin: 0 !important;
    justify-content: center;
  }
  .security-record-modal .modal-footer #continueStep:not(.d-none),
  .security-record-modal .modal-footer #nextStep:not(.d-none),
  .security-record-modal .modal-footer #saveViolation:not(.d-none) {
    flex: 1 1 100%;
    width: 100% !important;
    min-height: 46px;
    order: 10;
  }
}

@media (max-width: 359.98px) {
  .security-record-modal .modal-footer > #backStep,
  .security-record-modal .modal-footer > .ms-auto > .btn,
  .security-record-modal .modal-footer > .ms-auto > .portal-btn,
  .security-record-modal .modal-footer > .ms-auto > .admin-btn {
    flex-basis: 100%;
    width: 100% !important;
  }
}



/* Final fix: keep the wizard actions visible and make only the form body scroll. */
.security-record-modal.modal {
    overflow-y: auto !important;
    overflow-x: hidden !important;
    -webkit-overflow-scrolling: touch;
}
.security-record-modal .modal-dialog,
.security-record-modal .modal-dialog.modal-dialog-scrollable {
    display: flex !important;
    flex-direction: column !important;
    width: min(1160px, calc(100% - 2rem)) !important;
    height: calc(100dvh - 2rem) !important;
    max-height: calc(100dvh - 2rem) !important;
    margin: 1rem auto !important;
}
.security-record-modal .modal-content {
    display: flex !important;
    flex-direction: column !important;
    flex: 1 1 auto !important;
    width: 100% !important;
    height: 100% !important;
    min-height: 0 !important;
    max-height: 100% !important;
    overflow: hidden !important;
}
/* The form sits between the header and the modal body/footer, so it must also
   be a flex column; otherwise its body cannot shrink and the footer gets pushed off-screen. */
.security-record-modal .modal-content > form#violationForm {
    display: flex !important;
    flex: 1 1 auto !important;
    flex-direction: column !important;
    min-width: 0 !important;
    min-height: 0 !important;
    height: auto !important;
    overflow: hidden !important;
}
.security-record-modal .modal-header {
    flex: 0 0 auto !important;
    min-width: 0;
    position: relative;
    z-index: 2;
}
.security-record-modal .modal-body {
    display: block !important;
    flex: 1 1 auto !important;
    min-height: 0 !important;
    max-height: none !important;
    height: auto !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    overscroll-behavior-y: contain;
    -webkit-overflow-scrolling: touch;
    touch-action: pan-y;
}
.security-record-modal .modal-footer {
    display: flex !important;
    flex: 0 0 auto !important;
    flex-wrap: wrap !important;
    align-items: center !important;
    justify-content: flex-end !important;
    gap: .5rem !important;
    min-height: 0;
    padding: .65rem .85rem calc(.65rem + env(safe-area-inset-bottom, 0px)) !important;
    position: relative;
    z-index: 3;
    background: #fffdf6;
}
.security-record-modal .modal-footer > #backStep {
    flex: 0 1 auto !important;
    min-width: 5.5rem;
    margin: 0 !important;
}
.security-record-modal .modal-footer > .ms-auto {
    display: flex !important;
    flex: 0 1 auto !important;
    flex-wrap: wrap !important;
    gap: .5rem !important;
    min-width: 0;
    margin-left: auto !important;
}
.security-record-modal .modal-footer .btn,
.security-record-modal .modal-header .btn-close {
    min-height: 44px;
    touch-action: manipulation;
}
.security-record-modal .modal-footer .btn {
    max-width: 100%;
    white-space: normal !important;
}
@media (max-width: 767.98px), (max-height: 600px) {
    .security-record-modal.modal { padding: 0 !important; }
    .security-record-modal .modal-dialog,
    .security-record-modal .modal-dialog.modal-dialog-scrollable {
        width: calc(100% - 1rem) !important;
        height: calc(100dvh - 1rem) !important;
        max-height: calc(100dvh - 1rem) !important;
        margin: .5rem auto !important;
    }
    .security-record-modal .modal-content { border-radius: .85rem !important; }
    .security-record-modal .modal-header {
        padding: .75rem 3.25rem .75rem .85rem !important;
        align-items: flex-start;
    }
    .security-record-modal .modal-header .modal-title {
        min-width: 0;
        font-size: clamp(1rem, 4.2vw, 1.2rem);
        line-height: 1.25;
        overflow-wrap: anywhere;
    }
    .security-record-modal .modal-header .btn-close {
        position: absolute;
        top: .6rem;
        right: .6rem;
    }
    .security-record-modal .modal-body { padding: .75rem !important; }
    .security-record-modal .modal-body .row { --bs-gutter-x: .75rem; }
    .security-record-modal .modal-body .card { min-width: 0; }
    .security-record-modal .modal-body input,
    .security-record-modal .modal-body select,
    .security-record-modal .modal-body textarea,
    .security-record-modal .modal-body .form-control,
    .security-record-modal .modal-body .form-select { max-width: 100%; min-width: 0; }
    .security-record-modal .modal-footer {
        justify-content: stretch !important;
        gap: .4rem !important;
        padding: .55rem .65rem calc(.55rem + env(safe-area-inset-bottom, 0px)) !important;
    }
    .security-record-modal .modal-footer > #backStep { flex: 1 1 calc(50% - .4rem) !important; }
    .security-record-modal .modal-footer > .ms-auto {
        flex: 1 1 100% !important;
        width: 100%;
        margin: 0 !important;
        justify-content: stretch;
    }
    .security-record-modal .modal-footer > .ms-auto > .btn,
    .security-record-modal .modal-footer > .ms-auto > .portal-btn,
    .security-record-modal .modal-footer > .ms-auto > .admin-btn {
        flex: 1 1 calc(50% - .4rem) !important;
        min-width: 0;
        margin: 0 !important;
    }
    .security-record-modal .modal-footer #continueStep:not(.d-none),
    .security-record-modal .modal-footer #nextStep:not(.d-none),
    .security-record-modal .modal-footer #saveViolation:not(.d-none) {
        flex: 1 1 100% !important;
        width: 100% !important;
        min-height: 46px;
        order: 10;
    }
}
@media (max-width: 359.98px) {
    .security-record-modal .modal-footer > #backStep,
    .security-record-modal .modal-footer > .ms-auto > .btn,
    .security-record-modal .modal-footer > .ms-auto > .portal-btn,
    .security-record-modal .modal-footer > .ms-auto > .admin-btn {
        flex-basis: 100% !important;
        width: 100% !important;
    }
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

                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="violationForm">
                @csrf

                <input type="hidden" id="selectedStudentNumber" name="student_number">

                <div class="modal-body">
                    <div class="row text-center mb-4">
                        <div class="col">
                            <div id="studentStepIndicator" class="wizard-bubble wizard-step-current mx-auto">1</div>
                            <div class="mt-2 fw-semibold">Student</div>
                        </div>
                        <div class="col">
                            <div id="violationStepIndicator" class="wizard-bubble wizard-step-upcoming mx-auto">2</div>
                            <div class="mt-2 fw-semibold">Violation</div>
                        </div>
                        <div class="col">
                            <div id="evidenceStepIndicator" class="wizard-bubble wizard-step-upcoming mx-auto">3</div>
                            <div class="mt-2 fw-semibold">Evidence</div>
                        </div>
                        <div class="col">
                            <div id="reviewStepIndicator" class="wizard-bubble wizard-step-upcoming mx-auto">4</div>
                            <div class="mt-2 fw-semibold">Review</div>
                        </div>
                    </div>

                    <div id="studentStep">
                        <div class="card wizard-card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-search me-2" style="color:#7a0f1e;"></i>Search Student</h5>
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
                                <h5 class="mb-0"><i class="bi bi-person-badge me-2" style="color:#7a0f1e;"></i>Selected Student</h5>
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

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="violationStep" class="d-none">
                        <div class="card wizard-card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-exclamation-octagon me-2" style="color:#7a0f1e;"></i>Violation Information</h5>
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

                    {{-- Step 3: Evidence --}}
                    <div id="evidenceStep" class="d-none">
                        <div class="card wizard-card">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-shield-lock me-2" style="color:#7a0f1e;"></i>Controlled Evidence Capture</h5>
                                <span class="badge text-bg-secondary" id="evidenceCountBadge">0 / 5 files</span>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-warning small mb-3">
                                    <i class="bi bi-shield-exclamation me-1"></i>
                                    <strong>Protected capture:</strong> photos must be taken through the PUPTracker camera interface so a permanent watermark can be applied. Photo capture is monitored for suspicious interruptions (screen capture, focus loss) as a deterrent — these are recorded as security events requiring review. PDF documents use the secure upload path. All files are stored privately.
                                </div>

                                <div class="row g-3">
                                    {{-- Photo: controlled camera --}}
                                    <div class="col-lg-7">
                                        <div class="border rounded-3 p-3 h-100 bg-white">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="bi bi-camera-fill fs-4 me-2" style="color:#7a0f1e;"></i>
                                                <div>
                                                    <strong>Capture Photo</strong>
                                                    <div class="small text-muted">Live capture with watermark</div>
                                                </div>
                                            </div>

                                            {{-- Camera not started / notice gate --}}
                                            <div id="cameraIdlePanel" class="text-center py-4 text-muted">
                                                <i class="bi bi-camera-video-off display-5 d-block mb-2"></i>
                                                <p class="small mb-3">Camera is off. A security notice will be shown before the camera starts.</p>
                                                <button type="button" id="openCameraBtn" class="btn portal-btn btn-sm">
                                                    <i class="bi bi-camera-video me-1"></i>Open Camera
                                                </button>
                                            </div>

                                            {{-- Camera running --}}
                                            <div id="cameraLivePanel" class="d-none">
                                                <div id="cameraWrap" class="position-relative rounded-3 overflow-hidden bg-black" style="aspect-ratio:4/3;">
                                                    <video id="cameraPreview" autoplay playsinline muted class="w-100 h-100 object-fit-cover"></video>
                                                    {{-- Flip between front and back camera (mobile). Only visible while the stream is live. --}}
                                                    <button
                                                        type="button"
                                                        id="flipCameraBtn"
                                                        class="position-absolute top-0 end-0 m-2 btn btn-sm btn-light rounded-circle d-none align-items-center justify-content-center"
                                                        style="width:40px;height:40px;box-shadow:0 2px 8px rgba(0,0,0,0.4);z-index:5;"
                                                        title="Switch camera (front/back)"
                                                        aria-label="Switch camera">
                                                        <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                                                    </button>
                                                    {{-- Live watermark overlay is a real-time preview; the FINAL watermark is baked into the captured image via canvas. --}}
                                                    <div id="liveWatermarkOverlay" class="position-absolute bottom-0 start-0 end-0 px-2 py-1 text-white small" style="background:rgba(0,0,0,0.55);font-family:ui-monospace,monospace;pointer-events:none;">
                                                        PUPTracker &nbsp;|&nbsp; <span id="liveWatermarkStudent">—</span> &nbsp;|&nbsp; <span id="liveWatermarkTime">--/--/---- --:--</span>
                                                    </div>
                                                    <div id="cameraInterruptedOverlay" class="position-absolute top-0 start-0 end-0 bottom-0 d-none align-items-center justify-content-center text-white" style="background:rgba(122,15,30,0.85);">
                                                        <div class="text-center px-3">
                                                            <i class="bi bi-eye-slash display-6 d-block mb-2"></i>
                                                            <strong>Camera paused</strong>
                                                            <div class="small">Possible screen capture or browser interruption was detected. Return to this window to continue.</div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="d-flex align-items-center justify-content-between mt-2">
                                                    <span class="small text-muted">
                                                        <i class="bi bi-shield-check me-1"></i>Watermark will be applied on capture
                                                    </span>
                                                    <div class="d-flex gap-2">
                                                        <button type="button" id="capturePhotoBtn" class="btn portal-btn btn-sm" disabled>
                                                            <i class="bi bi-camera me-1"></i>Capture Photo
                                                        </button>
                                                        <button type="button" id="closeCameraBtn" class="btn btn-outline-secondary btn-sm">
                                                            <i class="bi bi-x-lg me-1"></i>Stop
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Review captured photo before accepting --}}
                                            <div id="captureReviewPanel" class="d-none">
                                                <div class="rounded-3 overflow-hidden bg-black mb-2">
                                                    <img id="captureReviewImg" alt="Captured photo preview" class="w-100 d-block">
                                                </div>
                                                <div class="small text-muted mb-2">
                                                    <div>Evidence ID: <strong id="captureReviewCode">assigned on submit</strong></div>
                                                    <div>Type: <span id="captureReviewType">JPEG</span> &middot; Size: <span id="captureReviewSize">0 KB</span></div>
                                                </div>
                                                <div class="d-flex gap-2">
                                                    <button type="button" id="retakePhotoBtn" class="btn btn-outline-secondary btn-sm">
                                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Retake
                                                    </button>
                                                    <button type="button" id="usePhotoBtn" class="btn portal-btn btn-sm">
                                                        <i class="bi bi-check-lg me-1"></i>Use Photo
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- PDF / document upload --}}
                                    <div class="col-lg-5">
                                        <div class="border rounded-3 p-3 h-100 bg-white">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="bi bi-file-earmark-pdf-fill fs-4 me-2" style="color:#b91c1c;"></i>
                                                <div>
                                                    <strong>Upload PDF Document</strong>
                                                    <div class="small text-muted">Secure private upload</div>
                                                </div>
                                            </div>
                                            <p class="small text-muted">For document-type evidence (e.g. written reports). Photos must be captured with the camera above.</p>
                                            <input type="file" id="evidenceInput" name="evidence[]" class="form-control" accept=".pdf,application/pdf">
                                            <small class="text-muted">Allowed: PDF — max 5 MB each, up to 5 total files.</small>
                                        </div>
                                    </div>
                                </div>

                                <hr>

                                <label class="form-label fw-semibold">Staged Evidence</label>
                                <div id="evidencePreviewList" class="d-flex flex-wrap gap-2"></div>
                                <p class="text-muted small mb-0 mt-2">Each file is stored privately with its own unique Evidence ID (EVD-…) and is never overwritten.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Step 4: Review --}}
                    <div id="reviewStep" class="d-none">
                        <div class="card wizard-card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-clipboard-check me-2" style="color:#7a0f1e;"></i>Review &amp; Submit</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="preview-box p-3">
                                            <h6 class="fw-bold mb-2"><i class="bi bi-person-badge me-1"></i>Student</h6>
                                            <div class="small mb-1"><strong>Number:</strong> <span id="reviewStudentNumber">-</span></div>
                                            <div class="small mb-1"><strong>Name:</strong> <span id="reviewStudentName">-</span></div>
                                            <div class="small mb-1"><strong>Program:</strong> <span id="reviewStudentProgram">-</span></div>
                                            <div class="small"><strong>Year:</strong> <span id="reviewStudentYear">-</span></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="preview-box p-3">
                                            <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-octagon me-1"></i>Violation</h6>
                                            <div class="small mb-1"><strong>Category:</strong> <span id="reviewCategory">-</span></div>
                                            <div class="small mb-1"><strong>Type:</strong> <span id="reviewViolationType">-</span></div>
                                            <div class="small mb-1"><strong>Date &amp; Time:</strong> <span id="reviewDate">-</span></div>
                                            <div class="small mb-1"><strong>Offense Level:</strong> <span id="reviewOffenseLevel">-</span></div>
                                            <div class="small"><strong>Sanction:</strong> <span id="reviewSanction">-</span></div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="preview-box p-3">
                                            <h6 class="fw-bold mb-2"><i class="bi bi-chat-left-text me-1"></i>Description</h6>
                                            <div class="small" id="reviewDescription" style="white-space:pre-line;">-</div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="preview-box p-3">
                                            <h6 class="fw-bold mb-2"><i class="bi bi-paperclip me-1"></i>Evidence</h6>
                                            <div class="small" id="reviewEvidenceSummary">No evidence attached.</div>
                                        </div>
                                    </div>
                                </div>
<div class="col-12">

    <div class="preview-box p-3">

        <h6 class="fw-bold mb-2">
            <i class="bi bi-person-check me-1"></i>
            Student Identity Verification
        </h6>

        <div class="form-check">
            <input
                class="form-check-input"
                type="checkbox"
                id="identityConfirmation"
                name="identity_confirmation"
                value="1"
            >

            <label
                class="form-check-label fw-semibold"
                for="identityConfirmation"
            >
                I confirm that I have verified the identity of the selected
                student using the student's institutional identification details.
            </label>
        </div>

        <div class="small text-muted mt-2">
            Verify the student's identification details before submitting
            this violation for administrative review.
        </div>

    </div>

</div>
                               <div class="alert alert-info small mt-3 mb-0">
    <i class="bi bi-shield-exclamation me-1"></i>
    This report will be sent to an administrator for review and confirmation.
    Your identity verification will be recorded with this violation.
</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn portal-btn-outline d-none" id="backStep"><i class="bi bi-arrow-left me-1"></i>Back</button>
                    <button type="button" class="btn portal-btn d-none" id="nextStep">Next <i class="bi bi-arrow-right ms-1"></i></button>
                    <button type="button" class="btn portal-btn d-none" id="saveViolation"><i class="bi bi-send me-1"></i>Submit for Review</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Security notice shown before the camera initializes --}}
<div class="modal fade security-notice-modal" id="securityNoticeModal" tabindex="-1" aria-labelledby="securityNoticeModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background:#800000;">
                <h5 class="modal-title text-white fw-bold" id="securityNoticeModalLabel">
                    <i class="bi bi-shield-exclamation me-2"></i>Security Notice
                </h5>
            </div>
            <div class="modal-body">
                <div class="border rounded-3 p-3 mb-3" style="background:#fff7e3;">
                    <div class="small lh-lg" style="font-family:ui-monospace,monospace;">
                        This evidence capture process is protected by security and audit controls.<br><br>
                        Photo evidence must be captured using the PUPTracker camera interface.<br><br>
                        Unauthorized screen capture, attempts to circumvent the evidence capture process, and suspicious browser interruptions may be recorded as security events.<br><br>
                        Captured evidence may contain identifying information and is protected by access controls.
                    </div>
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="securityNoticeAck">
                    <label class="form-check-label" for="securityNoticeAck">
                        I understand the security notice.
                    </label>
                </div>
                <p class="text-muted small mt-2 mb-0">
                    This acknowledgement records your consent to the capture controls described. It does not replace the institution's privacy notice or applicable data protection requirements.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="securityNoticeCancelBtn">Cancel</button>
                <button type="button" class="btn portal-btn" id="securityNoticeStartBtn" disabled>
                    <i class="bi bi-camera-video me-1"></i>Start Camera
                </button>
            </div>
        </div>
    </div>
</div>