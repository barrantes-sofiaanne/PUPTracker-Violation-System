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
background: #800000 !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        color: #fff;
    }

    .admin-record-modal .modal-header .modal-title,
    .admin-record-modal .modal-header .modal-title i,
    .admin-record-modal .modal-header small {
        color: #fff !important;
    }

    .admin-record-modal .modal-body {
background: #ffffff;
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

    /* ------------------------------------------------------------------
       Wizard presentation — the Security module's Record Violation modal is
       the SOURCE OF TRUTH for this UI. Every visual value below mirrors it
       exactly.

       The admin layout does not load portal.css, so the Security-only tokens
       (`--portal-radius-lg`, `--portal-shadow`) and classes (`portal-btn`)
       are NOT available here; their literal values are reproduced instead.
       Maroon/gold are admin.css tokens and are used via those.

       These classes are PRESENTATION ONLY: the Admin modal keeps its own ids
       (`studentStepIndicator`, `violationWizardProgress`, ...), its own form
       submission, validation and controller, so Admin authorization and
       business rules are unchanged.
       ------------------------------------------------------------------ */
    .admin-record-modal .wizard-bubble {
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

       The states previously used Bootstrap's bg-success (GREEN) and
       bg-danger (Bootstrap red), neither of which belongs to the PUPTracker
       palette. They are replaced by these three classes, which use only the
       module's maroon and gold tokens. No other hue is introduced.
       ------------------------------------------------------------------ */
    .admin-record-modal .wizard-bubble.wizard-step-current {
        background: #7a0f1e;
        border-color: #7a0f1e;
        color: #fff;
        box-shadow: 0 6px 14px rgba(122, 15, 30, 0.3);
    }

    .admin-record-modal .wizard-bubble.wizard-step-completed {
        background: #daa520;
        border-color: #daa520;
        color: #4a0209;
    }

    .admin-record-modal .wizard-bubble.wizard-step-upcoming {
        background: #fff8e8;
        border-color: rgba(122, 15, 30, 0.28);
        color: #7a0f1e;
    }

    /* Step cards use the module's maroon/gold tone rather than a generic
       shadow, matching Security's `.wizard-card`. */
    .admin-record-modal .wizard-card {
        border: 1px solid rgba(122, 15, 30, 0.12);
        border-radius: 1rem;
        box-shadow: 0 8px 24px rgba(128, 0, 0, 0.12);
        overflow: hidden;
    }

    .admin-record-modal .wizard-card .card-header {
        background: linear-gradient(180deg, #fffefa 0%, #fff8e8 100%);
        border-bottom: 1px solid rgba(122, 15, 30, 0.12);
        color: #4a0209;
        padding: 0.9rem 1.1rem;
    }

    .admin-record-modal .wizard-card .card-header i {
        color: #7a0f1e !important;
    }

    /* Preview/read-only summary panels (Review step). */
    .admin-record-modal .preview-box {
        border: 1px solid rgba(122, 15, 30, 0.1);
        border-radius: 0.9rem;
        background: #fffdf7;
    }

    .admin-record-modal .preview-box strong {
        color: #4a0209;
    }

    /* Controlled camera capture — the live preview is obscured while the
       video is interrupted, exactly as Security does. */
    .admin-record-modal #cameraPreview {
        filter: none;
        transition: filter 0.15s ease;
        background: #000;
    }

    .admin-record-modal #cameraPreview.camera-obscured {
        filter: blur(18px) brightness(0.5);
    }

    .admin-record-modal #cameraWrap.video-interrupted #cameraPreview {
        filter: blur(18px) brightness(0.5);
    }

    /* Disabled footer buttons stay readable — the maroon tone is preserved
       instead of fading to a washed-out grey. */
    .admin-record-modal .modal-footer .btn:disabled,
    .admin-record-modal .modal-footer .btn.disabled {
        opacity: 0.75;
        filter: saturate(0.85);
        cursor: not-allowed;
    }

    /* Mirrors Security's `.portal-btn:disabled` rule: the primary CTA keeps a
       readable maroon fill when disabled. */
    .admin-record-modal .modal-footer .admin-btn-primary:disabled,
    .admin-record-modal .modal-footer .admin-btn-primary.disabled {
        color: #fff;
        background: linear-gradient(135deg, #a24a55 0%, #8a3a44 100%);
        border-color: #a24a55;
        box-shadow: none;
    }

    .admin-record-modal .form-control,
    .admin-record-modal .form-select {
        border-color: rgba(122, 15, 30, 0.2);
        border-radius: 0.72rem;
    }

    /* The BODY scrolls independently so a long wizard never pushes the header or
       the footer off-screen — header and footer stay reachable at all times. */
    .admin-record-modal .modal-body {
        overflow-y: auto;
        overscroll-behavior: contain;
    }

    .admin-record-modal .modal-content {
        /* Never taller than the viewport, so the footer can not be pushed out of
           view by a tall step (the evidence step with a live camera is tall). */
        max-height: calc(100vh - 1rem);
    }

    @media (max-width: 575.98px) {
        .admin-record-modal .modal-dialog {
            margin: 0.5rem;
        }

        .admin-record-modal .modal-content {
            max-height: calc(100vh - 1rem);
        }
    }

    .admin-record-modal .form-control:focus,
    .admin-record-modal .form-select:focus {
        border-color: rgba(122, 15, 30, 0.5);
        box-shadow: 0 0 0 0.2rem rgba(122, 15, 30, 0.12);
    }

    /* Header/footer chrome — literally the Security values (its radius token
       is 1rem and its border tone is rgba(122, 15, 30, ...)). */
    .admin-record-modal .modal-header {
background: #800000 !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        color: #fff;
        padding: 1.1rem 1.25rem;
        /* Layout: title block flexes, the × keeps a fixed touch target and can
           never be pushed over the title or clipped on narrow screens. */
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        flex-wrap: nowrap;
    }

    .admin-record-modal .modal-header > div {
        min-width: 0; /* allows the title to wrap instead of overflowing */
        flex: 1 1 auto;
    }

    .admin-record-modal .modal-header .modal-title {
        line-height: 1.25;
        overflow-wrap: anywhere;
    }

    /* The × is a bare icon: NO white pill/card behind it. `background-color`
       is forced transparent and the glyph is drawn from an explicit white SVG
       (a CSS `filter` over the whole button is what produced the visible white
       box on phones). 44px keeps the touch target adequate. */
    .admin-record-modal .modal-header .btn-close {
        flex: 0 0 auto;
        width: 44px;
        height: 44px;
        margin: 0;
        padding: 0;
        background-color: transparent !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") !important;
        background-size: 1.1em auto;
        background-position: center;
        background-repeat: no-repeat;
        border: 0;
        border-radius: 999px;
        opacity: 0.9;
        box-shadow: none !important;
        filter: none !important;
    }

    .admin-record-modal .modal-header .btn-close:hover,
    .admin-record-modal .modal-header .btn-close:focus-visible {
        opacity: 1;
        background-color: rgba(255, 255, 255, 0.14) !important;
        box-shadow: none !important;
    }

    .admin-record-modal .modal-footer {
        background: #fffdf6;
        border-top: 1px solid rgba(122, 15, 30, 0.12);
        padding: 0.8rem 1rem;
        /* Wrap rather than clip: on narrow screens the buttons stack instead of
           overflowing or overlapping. */
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
    }

    /* Keep the footer visible and make every action reachable on phones. */
    .admin-record-modal .modal-content {
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .admin-record-modal .modal-body {
        min-height: 0;
        flex: 1 1 auto;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }

    .admin-record-modal .modal-footer {
        position: relative;
        z-index: 5;
        flex: 0 0 auto;
    }

    @media (max-width: 575.98px) {
        .admin-record-modal .modal-dialog {
            margin: 0.5rem;
            max-width: calc(100% - 1rem);
        }

        .admin-record-modal .modal-content {
            max-height: calc(100dvh - 1rem);
        }

        .admin-record-modal .modal-header {
            padding: 0.8rem 0.9rem;
            flex: 0 0 auto;
        }

        .admin-record-modal .modal-header .modal-title {
            font-size: 1.1rem;
            line-height: 1.3;
        }

        .admin-record-modal .modal-body {
            max-height: none;
            padding: 0.85rem;
        }

        .admin-record-modal .modal-footer,
        .admin-record-modal .modal-footer.justify-content-between {
            display: grid !important;
            grid-template-columns: minmax(0, 1fr);
            gap: 0.55rem;
            padding: 0.7rem 0.85rem calc(0.7rem + env(safe-area-inset-bottom, 0px));
        }

        .admin-record-modal .modal-footer > #backStep {
            grid-row: 1;
            width: 100%;
            margin: 0;
            justify-content: center;
        }

        .admin-record-modal .modal-footer > .ms-auto {
            grid-row: 2;
            margin: 0 !important;
            width: 100%;
            min-width: 0;
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 0.55rem;
        }

        .admin-record-modal .modal-footer > .ms-auto > .admin-btn {
            width: 100%;
            min-width: 0;
            margin: 0 !important;
            justify-content: center;
            white-space: normal;
        }

        /* The submit CTA must remain visible on step 4, on its own row. */
        .admin-record-modal .modal-footer > .ms-auto > #saveViolation:not(.d-none) {
            display: inline-flex !important;
            grid-row: 3;
            width: 100%;
            min-height: 46px;
        }

        .admin-record-modal .modal-footer > .ms-auto > #continueStep:not(.d-none) {
            grid-row: 3;
            min-height: 46px;
        }

        .admin-record-modal .modal-footer > .ms-auto > #cancelViolationBtn {
            grid-row: 1;
            min-height: 44px;
        }
    }


