{{--
    Security → Violations → Resubmit Rejected Violation modal.

    Lifted out of the Submitted Violations panel so it stays a direct child of
    the document: Bootstrap's inactive `.tab-pane` is `display:none`, which would
    also hide a modal nested inside it.
--}}

<style>
    #resubmitViolationModal .modal-content {
        max-height: calc(100vh - 1rem);
    }

    #resubmitViolationModal .modal-body {
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }

    #resubmitViolationModal .modal-header {
        position: relative;
        padding-right: 4rem;
        background: #800000 !important;
        color: #fff !important;
    }

    #resubmitViolationModal .modal-header .modal-title {
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

        z-index: 10;
    }

    #resubmitViolationModal .modal-footer {
        flex-shrink: 0;
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    @media (max-width: 575.98px) {
        #resubmitViolationModal .modal-dialog {
            margin: 0.5rem;
        }

        #resubmitViolationModal .modal-footer {
            padding: 0.75rem;
        }

        #resubmitViolationModal .modal-footer .btn {
            min-height: 44px;
        }

        #resubmitViolationModal .modal-footer .btn[type="submit"] {
            flex: 1 1 100%;
        }
    }

    #resubmitViolationModal .modal-title,
#resubmitViolationModal .modal-title i {
    color: #fff !important;
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
