@extends('layouts.admin')

@section('title', 'Violation Correction & Deletion Requests')

@section('content')
<style>
    .review-summary {
        min-width: 190px;
    }

    .review-modal-section {
        border: 1px solid #e8e8ed;
        border-radius: .8rem;
        padding: 1rem;
        height: 100%;
    }

    .review-modal-label {
        color: #6c757d;
        font-size: .78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .035em;
        margin-bottom: .3rem;
    }

    .review-evidence-image {
        display: block;
        max-width: 100%;
        max-height: 420px;
        object-fit: contain;
        margin: auto;
        border-radius: .5rem;
    }

    .review-evidence-pdf {
        width: 100%;
        height: min(65vh, 650px);
        border: 1px solid #dee2e6;
        border-radius: .5rem;
    }

    .review-action-card {
        border: 1px solid #e5e7eb;
        border-radius: .8rem;
        padding: 1rem;
        height: 100%;
    }

    .review-action-card.approve {
        border-top: 3px solid #198754;
        background: #f8fcf9;
    }

    .review-action-card.reject {
        border-top: 3px solid #dc3545;
        background: #fffafa;
    }
</style>

<div class="container-fluid">
    <div class="page-header-modern mb-3">
        <div>
            <h3 class="mb-1">
                Violation Correction &amp; Deletion Requests
            </h3>
            <p class="mb-0">
                Review student requests to correct factual details
                or request deletion of a violation record.
            </p>
        </div>
    </div>

@php

@if(session('success') || session('error') || $errors->has('correction_request'))
    <div
        id="decision-alert-data"
        data-success="{{ session('success') }}"
        data-error="{{ session('error') ?? $errors->first('correction_request') }}"
        hidden>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const alertData = document.getElementById('decision-alert-data');

        if (!alertData) {
            return;
        }

        const successMessage = alertData.dataset.success;
        const errorMessage = alertData.dataset.error;

        if (typeof Swal === 'undefined') {
            if (successMessage) {
                alert(successMessage);
            } else if (errorMessage) {
                alert(errorMessage);
            }
            return;
        }

        if (successMessage) {
            Swal.fire({
                icon: 'success',
                title: 'Successful!',
                text: successMessage,
                confirmButtonText: 'OK'
            });
        } else if (errorMessage) {
            Swal.fire({
                icon: 'error',
                title: 'Action Failed',
                text: errorMessage,
                confirmButtonText: 'OK'
            });
        }
    });
    </script>
@endif


</script>



    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                Pending Correction &amp; Deletion Requests
            </h5>

            <span class="badge bg-secondary">
                {{ $requests->total() }} request(s)
            </span>
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
                            $currentType = $violation?->violation_type_display
                                ?? 'Unknown Violation';

                            $hasAssignedSanction =
                                \App\Models\StudentSanctionRecord::where(
                                    'violation_id',
                                    $correctionRequest->violation_id
                                )->exists();
                        @endphp

                        <tr>
                            <td class="review-summary">
                                <div class="fw-semibold">
                                    {{ trim(
                                        ($student?->first_name ?? '') . ' ' .
                                        ($student?->last_name ?? '')
                                    ) ?: 'Student' }}
                                </div>

                                <div class="small text-muted">
                                    {{ $correctionRequest->student_number }}
                                </div>
                            </td>

                            <td>
                                <span class="badge {{
                                    $correctionRequest->isDeletionRequest()
                                        ? 'bg-danger'
                                        : 'bg-primary'
                                }}">
                                    {{
                                        $correctionRequest->isDeletionRequest()
                                            ? 'Deletion'
                                            : 'Correction'
                                    }}
                                </span>

                                @if(
                                    $hasAssignedSanction &&
                                    !$correctionRequest->isDeletionRequest()
                                )
                                    <div class="mt-1">
                                        <span class="badge bg-warning text-dark">
                                            Sanction Assigned
                                        </span>
                                    </div>
                                @endif
                            </td>

                            <td>
                                <span class="badge bg-light text-dark">
                                    #{{ $correctionRequest->violation_id }}
                                </span>

                                <div class="small mt-1">
                                    {{ $currentType }}
                                </div>
                            </td>

                            <td class="text-nowrap">
                                {{ $correctionRequest->created_at?->format(
                                    'M d, Y h:i A'
                                ) ?? 'N/A' }}
                            </td>

                            <td class="text-end">
                                <button
                                    type="button"
                                    class="btn btn-primary btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#reviewRequestModal{{ $correctionRequest->request_id }}">
                                    <i class="bi bi-eye me-1"></i>
                                    Review Request
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-check2-circle fs-3 d-block mb-2"></i>
                                No pending violation change requests.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer bg-white">
            {{ $requests->links() }}
        </div>
    </div>

    {{-- Review modals are placed outside the table. --}}
@foreach($requests as $correctionRequest)

    {{-- Original review modal --}}
    <div class="modal fade"
         id="reviewRequestModal{{ $correctionRequest->request_id }}">

        {{-- Existing review content and new decision buttons --}}

    </div>
    {{-- Original review modal ends here --}}

    {{-- Remarks modal --}}
    <div class="modal fade"
         id="decisionRemarksModal{{ $correctionRequest->request_id }}">
        {{-- Remarks form --}}
    </div>

    {{-- Confirmation modal --}}
    <div class="modal fade"
         id="decisionConfirmModal{{ $correctionRequest->request_id }}">
        {{-- Confirmation controls --}}
    </div>

    {{-- Hidden Laravel submission form --}}
    <form id="decisionSubmitForm{{ $correctionRequest->request_id }}"
          method="POST"
          class="d-none">
        @csrf
        <input type="hidden" name="review_remarks" value="">
    </form>

