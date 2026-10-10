{{--
    OSS Staff → Violations → Resubmit Rejected Violation modal.

    Lifted out of the Submitted Violations panel so it stays a direct child of
    the document: Bootstrap's inactive `.tab-pane` is `display:none`, which would
    also hide a modal nested inside it.
--}}
<div class="modal fade" id="resubmitViolationModal" tabindex="-1" aria-labelledby="resubmitViolationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
<div class="modal-header text-white" style="background: #800000;">
                    <h5 class="modal-title fw-bold" id="resubmitViolationModalLabel">
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
