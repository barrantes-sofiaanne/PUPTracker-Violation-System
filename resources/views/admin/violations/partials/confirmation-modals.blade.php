{{--
    Admin → Violations → Violation Confirmation modals.

    Lifted verbatim out of the Confirmations panel so they stay direct children
    of the document: Bootstrap hides an inactive `.tab-pane` with
    `display:none`, which would also hide a modal nested inside it.

    These modals are only rendered when the Confirmations data is present, so
    the Management/Configuration/History tabs do not carry unused markup.
--}}
@if(isset($violations))
{{-- Review + Confirm / Deny modal --}}
<div class="modal fade admin-modal" id="violationDetailModal" tabindex="-1" aria-labelledby="violationDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content admin-modal-content">
            <div class="modal-header admin-modal-header">
                <h5 class="modal-title admin-modal-title" id="violationDetailModalLabel">Review Violation</h5>
                <button type="button" class="btn-close admin-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body admin-modal-body">
                <div class="mb-2"><strong>Student Number:</strong> <span id="confirmModalStudent">-</span></div>
                <div class="mb-2"><strong>Violation:</strong> <span id="confirmModalViolation">-</span></div>
                <div class="mb-2"><strong>Date:</strong> <span id="confirmModalDate">-</span></div>
                <div class="mb-2"><strong>Recorded By:</strong> <span id="confirmModalRecordedBy">-</span></div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Description / Remarks</label>
                    <div class="border rounded p-3 bg-light" id="confirmModalDescription"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Supporting Evidence</label>
                    <div class="border rounded p-3 bg-light d-flex flex-wrap gap-2" id="confirmModalEvidence">
                        <span class="text-muted small">No evidence attached.</span>
                    </div>
                </div>

                <div class="alert alert-info small mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Confirming adds this violation to the student's record. Denying requires a reason and notifies the student and the recording security officer.
                </div>
            </div>
            <div class="modal-footer admin-modal-footer d-flex justify-content-between">
                <button type="button" class="admin-btn admin-btn-danger" data-bs-toggle="modal" data-bs-target="#denyViolationModal" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Deny
                </button>

                <div class="d-flex gap-2">
                    <button type="button" class="admin-btn admin-btn-neutral" data-bs-dismiss="modal">Close</button>
                    <form id="confirmViolationForm" method="POST">
                        @csrf
                        <button type="submit" class="admin-btn admin-btn-success">
                            <i class="bi bi-check-circle me-1"></i>Confirm Violation
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Deny with reason modal --}}
<div class="modal fade admin-modal" id="denyViolationModal" tabindex="-1" aria-labelledby="denyViolationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content admin-modal-content">
            <div class="modal-header admin-modal-header">
                <h5 class="modal-title admin-modal-title" id="denyViolationModalLabel">Deny Violation</h5>
                <button type="button" class="btn-close admin-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="denyViolationForm" method="POST">
                @csrf
                <div class="modal-body admin-modal-body">
                    <p class="text-muted">
                        The recorded violation will be marked as denied and will <strong>not</strong> appear on the student's record. Provide a reason below.
                    </p>
                    <div class="mb-2"><strong>Student Number:</strong> <span id="denyModalStudent">-</span></div>
                    <label for="denyReason" class="form-label fw-semibold mt-2">Reason <span class="text-danger">*</span></label>
                    <textarea name="reason" id="denyReason" class="form-control" rows="3" placeholder="Required — explain why this violation is being denied..." maxlength="1000" required></textarea>
                </div>
                <div class="modal-footer admin-modal-footer">
                    <button type="button" class="admin-btn admin-btn-neutral" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="admin-btn admin-btn-danger">
                        <i class="bi bi-x-circle me-1"></i>Deny Violation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Evidence Security Events timeline modal --}}
<div class="modal fade admin-modal" id="evidenceEventsModal" tabindex="-1" aria-labelledby="evidenceEventsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content admin-modal-content">
            <div class="modal-header admin-modal-header">
                <h5 class="modal-title admin-modal-title" id="evidenceEventsModalLabel">Evidence Security Events</h5>
                <button type="button" class="btn-close admin-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body admin-modal-body">
                <div class="mb-2">
                    <strong>Evidence ID:</strong> <span id="evidenceEventsCode">-</span>
                </div>
                <div class="alert alert-warning small mb-3">
                    <i class="bi bi-shield-exclamation me-1"></i>
                    These events are <strong>detection indicators</strong> recorded during evidence capture and access. A blur, focus loss, or possible screen-capture event does <strong>not</strong> prove a screenshot occurred — it may be caused by switching applications, browser UI, notifications, or other legitimate interruptions. Events require administrative review and are never an automatic basis for disciplinary action.
                </div>
                <div id="evidenceEventsLoading" class="text-center py-4 text-muted">
                    <div class="spinner-border spinner-border-sm me-2"></div>Loading security events…
                </div>
                <div id="evidenceEventsEmpty" class="text-center py-4 text-muted d-none">
                    No security events recorded for this evidence.
                </div>
                <ul id="evidenceEventsList" class="list-group list-group-flush"></ul>
            </div>
            <div class="modal-footer admin-modal-footer">
                <button type="button" class="admin-btn admin-btn-neutral" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endif