/* Final fix: keep the wizard actions visible and make only the form body scroll. */
.admin-record-modal.modal {
    overflow-y: auto !important;
    overflow-x: hidden !important;
    -webkit-overflow-scrolling: touch;
}
.admin-record-modal .modal-dialog,
.admin-record-modal .modal-dialog.modal-dialog-scrollable {
    display: flex !important;
    flex-direction: column !important;
    width: min(1160px, calc(100% - 2rem)) !important;
    height: calc(100dvh - 2rem) !important;
    max-height: calc(100dvh - 2rem) !important;
    margin: 1rem auto !important;
}
.admin-record-modal .modal-content {
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
.admin-record-modal .modal-content > form#violationForm {
    display: flex !important;
    flex: 1 1 auto !important;
    flex-direction: column !important;
    min-width: 0 !important;
    min-height: 0 !important;
    height: auto !important;
    overflow: hidden !important;
}
.admin-record-modal .modal-header {
    flex: 0 0 auto !important;
    min-width: 0;
    position: relative;
    z-index: 2;
}
.admin-record-modal .modal-body {
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
.admin-record-modal .modal-footer {
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
.admin-record-modal .modal-footer > #backStep {
    flex: 0 1 auto !important;
    min-width: 5.5rem;
    margin: 0 !important;
}
.admin-record-modal .modal-footer > .ms-auto {
    display: flex !important;
    flex: 0 1 auto !important;
    flex-wrap: wrap !important;
    gap: .5rem !important;
    min-width: 0;
    margin-left: auto !important;
}
.admin-record-modal .modal-footer .btn,
.admin-record-modal .modal-header .btn-close {
    min-height: 44px;
    touch-action: manipulation;
}
.admin-record-modal .modal-footer .btn {
    max-width: 100%;
    white-space: normal !important;
}
@media (max-width: 767.98px), (max-height: 600px) {
    .admin-record-modal.modal { padding: 0 !important; }
    .admin-record-modal .modal-dialog,
    .admin-record-modal .modal-dialog.modal-dialog-scrollable {
        width: calc(100% - 1rem) !important;
        height: calc(100dvh - 1rem) !important;
        max-height: calc(100dvh - 1rem) !important;
        margin: .5rem auto !important;
    }
    .admin-record-modal .modal-content { border-radius: .85rem !important; }
    .admin-record-modal .modal-header {
        padding: .75rem 3.25rem .75rem .85rem !important;
        align-items: flex-start;
    }
    .admin-record-modal .modal-header .modal-title {
        min-width: 0;
        font-size: clamp(1rem, 4.2vw, 1.2rem);
        line-height: 1.25;
        overflow-wrap: anywhere;
    }
    .admin-record-modal .modal-header .btn-close {
        position: absolute;
        top: .6rem;
        right: .6rem;
    }
    .admin-record-modal .modal-body { padding: .75rem !important; }
    .admin-record-modal .modal-body .row { --bs-gutter-x: .75rem; }
    .admin-record-modal .modal-body .card { min-width: 0; }
    .admin-record-modal .modal-body input,
    .admin-record-modal .modal-body select,
    .admin-record-modal .modal-body textarea,
    .admin-record-modal .modal-body .form-control,
    .admin-record-modal .modal-body .form-select { max-width: 100%; min-width: 0; }
    .admin-record-modal .modal-footer {
        justify-content: stretch !important;
        gap: .4rem !important;
        padding: .55rem .65rem calc(.55rem + env(safe-area-inset-bottom, 0px)) !important;
    }
    .admin-record-modal .modal-footer > #backStep { flex: 1 1 calc(50% - .4rem) !important; }
    .admin-record-modal .modal-footer > .ms-auto {
        flex: 1 1 100% !important;
        width: 100%;
        margin: 0 !important;
        justify-content: stretch;
    }
    .admin-record-modal .modal-footer > .ms-auto > .btn,
    .admin-record-modal .modal-footer > .ms-auto > .portal-btn,
    .admin-record-modal .modal-footer > .ms-auto > .admin-btn {
        flex: 1 1 calc(50% - .4rem) !important;
        min-width: 0;
        margin: 0 !important;
    }
    .admin-record-modal .modal-footer #continueStep:not(.d-none),
    .admin-record-modal .modal-footer #nextStep:not(.d-none),
    .admin-record-modal .modal-footer #saveViolation:not(.d-none) {
        flex: 1 1 100% !important;
        width: 100% !important;
        min-height: 46px;
        order: 10;
    }
}
@media (max-width: 359.98px) {
    .admin-record-modal .modal-footer > #backStep,
    .admin-record-modal .modal-footer > .ms-auto > .btn,
    .admin-record-modal .modal-footer > .ms-auto > .portal-btn,
    .admin-record-modal .modal-footer > .ms-auto > .admin-btn {
        flex-basis: 100% !important;
        width: 100% !important;
    }
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

            <div class="modal-header text-white">

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
                                class="wizard-bubble wizard-step-current mx-auto">

                                1

                            </div>

                            <div class="mt-2 fw-semibold">

                                Student

                            </div>

                        </div>

                        <div class="col">

                            <div
                                id="violationStepIndicator"
                                class="wizard-bubble wizard-step-upcoming mx-auto">

                                2

                            </div>

                            <div class="mt-2 fw-semibold">

                                Violation

                            </div>

                        </div>

                        <div class="col">

                            <div
                                id="evidenceStepIndicator"
                                class="wizard-bubble wizard-step-upcoming mx-auto">

                                3

                            </div>

                            <div class="mt-2 fw-semibold">

                                Evidence

                            </div>

                        </div>

                        <div class="col">

                            <div
                                id="reviewStepIndicator"
                                class="wizard-bubble wizard-step-upcoming mx-auto">

                                4

                            </div>

                            <div class="mt-2 fw-semibold">

                                Review

                            </div>

                        </div>

                    </div>

                    {{--
                        The horizontal progress line was removed: the numbered
                        circles ARE the progress indicator. `violationWizardProgress`
                        is intentionally absent; the JS no longer looks it up.
                    --}}

                    <!-- =========================================== -->
                    <!-- STEP 1 -->
                    <!-- =========================================== -->

                    <div id="studentStep">

                        <div class="card wizard-card">

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

                        <div class="card wizard-card mt-4">

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

<div class="card wizard-card">

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
            {{-- Human-readable label only. Security renders exactly this, so the
                 admin dropdown must not append the raw category ID. --}}
            {{ $category->category_name }}
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

<div class="card wizard-card">

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

                        <div class="card wizard-card">

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

                        <div class="card wizard-card">

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

                                <div class="alert alert-warning small mt-3 mb-0">

                                    <i class="bi bi-person-check me-1"></i>

                                    Please verify the student's identity before recording this violation.

                                </div>

                                <div class="form-check mt-3 p-3 border rounded-3 bg-white">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        id="identityConfirmed"
                                        name="identity_confirmed"
                                        value="1">

                                    <label
                                        class="form-check-label fw-semibold"
                                        for="identityConfirmed">

                                        I confirm that I have verified the student's identity and that the student information shown above belongs to the student being recorded.

                                    </label>

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

                            Next

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

<div class="modal-header evidence-capture-modal-header">

    <h5
        class="modal-title fw-bold"
        id="securityNoticeModalLabel">

        <i class="bi bi-shield-lock me-2"></i>
        Evidence Capture Notice

    </h5>

    <button
        type="button"
        class="btn-close evidence-capture-modal-close"
        data-bs-dismiss="modal"
        aria-label="Close">
    </button>

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

<style>
/* Shared Security module visual baseline */
.admin-record-modal .modal-dialog {
        max-width: 1160px;
    }

    .admin-record-modal .modal-content {
        border: 1px solid rgba(122, 15, 30, 0.12);
        border-radius: var(--portal-radius-lg);
        overflow: hidden;
        max-height: calc(100vh - 2rem);
    }

.admin-record-modal .modal-header {
    position: relative;
    background: #800000;
    border-bottom: 1px solid rgba(255, 255, 255, 0.15);
    color: #fff;
    padding: 1.1rem 4.5rem 1.1rem 1.25rem;
}

.admin-record-modal .modal-header .btn-close {
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

.admin-record-modal .modal-header .btn-close:hover,
.admin-record-modal .modal-header .btn-close:focus-visible {
    opacity: 1;
}

    .admin-record-modal .modal-header .modal-title,
    .admin-record-modal .modal-header .modal-title i,
    .admin-record-modal .modal-header small {
        color: #fff !important;
    }

    .admin-record-modal .modal-body {
background: #ffffff;
        overflow-y: auto;
        max-height: calc(100vh - 220px);
    }

    .admin-record-modal .modal-footer {
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
    .admin-record-modal .modal-body {
        overflow-y: auto;
        overscroll-behavior: contain;
    }

    .admin-record-modal .modal-content {
        max-height: calc(100vh - 1rem);
    }

    /* Small screens: the primary CTA takes its own full-width row. */
    @media (max-width: 575.98px) {
        .admin-record-modal .modal-dialog {
            margin: 0.5rem;
        }

        .admin-record-modal .modal-footer > .ms-auto {
            margin-left: 0 !important;
            width: 100%;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .admin-record-modal .modal-footer > .ms-auto > .portal-btn,
        .admin-record-modal .modal-footer > .ms-auto > .admin-btn {
            flex: 1 1 auto;
        }

        .admin-record-modal .modal-footer #saveViolation {
            order: 3;
            flex: 1 1 100%;
        }
    }

    .admin-record-modal .wizard-bubble {
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
    .admin-record-modal .wizard-bubble.wizard-step-current {
        background: #7a0f1e;
        border-color: #7a0f1e;
        color: #fff;
        box-shadow: 0 6px 14px rgba(122, 15, 30, 0.3);
    }

    .admin-record-modal .wizard-bubble.wizard-step-completed {
        background: #daa520;
        border-color: #daa520;
        color: #4a0209;
    }

    .admin-record-modal .wizard-bubble.wizard-step-upcoming {
        background: #fff8e8;
        border-color: rgba(122, 15, 30, 0.28);
        color: #7a0f1e;
    }

    .admin-record-modal .wizard-card {
        border: 1px solid rgba(122, 15, 30, 0.12);
        border-radius: var(--portal-radius-lg);
        box-shadow: var(--portal-shadow);
    }

    .admin-record-modal .wizard-card .card-header {
        background: linear-gradient(180deg, #fffefa 0%, #fff8e8 100%);
        border-bottom: 1px solid rgba(122, 15, 30, 0.12);
        color: #4a0209;
        padding: 0.9rem 1.1rem;
    }

    .admin-record-modal .wizard-card .card-header i {
        color: #7a0f1e !important;
    }

    .admin-record-modal .form-control,
    .admin-record-modal .form-select {
        border-color: rgba(122, 15, 30, 0.2);
        border-radius: 0.72rem;
    }

    .admin-record-modal .form-control:focus,
    .admin-record-modal .form-select:focus {
        border-color: rgba(122, 15, 30, 0.5);
        box-shadow: 0 0 0 0.2rem rgba(122, 15, 30, 0.12);
    }

    .admin-record-modal .preview-box {
        border: 1px solid rgba(122, 15, 30, 0.1);
        border-radius: 0.9rem;
        background: #fffdf7;
    }

    .admin-record-modal .preview-box strong {
        color: #4a0209;
    }

    /* Controlled camera capture */
    .admin-record-modal #cameraPreview {
        filter: none;
        transition: filter 0.15s ease;
        background: #000;
    }

    .admin-record-modal #cameraPreview.camera-obscured {
        filter: blur(18px) brightness(0.5);
    }

    .admin-record-modal #cameraWrap.video-interrupted #cameraPreview {
        filter: blur(18px) brightness(0.5);
    }

    .security-notice-modal .modal-content {
        border-radius: 1rem;
    }

    /* Disabled buttons stay readable — keep the maroon/gold tone visible. */
    .admin-record-modal .modal-footer .btn:disabled,
    .admin-record-modal .modal-footer .btn.disabled {
        opacity: 0.75;
        filter: saturate(0.85);
        cursor: not-allowed;
    }

    .admin-record-modal .modal-footer .portal-btn:disabled {
        color: #fff;
        background: linear-gradient(135deg, #a24a55 0%, #8a3a44 100%);
        border-color: #a24a55;
        box-shadow: none;
    }

/* Shared Security-inspired mobile layout: independent scrolling and reachable actions */
@media (max-width: 767.98px) {
  .admin-record-modal .modal-dialog { width: calc(100% - 1rem); max-width: none; margin: .5rem auto; }
  .admin-record-modal .modal-content { height: calc(100dvh - 1rem); max-height: calc(100dvh - 1rem); min-height: 0; display: flex; flex-direction: column; overflow: hidden; }
  .admin-record-modal .modal-header { flex: 0 0 auto; padding: .9rem 3.5rem .9rem 1rem; }
  .admin-record-modal .modal-body { flex: 1 1 auto; min-height: 0; overflow-y: auto !important; overflow-x: hidden; -webkit-overflow-scrolling: touch; overscroll-behavior-y: contain; padding: .85rem !important; }
  .admin-record-modal .modal-footer { flex: 0 0 auto; position: sticky; bottom: 0; z-index: 5; display: flex; flex-wrap: wrap; gap: .5rem; padding: .75rem max(.75rem, env(safe-area-inset-right)) calc(.75rem + env(safe-area-inset-bottom)) max(.75rem, env(safe-area-inset-left)); }
  .admin-record-modal .modal-footer .btn { min-height: 44px; max-width: 100%; }
  .admin-record-modal .modal-body .row { --bs-gutter-x: .75rem; }
  .admin-record-modal .modal-body input, .admin-record-modal .modal-body select, .admin-record-modal .modal-body textarea { max-width: 100%; }
  .admin-record-modal .modal-body img, .admin-record-modal .modal-body canvas, .admin-record-modal .modal-body video { max-width: 100%; height: auto; }
}
@media (max-width: 420px) {
  .admin-record-modal .modal-footer { justify-content: stretch; }
  .admin-record-modal .modal-footer .btn { flex: 1 1 auto; }
  .admin-record-modal .modal-title { font-size: 1rem; }
  .admin-record-modal .modal-body { font-size: .95rem; }
}



/* UI alignment with the Security Record Violation modal.
   Presentation-only overrides: do not change wizard or submission handlers. */
.admin-record-modal .modal-header {
    background: #800000 !important;
    color: #fff !important;
}
.admin-record-modal .modal-header .modal-title,
.admin-record-modal .modal-header .modal-title i,
.admin-record-modal .modal-header small {
    color: #fff !important;
}
.admin-record-modal .modal-footer {
    background: #fffdf6 !important;
    border-top: 1px solid rgba(122, 15, 30, .12) !important;
    display: flex !important;
    flex-wrap: nowrap !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: .5rem !important;
}
.admin-record-modal .modal-footer > .ms-auto {
    display: flex !important;
    align-items: center !important;
    justify-content: flex-end !important;
    flex: 1 1 auto !important;
    width: auto !important;
    min-width: 0 !important;
    margin-left: auto !important;
    gap: .5rem !important;
}
.admin-record-modal .modal-footer #cancelViolationBtn {
    display: none !important;
}
.admin-record-modal .modal-footer #backStep,
.admin-record-modal .modal-footer #continueStep,
.admin-record-modal .modal-footer #saveViolation {
    min-height: 44px;
    margin: 0 !important;
    white-space: nowrap;
}
.admin-record-modal .modal-footer #continueStep,
.admin-record-modal .modal-footer #saveViolation {
    color: #fff !important;
    background: #800000 !important;
    border-color: #800000 !important;
}
.admin-record-modal .modal-footer #backStep {
    flex: 0 1 45% !important;
}
.admin-record-modal .modal-footer #continueStep,
.admin-record-modal .modal-footer #saveViolation {
    flex: 0 1 55% !important;
}
@media (max-width: 575.98px) {
    .admin-record-modal .modal-footer {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        gap: .5rem !important;
        padding: .75rem max(.75rem, env(safe-area-inset-right)) calc(.75rem + env(safe-area-inset-bottom)) max(.75rem, env(safe-area-inset-left)) !important;
    }
    .admin-record-modal .modal-footer > #backStep {
        flex: 1 1 45% !important;
        width: auto !important;
        grid-row: auto !important;
    }
    .admin-record-modal .modal-footer > .ms-auto {
        flex: 1 1 55% !important;
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        width: auto !important;
        margin: 0 !important;
        gap: .5rem !important;
    }
    .admin-record-modal .modal-footer > .ms-auto > #continueStep:not(.d-none),
    .admin-record-modal .modal-footer > .ms-auto > #saveViolation:not(.d-none) {
        flex: 1 1 100% !important;
        width: 100% !important;
        min-width: 0 !important;
        grid-row: auto !important;
    }
    .admin-record-modal .modal-footer > .ms-auto > #cancelViolationBtn {
        display: none !important;
    }
}

