@extends('layouts.admin')

@section('title', 'Violation Correction & Deletion Requests')

@section('content')
<style>
    .review-summary { min-width: 190px; }
    .review-modal-section { border: 1px solid #e8e8ed; border-radius: .8rem; padding: 1rem; height: 100%; }
    .review-modal-label { color: #6c757d; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .035em; margin-bottom: .3rem; }
    .review-evidence-image { display:block; max-width:100%; max-height:420px; object-fit:contain; margin:auto; border-radius:.5rem; }
    .review-evidence-pdf { width:100%; height: min(65vh, 650px); border:1px solid #dee2e6; border-radius:.5rem; }
    .review-action-card { border:1px solid #e5e7eb; border-radius:.8rem; padding:1rem; height:100%; }
    .review-action-card.approve { border-top:3px solid #198754; background:#f8fcf9; }
    .review-action-card.reject { border-top:3px solid #dc3545; background:#fffafa; }
</style>

<div class="container-fluid">
    <div class="page-header-modern mb-3">
        <div>
            <h3 class="mb-1">Violation Correction &amp; Deletion Requests</h3>
            <p class="mb-0">Review student requests to correct factual details or request deletion of a violation record.</p>
        </div>
    </div>

    @if(session('success') || session('error') || $errors->has('correction_request'))
        <div id="decision-alert-data"
             data-success="{{ session('success') }}"
             data-error="{{ session('error') ?? $errors->first('correction_request') ?? $errors->first('review_remarks') }}"
             hidden></div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Pending Correction &amp; Deletion Requests</h5>
            <span class="badge bg-secondary">{{ $requests->total() }} request(s)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Request Type</th>
                            <th>Violation</th>
                            <th>Submitted</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($requests as $correctionRequest)
                        @php
                            $violation = $correctionRequest->violation;
                            $student = $violation?->student;
                            $currentType = $violation?->violation_type_display ?? 'Unknown Violation';
                            $requestedType = $correctionRequest->requestedViolationType?->violation_type ?? $currentType;
                            $hasAssignedSanction = \App\Models\StudentSanctionRecord::where('violation_id', $correctionRequest->violation_id)->exists();
                        @endphp
                        <tr>
                            <td class="review-summary">
                                <div class="fw-semibold">{{ trim(($student?->first_name ?? '') . ' ' . ($student?->last_name ?? '')) ?: 'Student' }}</div>
                                <div class="small text-muted">{{ $correctionRequest->student_number }}</div>
                            </td>
                            <td>
                                <span class="badge {{ $correctionRequest->isDeletionRequest() ? 'bg-danger' : 'bg-primary' }}">
                                    {{ $correctionRequest->isDeletionRequest() ? 'Deletion' : 'Correction' }}
                                </span>
                                @if($hasAssignedSanction && !$correctionRequest->isDeletionRequest())
                                    <div class="mt-1"><span class="badge bg-warning text-dark">Sanction Assigned</span></div>
                                @endif
                            </td>
                            <td><span class="badge bg-light text-dark">#{{ $correctionRequest->violation_id }}</span><div class="small mt-1">{{ $currentType }}</div></td>
                            <td class="text-nowrap">{{ $correctionRequest->created_at?->format('M d, Y h:i A') ?? 'N/A' }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#reviewRequestModal{{ $correctionRequest->request_id }}">
                                    <i class="bi bi-eye me-1"></i>Review Request
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-check2-circle fs-3 d-block mb-2"></i>No pending violation change requests.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">{{ $requests->links() }}</div>
    </div>

    {{-- Keep the modal markup outside the table so the table remains compact and valid. --}}
    @foreach($requests as $correctionRequest)
        @php
            $violation = $correctionRequest->violation;
            $student = $violation?->student;
            $currentType = $violation?->violation_type_display ?? 'Unknown Violation';
            $currentCategory = $violation?->violationType?->violationCategory?->category_name ?? 'Not recorded';
            $requestedType = $correctionRequest->requestedViolationType?->violation_type ?? $currentType;
            $requestedCategory = $correctionRequest->requestedViolationType?->violationCategory?->category_name ?? 'Not specified';
            $evidenceFiles = $correctionRequest->isDeletionRequest() ? ($correctionRequest->deletion_evidence ?? []) : [];
        @endphp
        <div class="modal fade" id="reviewRequestModal{{ $correctionRequest->request_id }}" tabindex="-1" aria-labelledby="reviewRequestTitle{{ $correctionRequest->request_id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title fw-bold" id="reviewRequestTitle{{ $correctionRequest->request_id }}">
                                {{ $correctionRequest->isDeletionRequest() ? 'Review Deletion Request' : 'Review Correction Request' }}
                            </h5>
                            <div class="small text-muted">Request #{{ $correctionRequest->request_id }} · Violation #{{ $correctionRequest->violation_id }}</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-4"><div class="review-modal-section"><div class="review-modal-label">Student</div><div class="fw-semibold">{{ trim(($student?->first_name ?? '') . ' ' . ($student?->last_name ?? '')) ?: 'Student' }}</div><div class="small text-muted">{{ $correctionRequest->student_number }}</div></div></div>
                            <div class="col-12 col-md-4"><div class="review-modal-section"><div class="review-modal-label">Request submitted</div><div class="fw-semibold">{{ $correctionRequest->created_at?->format('M d, Y h:i A') ?? 'N/A' }}</div><div class="mt-2"><span class="badge {{ $correctionRequest->isDeletionRequest() ? 'bg-danger' : 'bg-primary' }}">{{ $correctionRequest->isDeletionRequest() ? 'Deletion' : 'Correction' }}</span></div></div></div>
                            <div class="col-12 col-md-4"><div class="review-modal-section"><div class="review-modal-label">Student's reason</div><div style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $correctionRequest->reason ?: 'No reason provided.' }}</div></div></div>
                        </div>

                        @if(!$correctionRequest->isDeletionRequest())
                            <h6 class="fw-bold mb-2">Compare violation details</h6>
                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-6"><div class="review-modal-section bg-light">
                                    <div class="review-modal-label">Current record</div>
                                    <div class="mb-2"><span class="small text-muted">Category</span><div class="fw-semibold">{{ $currentCategory }}</div></div>
                                    <div class="mb-2"><span class="small text-muted">Violation type</span><div class="fw-semibold">{{ $currentType }}</div></div>
                                    <div class="mb-2"><span class="small text-muted">Date and time</span><div class="fw-semibold">{{ $violation?->violation_date ? \Carbon\Carbon::parse($violation->violation_date)->format('M d, Y h:i A') : 'N/A' }}</div></div>
                                    <div><span class="small text-muted">Description / remarks</span><div style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $violation?->description ?: 'No remarks' }}</div></div>
                                </div></div>
                                <div class="col-12 col-md-6"><div class="review-modal-section">
                                    <div class="review-modal-label">Student's requested correction</div>
                                    <div class="mb-2"><span class="small text-muted">Requested category</span><div class="fw-semibold">{{ $requestedCategory }}</div></div>
                                    <div class="mb-2"><span class="small text-muted">Requested violation type</span><div class="fw-semibold">{{ $requestedType }}</div></div>
                                    <div class="mb-2"><span class="small text-muted">Requested date and time</span><div class="fw-semibold">{{ $correctionRequest->requested_violation_date?->format('M d, Y h:i A') ?? 'N/A' }}</div></div>
                                    <div><span class="small text-muted">Requested description / remarks</span><div style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $correctionRequest->requested_description ?: 'No remarks provided' }}</div></div>
                                </div></div>
                            </div>
                        @else
                            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i>The student is requesting removal of this violation from active records. Review the reason and evidence before deciding.</div>
                            <h6 class="fw-bold mb-2">Violation record requested for deletion</h6>
                            <div class="review-modal-section mb-3">
                                <div class="row g-3">
                                    <div class="col-12 col-md-4"><div class="review-modal-label">Category</div><div>{{ $currentCategory }}</div></div>
                                    <div class="col-12 col-md-4"><div class="review-modal-label">Violation type</div><div>{{ $currentType }}</div></div>
                                    <div class="col-12 col-md-4"><div class="review-modal-label">Date and time</div><div>{{ $violation?->violation_date ? \Carbon\Carbon::parse($violation->violation_date)->format('M d, Y h:i A') : 'N/A' }}</div></div>
                                    <div class="col-12"><div class="review-modal-label">Description / remarks</div><div style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $violation?->description ?: 'No remarks' }}</div></div>
                                </div>
                            </div>
                            <h6 class="fw-bold mb-2">Student's supporting evidence</h6>
                            @if(count($evidenceFiles))
                                <div class="row g-3 mb-3">
                                @foreach($evidenceFiles as $fileIndex => $file)
                                    @php
                                        $evidenceUrl = route('violation-change-requests.evidence', ['correctionRequest' => $correctionRequest->request_id, 'fileIndex' => $fileIndex]);
                                        $evidenceName = $file['name'] ?? 'Evidence file';
                                        $evidenceMime = strtolower($file['mime'] ?? '');
                                        $evidenceExtension = strtolower(pathinfo($evidenceName, PATHINFO_EXTENSION));
                                        $isImageEvidence = str_starts_with($evidenceMime, 'image/') || in_array($evidenceExtension, ['jpg','jpeg','png','webp','gif'], true);
                                        $isPdfEvidence = $evidenceMime === 'application/pdf' || $evidenceExtension === 'pdf';
                                    @endphp
                                    <div class="col-12 {{ $isImageEvidence || $isPdfEvidence ? 'col-lg-6' : '' }}">
                                        <div class="review-modal-section">
                                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2"><div class="fw-semibold text-break"><i class="bi {{ $isPdfEvidence ? 'bi-file-earmark-pdf text-danger' : ($isImageEvidence ? 'bi-file-earmark-image text-primary' : 'bi-paperclip') }} me-1"></i>{{ $evidenceName }}</div><a class="btn btn-sm btn-outline-secondary flex-shrink-0" href="{{ $evidenceUrl }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Open</a></div>
                                            @if($isImageEvidence)
                                                <a href="{{ $evidenceUrl }}" target="_blank" rel="noopener"><img class="review-evidence-image" src="{{ $evidenceUrl }}" alt="Student-submitted evidence: {{ $evidenceName }}" loading="lazy"></a>
                                            @elseif($isPdfEvidence)
                                                <iframe class="review-evidence-pdf" src="{{ $evidenceUrl }}#toolbar=1&navpanes=0" title="PDF evidence: {{ $evidenceName }}"></iframe>
                                            @else
                                                <div class="small text-muted">Preview is not available for this file type. Use Open to view or download the file.</div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                                </div>
                            @else
                                <div class="alert alert-light border">No supporting evidence was attached to this deletion request.</div>
                            @endif
                        @endif

                        <hr class="my-4">
                        <h6 class="fw-bold mb-3">Administrator decision</h6>
                        <p class="text-muted small">Choose an action. You will be asked for remarks and then a final confirmation before the decision is submitted.</p>
                        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-end">
                            <button type="button"
                                    class="btn btn-outline-danger"
                                    data-decision-action="reject"
                                    data-review-modal="reviewRequestModal{{ $correctionRequest->request_id }}"
                                    data-remarks-modal="decisionRemarksModal{{ $correctionRequest->request_id }}">
                                <i class="bi bi-x-circle me-1"></i>Reject Request
                            </button>
                            <button type="button"
                                    class="btn btn-success"
                                    data-decision-action="approve"
                                    data-review-modal="reviewRequestModal{{ $correctionRequest->request_id }}"
                                    data-remarks-modal="decisionRemarksModal{{ $correctionRequest->request_id }}">
                                <i class="bi bi-check-circle me-1"></i>Approve Request
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Remarks modal: the admin must review the remarks before final confirmation. --}}
        <div class="modal fade" id="decisionRemarksModal{{ $correctionRequest->request_id }}" tabindex="-1" aria-labelledby="decisionRemarksTitle{{ $correctionRequest->request_id }}" aria-hidden="true" data-approve-url="{{ route('violation-correction-requests.approve', ['correctionRequest' => $correctionRequest->request_id]) }}" data-reject-url="{{ route('violation-correction-requests.reject', ['correctionRequest' => $correctionRequest->request_id]) }}">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form class="decision-remarks-form">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" id="decisionRemarksTitle{{ $correctionRequest->request_id }}"><span class="decision-action-label">Review Request</span></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label decision-remarks-label" for="decisionRemarksInput{{ $correctionRequest->request_id }}">Remarks</label>
                            <textarea id="decisionRemarksInput{{ $correctionRequest->request_id }}" class="form-control decision-remarks-input" rows="4" maxlength="2000" placeholder="Enter remarks"></textarea>
                            <div class="form-text decision-remarks-help mt-2">Remarks are optional for approval and required for rejection.</div>
                            <div class="text-danger small mt-2 decision-remarks-error d-none" role="alert"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Continue</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Final confirmation modal. No request is submitted until Confirm is clicked. --}}
        <div class="modal fade" id="decisionConfirmModal{{ $correctionRequest->request_id }}" tabindex="-1" aria-labelledby="decisionConfirmTitle{{ $correctionRequest->request_id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="decisionConfirmTitle{{ $correctionRequest->request_id }}">Are you sure?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="decision-confirm-message mb-0">Are you sure you want to process this request?</p>
                        <div class="small text-muted mt-2">This action will be recorded by the system.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn decision-confirm-submit btn-success">Confirm</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Hidden POST form uses the existing Laravel routes and CSRF protection. --}}
        <form id="decisionSubmitForm{{ $correctionRequest->request_id }}" method="POST" class="d-none">
            @csrf
            <input type="hidden" name="review_remarks" value="">
        </form>
    @endforeach
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Display the server's actual flash response, not a client-side success guess.
    const alertData = document.getElementById('decision-alert-data');
    if (alertData) {
        const successMessage = alertData.dataset.success;
        const errorMessage = alertData.dataset.error;
        if (successMessage || errorMessage) {
            if (typeof window.Swal !== 'undefined') {
                if (successMessage) {
                    window.Swal.fire({ icon: 'success', title: 'Successful!', text: successMessage, confirmButtonText: 'OK' });
                } else {
                    window.Swal.fire({ icon: 'error', title: 'Action Failed', text: errorMessage, confirmButtonText: 'OK' });
                }
            } else {
                window.alert(successMessage || errorMessage);
            }
        }
    }

    const actionButtons = document.querySelectorAll('[data-decision-action]');
    actionButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            if (typeof window.bootstrap === 'undefined' || !window.bootstrap.Modal) {
                window.alert('The modal library did not load. Please refresh the page and try again.');
                return;
            }

            const action = button.dataset.decisionAction;
            const reviewModalElement = document.getElementById(button.dataset.reviewModal);
            const remarksModalElement = document.getElementById(button.dataset.remarksModal);
            if (!reviewModalElement || !remarksModalElement) return;

            const idSuffix = button.dataset.remarksModal.replace('decisionRemarksModal', '');
            const confirmModalElement = document.getElementById('decisionConfirmModal' + idSuffix);
            const submitForm = document.getElementById('decisionSubmitForm' + idSuffix);
            const remarksInput = remarksModalElement.querySelector('.decision-remarks-input');
            const remarksError = remarksModalElement.querySelector('.decision-remarks-error');
            const actionLabel = remarksModalElement.querySelector('.decision-action-label');
            const remarksLabel = remarksModalElement.querySelector('.decision-remarks-label');
            const remarksHelp = remarksModalElement.querySelector('.decision-remarks-help');
            const confirmMessage = confirmModalElement?.querySelector('.decision-confirm-message');
            const confirmButton = confirmModalElement?.querySelector('.decision-confirm-submit');
            const remarksForm = remarksModalElement.querySelector('.decision-remarks-form');
            const hiddenRemarks = submitForm?.querySelector('[name="review_remarks"]');
            if (!confirmModalElement || !submitForm || !remarksInput || !remarksError || !actionLabel || !remarksLabel || !remarksHelp || !confirmMessage || !confirmButton || !remarksForm || !hiddenRemarks) return;

            const isApprove = action === 'approve';
            remarksInput.value = '';
            remarksError.textContent = '';
            remarksError.classList.add('d-none');
            remarksInput.required = !isApprove;
            remarksInput.minLength = isApprove ? 0 : 10;
            actionLabel.textContent = isApprove ? 'Approve Request' : 'Reject Request';
            remarksLabel.textContent = isApprove ? 'Approval remarks (optional)' : 'Reason for rejection (required)';
            remarksHelp.textContent = isApprove ? 'You may leave this field blank.' : 'Enter at least 10 characters explaining your decision.';
            remarksInput.placeholder = isApprove ? 'Optional approval remarks' : 'Explain why this request is rejected';
            confirmMessage.textContent = isApprove ? 'Are you sure you want to approve this request?' : 'Are you sure you want to reject this request?';
            confirmButton.textContent = isApprove ? 'Confirm Approval' : 'Confirm Rejection';
            confirmButton.classList.toggle('btn-success', isApprove);
            confirmButton.classList.toggle('btn-danger', !isApprove);
            hiddenRemarks.value = '';
            submitForm.action = isApprove ? remarksModalElement.dataset.approveUrl : remarksModalElement.dataset.rejectUrl;
            if (!submitForm.action) {
                window.alert('The submission route is missing. Please contact the system administrator.');
                return;
            }

            // Remove previous per-opening handlers to avoid duplicate transitions.
            remarksForm.onsubmit = null;
            confirmButton.onclick = null;

            const reviewModal = window.bootstrap.Modal.getOrCreateInstance(reviewModalElement);
            reviewModalElement.addEventListener('hidden.bs.modal', function openRemarks() {
                window.bootstrap.Modal.getOrCreateInstance(remarksModalElement).show();
            }, { once: true });
            reviewModal.hide();

            remarksForm.onsubmit = function (event) {
                event.preventDefault();
                const remarks = remarksInput.value.trim();
                if (!isApprove && remarks.length < 10) {
                    remarksError.textContent = 'The rejection reason must contain at least 10 characters.';
                    remarksError.classList.remove('d-none');
                    remarksInput.focus();
                    return;
                }
                remarksError.textContent = '';
                remarksError.classList.add('d-none');
                hiddenRemarks.value = remarks;
                remarksModalElement.addEventListener('hidden.bs.modal', function openConfirmation() {
                    window.bootstrap.Modal.getOrCreateInstance(confirmModalElement).show();
                }, { once: true });
                window.bootstrap.Modal.getOrCreateInstance(remarksModalElement).hide();
            };

            confirmButton.onclick = function () {
                if (confirmButton.disabled) return;
                confirmButton.disabled = true;
                confirmButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Processing...';
                submitForm.submit();
            };

            confirmModalElement.addEventListener('hidden.bs.modal', function resetConfirmButton() {
                confirmButton.disabled = false;
                confirmButton.textContent = isApprove ? 'Confirm Approval' : 'Confirm Rejection';
            }, { once: true });
        });
    });
});
</script>
@endsection