@endforeach
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const actionButtons = document.querySelectorAll(
        '[data-decision-action]'
    );

    actionButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const action = button.dataset.decisionAction;
            const reviewModalId = button.dataset.reviewModal;
            const remarksModalId = button.dataset.remarksModal;

            const reviewModalElement =
                document.getElementById(reviewModalId);

            const remarksModalElement =
                document.getElementById(remarksModalId);

            if (!reviewModalElement || !remarksModalElement) {
                return;
            }

            const confirmModalElement = document.getElementById(
                remarksModalId.replace(
                    'decisionRemarksModal',
                    'decisionConfirmModal'
                )
            );

            const submitForm = document.getElementById(
                remarksModalId.replace(
                    'decisionRemarksModal',
                    'decisionSubmitForm'
                )
            );

            if (!confirmModalElement || !submitForm) {
                return;
            }

            const remarksInput = remarksModalElement.querySelector(
                '.decision-remarks-input'
            );

            const remarksError = remarksModalElement.querySelector(
                '.decision-remarks-error'
            );

            const actionLabel = remarksModalElement.querySelector(
                '.decision-action-label'
            );

            const remarksLabel = remarksModalElement.querySelector(
                '.decision-remarks-label'
            );

            const remarksHelp = remarksModalElement.querySelector(
                '.decision-remarks-help'
            );

            const confirmMessage = confirmModalElement.querySelector(
                '.decision-confirm-message'
            );

            const confirmButton = confirmModalElement.querySelector(
                '.decision-confirm-submit'
            );

            // Reset the remarks form for each new decision.
            remarksInput.value = '';
            remarksError.textContent = '';
            remarksError.classList.add('d-none');

            remarksInput.required = action === 'reject';
            remarksInput.minLength = action === 'reject' ? 10 : 0;

            actionLabel.textContent =
                action === 'approve'
                    ? 'Approve Request'
                    : 'Reject Request';

            remarksLabel.textContent =
                action === 'approve'
                    ? 'Approval remarks (optional)'
                    : 'Reason for rejection (required)';

            remarksHelp.textContent =
                action === 'approve'
                    ? 'You may leave this field blank.'
                    : 'Enter at least 10 characters explaining your decision.';

            remarksInput.placeholder =
                action === 'approve'
                    ? 'Optional approval remarks'
                    : 'Explain why this request is rejected';

            confirmMessage.textContent =
                action === 'approve'
                    ? 'Are you sure you want to approve this request?'
                    : 'Are you sure you want to reject this request?';

            confirmButton.textContent =
                action === 'approve'
                    ? 'Confirm Approval'
                    : 'Confirm Rejection';

            confirmButton.classList.toggle(
                'btn-success',
                action === 'approve'
            );

            confirmButton.classList.toggle(
                'btn-danger',
                action === 'reject'
            );

            // Save the selected action and destination.
            remarksModalElement.dataset.selectedAction = action;

            submitForm.action =
                action === 'approve'
                    ? remarksModalElement.dataset.approveUrl
                    : remarksModalElement.dataset.rejectUrl;

            // Close the review modal before opening the remarks modal.
            const reviewModal =
                bootstrap.Modal.getOrCreateInstance(reviewModalElement);

            reviewModalElement.addEventListener(
                'hidden.bs.modal',
                function () {
                    bootstrap.Modal.getOrCreateInstance(
                        remarksModalElement
                    ).show();
                },
                { once: true }
            );

            reviewModal.hide();

            // Validate remarks before showing final confirmation.
            const remarksForm = remarksModalElement.querySelector(
                '.decision-remarks-form'
            );

            remarksForm.onsubmit = function (event) {
                event.preventDefault();

                const remarks = remarksInput.value.trim();

                if (
                    remarksModalElement.dataset.selectedAction === 'reject'
                    && remarks.length < 10
                ) {
                    remarksError.textContent =
                        'The rejection reason must contain at least 10 characters.';

                    remarksError.classList.remove('d-none');
                    remarksInput.focus();

                    return;
                }

                remarksError.textContent = '';
                remarksError.classList.add('d-none');

                submitForm.querySelector(
                    '[name="review_remarks"]'
                ).value = remarks;

                // Close remarks modal before opening confirmation.
                remarksModalElement.addEventListener(
                    'hidden.bs.modal',
                    function () {
                        bootstrap.Modal.getOrCreateInstance(
                            confirmModalElement
                        ).show();
                    },
                    { once: true }
                );

                bootstrap.Modal.getOrCreateInstance(
                    remarksModalElement
                ).hide();
            };

            // Submit to Laravel only after final confirmation.
            confirmButton.onclick = function () {
                if (confirmButton.disabled) {
                    return;
                }

                confirmButton.disabled = true;
                confirmButton.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-1" ' +
                    'role="status" aria-hidden="true"></span> Processing...';

                submitForm.submit();
            };

            // Restore the button if the admin cancels confirmation.
            confirmModalElement.addEventListener(
                'hidden.bs.modal',
                function () {
                    confirmButton.disabled = false;
                    confirmButton.textContent =
                        action === 'approve'
                            ? 'Confirm Approval'
                            : 'Confirm Rejection';
                },
                { once: true }
            );
        });
    });
});
</script>

@endsection