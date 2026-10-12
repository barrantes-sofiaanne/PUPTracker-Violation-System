{{--
    Security → Violations → Resubmit Rejected Violation modal.

    Lifted out of the Submitted Violations panel so it stays a direct child of
    the document: Bootstrap's inactive `.tab-pane` is `display:none`, which would
    also hide a modal nested inside it.
--}}


<style>
    /* Security: Resubmit Violation modal */
    #resubmitViolationModal .modal-dialog {
        width: calc(100% - 2rem);
        max-width: 900px;
        margin: 1rem auto;
    }

    #resubmitViolationModal .modal-content {
        display: flex;
        flex-direction: column;
        max-height: calc(100vh - 2rem);
        max-height: calc(100dvh - 2rem);
        overflow: hidden;
        border: 0;
        border-radius: 12px;
    }

    /* Keep the header visible while the form scrolls */
    #resubmitViolationModal .modal-header {
        position: relative;
        flex-shrink: 0;
        padding: 1rem 4rem 1rem 1.25rem;
        background: #800000 !important;
        color: #fff !important;
    }

    #resubmitViolationModal .modal-header .modal-title,
    #resubmitViolationModal .modal-header .modal-title i {
        color: #fff !important;
    }

    #resubmitViolationModal .modal-header .btn-close {
        position: absolute;
        top: 50%;
        right: 0.75rem;
        transform: translateY(-50%);
        width: 44px;
        height: 44px;
        margin: 0 !important;
        padding: 0;
        flex-shrink: 0;
        z-index: 10;
    }

    /* Allow the form body to scroll independently */
    #resubmitViolationModal #resubmitViolationForm {
        display: flex;
        flex-direction: column;
        flex: 1 1 auto;
        min-height: 0;
        overflow: hidden;
    }

    #resubmitViolationModal .modal-body {
        flex: 1 1 auto;
        min-height: 0;
        overflow-x: hidden;
        overflow-y: auto;
        padding: 1.25rem;
        -webkit-overflow-scrolling: touch;
    }

    #resubmitViolationModal .modal-body .row {
        --bs-gutter-x: 1rem;
        --bs-gutter-y: 1rem;
    }

    #resubmitViolationModal .form-control {
        display: block;
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }

    #resubmitViolationModal .modal-footer {
        display: flex;
        flex-shrink: 0;
        flex-wrap: wrap;
        gap: 0.5rem;
        padding: 1rem 1.25rem;
    }

    /* Tablets and mobile phones */
    @media (max-width: 767.98px) {
        #resubmitViolationModal .modal-dialog {
            width: auto;
            max-width: none;
            margin: 0.5rem;
        }

        #resubmitViolationModal .modal-content {
            max-height: calc(100vh - 1rem);
            max-height: calc(100dvh - 1rem);
            border-radius: 10px;
        }

        #resubmitViolationModal .modal-header {
            padding: 0.85rem 3.5rem 0.85rem 1rem;
        }

        #resubmitViolationModal .modal-title {
            font-size: 1.1rem;
            line-height: 1.35;
        }

        #resubmitViolationModal .modal-body {
            padding: 1rem;
        }

        #resubmitViolationModal .modal-body .row {
            --bs-gutter-x: 0.75rem;
            --bs-gutter-y: 0.75rem;
        }

        /* Stack Student, Violation Type and Date fields */
        #resubmitViolationModal .modal-body .col-md-6 {
            width: 100%;
        }

        /* Prevent mobile browser zoom on form focus */
        #resubmitViolationModal .form-control,
        #resubmitViolationModal input[type="file"],
        #resubmitViolationModal textarea {
            font-size: 16px;
        }

        #resubmitViolationModal textarea {
            min-height: 120px;
        }

        #resubmitViolationModal .alert {
            overflow-wrap: anywhere;
        }

        #resubmitViolationModal .form-text {
            overflow-wrap: anywhere;
        }

        #resubmitViolationModal .modal-footer {
            padding: 0.75rem 1rem;
        }

        #resubmitViolationModal .modal-footer .btn {
            min-height: 44px;
        }

        #resubmitViolationModal .modal-footer .btn[type="submit"] {
            flex: 1 1 100%;
        }
    }

    /* Extra-small phones */
    @media (max-width: 380px) {
        #resubmitViolationModal .modal-dialog {
            margin: 0.25rem;
        }

        #resubmitViolationModal .modal-content {
            max-height: calc(100vh - 0.5rem);
            max-height: calc(100dvh - 0.5rem);
        }

        #resubmitViolationModal .modal-header {
            padding-left: 0.75rem;
        }

        #resubmitViolationModal .modal-body {
            padding: 0.75rem;
        }

        #resubmitViolationModal .modal-footer {
            padding: 0.75rem;
        }
    }
</style>


<div class="modal fade" id="resubmitViolationModal" tabindex="-1" aria-labelledby="resubmitViolationModalLabel" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
<div class="modal-header text-white"
     style="background: #800000; color: #fff !important;">                <h5 class="modal-title fw-bold" id="resubmitViolationModalLabel">
                    <i class="bi bi-arrow-repeat me-2"></i>Resubmit Violation
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="resubmitViolationForm" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" id="resubmitViolationId" name="violation_id">
                <div class="modal-body">
                    <div class="alert alert-warning small">
                        <strong><i class="bi bi-info-circle me-1"></i>Administrator's reason for rejection:</strong>
                        <div id="resubmitReason" class="mt-1">—</div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Student</label>
                            <input type="text" id="resubmitStudent" class="form-control" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Violation Type</label>
                            <input type="text" id="resubmitViolationType" class="form-control" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Violation Date &amp; Time</label>
                            <input type="datetime-local" id="resubmitViolationDate" name="violation_date" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                            <textarea id="resubmitDescription" name="description" class="form-control" rows="4" required placeholder="Enter the corrected violation description"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Supporting Evidence (optional)</label>
                            <input type="file" id="resubmitEvidence" name="evidence[]" class="form-control" accept=".jpg,.jpeg,.png,.pdf" multiple>
                            <div class="form-text">JPG, PNG or PDF. Max 5 files, up to 5 MB each.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn portal-btn" id="resubmitSubmitBtn">
                        <i class="bi bi-send me-1"></i>Resubmit for Review
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