</style>


{{-- Final UI parity overrides: review identity confirmation and compact footer --}}
<style>
/* Keep the Admin identity confirmation readable and visually aligned with Security. */
.admin-record-modal #identityConfirmed {
    width: 1.15rem; height: 1.15rem; margin-top: .2rem; flex: 0 0 auto;
    accent-color: #850000; cursor: pointer;
}
.admin-record-modal .form-check:has(#identityConfirmed) {
    display: flex; align-items: flex-start; gap: .7rem;
    padding: 1rem !important; margin-top: 1rem !important;
    border: 1px solid #eadfdb !important; border-radius: 14px !important;
    background: #fff !important;
}
.admin-record-modal .form-check:has(#identityConfirmed) .form-check-input {
    float: none !important; margin-left: 0 !important;
}
.admin-record-modal .form-check:has(#identityConfirmed) .form-check-label {
    display: block; line-height: 1.55; font-size: clamp(.95rem, 2.8vw, 1.05rem);
}
.admin-record-modal .modal-footer {
    display: flex !important; align-items: stretch !important;
    justify-content: space-between !important; gap: .65rem !important;
}
.admin-record-modal .modal-footer > #backStep,
.admin-record-modal .modal-footer > .ms-auto > #continueStep:not(.d-none),
.admin-record-modal .modal-footer > .ms-auto > #saveViolation:not(.d-none) {
    min-height: 52px !important; height: 52px !important;
    padding: .7rem 1rem !important; border-radius: 14px !important;
    font-size: 1rem !important; font-weight: 700 !important;
    flex: 1 1 0 !important; width: auto !important; min-width: 0 !important;
}
.admin-record-modal .modal-footer > .ms-auto {
    display: flex !important; flex: 1 1 0 !important;
    flex-direction: row !important; flex-wrap: nowrap !important;
    gap: .65rem !important; width: auto !important; margin: 0 !important;
}
.admin-record-modal .modal-footer > #backStep { flex: 1 1 0 !important; }
.admin-record-modal .modal-footer > .ms-auto > #continueStep:not(.d-none),
.admin-record-modal .modal-footer > .ms-auto > #saveViolation:not(.d-none) {
    background: #850000 !important; border-color: #850000 !important; color: #fff !important;
}
@media (max-width: 576px) {
    .admin-record-modal .modal-footer {
        display: flex !important; flex-direction: row !important; flex-wrap: nowrap !important;
        padding: .7rem max(.7rem, env(safe-area-inset-right)) calc(.7rem + env(safe-area-inset-bottom)) max(.7rem, env(safe-area-inset-left)) !important;
    }
    .admin-record-modal .modal-footer > #backStep,
    .admin-record-modal .modal-footer > .ms-auto > #continueStep:not(.d-none),
    .admin-record-modal .modal-footer > .ms-auto > #saveViolation:not(.d-none) {
        min-height: 48px !important; height: 48px !important; padding: .6rem .5rem !important;
        font-size: .92rem !important;
    }
}


/* Final responsive override: Record Violation modal (mobile/tablet safe area).
   Keep the header and action buttons visible; only the form content scrolls. */
@supports (height: 100dvh) {
  .admin-record-modal.modal-content { max-height: calc(100dvh - 1rem); }
}
.admin-record-modal .modal-content {
  display: flex;
  flex-direction: column;
  min-height: 0;
  overflow: hidden;
}
.admin-record-modal .modal-header,
.admin-record-modal .modal-footer {
  flex: 0 0 auto;
  position: relative;
  z-index: 2;
}
.admin-record-modal .modal-body {
  flex: 1 1 auto;
  min-height: 0;
  max-height: none;
  overflow-y: auto !important;
  overflow-x: hidden;
  -webkit-overflow-scrolling: touch;
  overscroll-behavior-y: contain;
}
.admin-record-modal .modal-footer {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: .5rem;
  padding-bottom: calc(.75rem + env(safe-area-inset-bottom, 0px));
  background: #fffdf6;
}
.admin-record-modal .modal-footer .btn,
.admin-record-modal .modal-header .btn-close {
  min-height: 44px;
  touch-action: manipulation;
}
.admin-record-modal .modal-footer .btn { white-space: normal; }

@media (max-width: 767.98px), (max-height: 600px) {
  .admin-record-modal.modal { padding: 0 !important; overflow: hidden; }
  .admin-record-modal .modal-dialog,
  .admin-record-modal .modal-dialog.modal-dialog-scrollable {
    width: calc(100% - 1rem);
    max-width: none;
    height: calc(100dvh - 1rem);
    max-height: calc(100dvh - 1rem);
    margin: .5rem auto;
  }
  .admin-record-modal .modal-content {
    height: calc(100dvh - 1rem);
    max-height: calc(100dvh - 1rem);
    border-radius: .9rem;
  }
  .admin-record-modal .modal-header {
    padding: .8rem 3.5rem .8rem .9rem;
    align-items: flex-start;
  }
  .admin-record-modal .modal-header .modal-title {
    min-width: 0;
    font-size: clamp(1rem, 4.2vw, 1.2rem);
    line-height: 1.25;
    overflow-wrap: anywhere;
  }
  .admin-record-modal .modal-header small { line-height: 1.35; }
  .admin-record-modal .modal-body { padding: .75rem !important; }
  .admin-record-modal .modal-body .row { --bs-gutter-x: .75rem; }
  .admin-record-modal .modal-body .card { min-width: 0; }
  .admin-record-modal .modal-body input,
  .admin-record-modal .modal-body select,
  .admin-record-modal .modal-body textarea,
  .admin-record-modal .modal-body .form-control,
  .admin-record-modal .modal-body .form-select { max-width: 100%; min-width: 0; }
  .admin-record-modal .modal-footer {
    flex-wrap: wrap !important;
    justify-content: stretch;
    gap: .45rem;
    padding: .6rem .7rem calc(.6rem + env(safe-area-inset-bottom, 0px));
  }
  .admin-record-modal .modal-footer > #backStep {
    flex: 1 1 calc(50% - .45rem);
    width: auto !important;
    margin: 0 !important;
  }
  .admin-record-modal .modal-footer > .ms-auto {
    display: flex !important;
    flex: 1 1 100%;
    flex-wrap: wrap !important;
    gap: .45rem;
    width: 100%;
    min-width: 0;
    margin: 0 !important;
  }
  .admin-record-modal .modal-footer > .ms-auto > .btn,
  .admin-record-modal .modal-footer > .ms-auto > .portal-btn,
  .admin-record-modal .modal-footer > .ms-auto > .admin-btn {
    flex: 1 1 calc(50% - .45rem);
    min-width: 0;
    width: auto !important;
    margin: 0 !important;
    justify-content: center;
  }
  .admin-record-modal .modal-footer #continueStep:not(.d-none),
  .admin-record-modal .modal-footer #nextStep:not(.d-none),
  .admin-record-modal .modal-footer #saveViolation:not(.d-none) {
    flex: 1 1 100%;
    width: 100% !important;
    min-height: 46px;
    order: 10;
  }
}

@media (max-width: 359.98px) {
  .admin-record-modal .modal-footer > #backStep,
  .admin-record-modal .modal-footer > .ms-auto > .btn,
  .admin-record-modal .modal-footer > .ms-auto > .portal-btn,
  .admin-record-modal .modal-footer > .ms-auto > .admin-btn {
    flex-basis: 100%;
    width: 100% !important;
  }
}

</style>