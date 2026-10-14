@extends('layouts.admin')

@section('title', 'Violation Correction & Deletion Requests')

@section('content')

<style>
/* Correction and deletion request actions */
.correction-review-actions {
    min-width: 430px;
    padding: 0.75rem;
}

.correction-review-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.65rem;
    align-items: stretch;
}

.correction-review-panel {
    display: flex;
    flex-direction: column;
    min-width: 0;
    padding: 0.75rem;
    border: 1px solid #e7e7eb;
    border-radius: 0.85rem;
    background: #fff;
}

.correction-review-panel.approval {
    border-top: 3px solid #198754;
    background: #f8fcf9;
}

.correction-review-panel.rejection {
    border-top: 3px solid #dc3545;
    background: #fffafa;
}

.correction-review-heading {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-bottom: 0.65rem;
    font-size: 0.85rem;
    font-weight: 750;
}

.correction-review-panel.approval .correction-review-heading {
    color: #157347;
}

.correction-review-panel.rejection .correction-review-heading {
    color: #b02a37;
}

.correction-review-panel textarea {
    width: 100%;
    min-height: 76px;
    flex: 1 1 auto;
    resize: vertical;
    border-radius: 0.65rem;
    border-color: #dedee4;
    background: #fff;
    font-size: 0.82rem;
    line-height: 1.45;
}

.correction-review-panel textarea:focus {
    border-color: #9b7777;
    box-shadow: 0 0 0 0.15rem rgba(128, 9, 9, 0.09);
}

.correction-review-panel .btn {
    width: 100%;
    min-height: 40px;
    margin-top: 0.55rem;
    padding: 0.55rem 0.4rem;
    border-radius: 0.65rem;
    font-size: 0.82rem;
    font-weight: 700;
    white-space: normal;
}

.correction-review-hint {
    margin-top: 0.4rem;
    color: #777;
    font-size: 0.72rem;
    line-height: 1.4;
}

@media (max-width: 575.98px) {
    .correction-review-actions {
        min-width: 320px;
    }

    .correction-review-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .correction-review-panel textarea {
        min-height: 65px;
    }
}

</style>

<div class="container-fluid">

    <div class="page-header-modern mb-3">

        <div>
            <h3 class="mb-1">
                Violation Correction &amp; Deletion Requests
            </h3>

            <p class="mb-0">
                Review student requests to correct factual details or request deletion of a violation record.
            </p>
        </div>

    </div>

    @if(session('success'))

        <div class="alert alert-success">
            <i class="bi bi-check-circle me-1"></i>
            {{ session('success') }}
        </div>

    @endif

    @if($errors->has('correction_request'))

        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-1"></i>
            {{ $errors->first('correction_request') }}
        </div>

    @endif

    <div class="card shadow-sm border-0">

        <div class="card-header bg-white">

            <h5 class="mb-0">
                Pending Correction &amp; Deletion Requests
            </h5>

        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead>

                        <tr>

                            <th>
                                Student
                            </th>

                            <th>Request Type</th>
                            <th>Violation</th>

                            <th>
                                Current Record
                            </th>

                            <th>
                                Requested Correction
                            </th>

                            <th>
                                Student Reason
                            </th>

                            <th>
                                Submitted
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse($requests as $correctionRequest)

                            @php

                                $violation =
                                    $correctionRequest->violation;

                                $student =
                                    $violation?->student;

                                $currentType =
                                    $violation?->violation_type_display
                                    ?? 'Unknown Violation';

                                $requestedType =
                                    $correctionRequest
                                        ->requestedViolationType
                                        ?->violation_type
                                    ?? $currentType;

                                $hasAssignedSanction =
                                    \App\Models\StudentSanctionRecord::where(
                                        'violation_id',
                                        $correctionRequest->violation_id
                                    )->exists();

                            @endphp

                            <tr>

                                {{-- Student --}}
                                <td>

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

                                {{-- Request type --}}
                                <td><span class="badge {{ $correctionRequest->isDeletionRequest() ? 'bg-danger' : 'bg-primary' }}">{{ $correctionRequest->isDeletionRequest() ? 'Deletion' : 'Correction' }}</span></td>

                                {{-- Violation --}}
                                <td>

                                    <span class="badge bg-light text-dark">

                                        #{{ $correctionRequest->violation_id }}

                                    </span>

                                </td>

                                {{-- Current --}}
                                <td style="min-width: 230px;">

                                    <div class="fw-semibold">

                                        {{ $currentType }}

                                    </div>

                                    <div class="small text-muted">

                                        {{ $violation?->violation_date
                                            ? \Carbon\Carbon::parse(
                                                $violation->violation_date
                                            )->format('M d, Y h:i A')
                                            : 'N/A'
                                        }}

                                    </div>

                                    <div class="small mt-1">

                                        {{ $violation?->description ?: 'No remarks' }}

                                    </div>

                                </td>

                                {{-- Requested action --}}
                                <td style="min-width: 230px;">
                                    @if($correctionRequest->isDeletionRequest())
                                        <div class="fw-semibold text-danger">Delete this violation record</div>
                                        <div class="small text-muted mt-1">Student requests removal of the active record.</div>
                                    @else
                                    <div class="fw-semibold">{{ $requestedType }}</div>

                                    <div class="small text-muted">

                                        {{ $correctionRequest->requested_violation_date
                                            ? $correctionRequest
                                                ->requested_violation_date
                                                ->format('M d, Y h:i A')
                                            : 'N/A'
                                        }}

                                    </div>

                                    <div class="small mt-1">

                                        {{ $correctionRequest
                                            ->requested_description
                                            ?: 'No remarks'
                                        }}

                                    </div>

                                    @if($hasAssignedSanction && !$correctionRequest->isDeletionRequest())

                                        <div class="mt-2">

                                            <span class="badge bg-warning text-dark">

                                                Sanction Assigned

                                            </span>

                                        </div>

                                    @endif
                                    @endif
                                    @if($correctionRequest->isDeletionRequest() && !empty($correctionRequest->deletion_evidence))
                                        <div class="mt-2"><strong class="small">Supporting evidence</strong>
                                        @foreach($correctionRequest->deletion_evidence as $fileIndex => $file)
                                            <a class="d-block small" href="{{ route('violation-change-requests.evidence', ['correctionRequest' => $correctionRequest->request_id, 'fileIndex' => $fileIndex]) }}"><i class="bi bi-paperclip me-1"></i>{{ $file['name'] ?? 'Evidence file' }}</a>
                                        @endforeach</div>
                                    @endif
                                </td>

                                {{-- Reason --}}
                                <td style="min-width: 250px;">

                                    {{ $correctionRequest->reason }}

                                </td>

                                {{-- Submitted --}}
                                <td>

                                    {{ $correctionRequest->created_at
                                        ? $correctionRequest
                                            ->created_at
                                            ->format('M d, Y h:i A')
                                        : 'N/A'
                                    }}

                                </td>

                                {{-- Actions --}}
                                
{{-- Actions --}}
<td class="correction-review-actions">
    <div class="correction-review-grid">

        {{-- APPROVAL PANEL --}}
        <div class="correction-review-panel approval">
            <div class="correction-review-heading">
                <i class="bi bi-check-circle-fill"></i>
                <span>Approve Request</span>
            </div>

            <form
                method="POST"
                action="{{ route(
                    'violation-correction-requests.approve',
                    ['correctionRequest' => $correctionRequest->request_id]
                ) }}"
                data-confirm="{{ $correctionRequest->isDeletionRequest()
                    ? 'Approve deletion? The violation will be removed from active records and the decision preserved in the audit log.'
                    : 'Approve this correction request and update the violation record?' }}"
                data-confirm-title="{{ $correctionRequest->isDeletionRequest()
                    ? 'Approve Deletion Request?'
                    : 'Approve Correction Request?' }}"
                data-confirm-confirm-label="{{ $correctionRequest->isDeletionRequest()
                    ? 'Approve Deletion'
                    : 'Approve & Correct' }}"
            >
                @csrf

                <textarea
                    name="review_remarks"
                    class="form-control"
                    rows="3"
                    maxlength="2000"
                    placeholder="Optional approval remarks"
                    aria-label="Optional approval remarks"
                ></textarea>

                <div class="correction-review-hint">
                    Optional — add notes for the review record.
                </div>

                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-circle me-1"></i>
                    {{ $correctionRequest->isDeletionRequest()
                        ? 'Approve Deletion'
                        : 'Approve & Correct' }}
                </button>
            </form>
        </div>

        {{-- REJECTION PANEL --}}
        <div class="correction-review-panel rejection">
            <div class="correction-review-heading">
                <i class="bi bi-x-circle-fill"></i>
                <span>Reject Request</span>
            </div>

            <form
                method="POST"
                action="{{ route(
                    'violation-correction-requests.reject',
                    ['correctionRequest' => $correctionRequest->request_id]
                ) }}"
                data-confirm="{{ $correctionRequest->isDeletionRequest()
                    ? 'Reject this deletion request? The violation record will remain unchanged.'
                    : 'Reject this correction request? The violation record will not be changed.' }}"
                data-confirm-title="{{ $correctionRequest->isDeletionRequest()
                    ? 'Reject Deletion Request?'
                    : 'Reject Correction Request?' }}"
                data-confirm-confirm-label="{{ $correctionRequest->isDeletionRequest()
                    ? 'Reject Deletion Request'
                    : 'Reject Request' }}"
            >
                @csrf

                <textarea
                    name="review_remarks"
                    class="form-control"
                    rows="3"
                    minlength="10"
                    maxlength="2000"
                    required
                    placeholder="Explain why this request is rejected (minimum 10 characters)"
                    aria-label="Required reason for rejection"
                ></textarea>

                <div class="correction-review-hint">
                    Required — provide at least 10 characters.
                </div>

                <button type="submit" class="btn btn-outline-danger">
                    <i class="bi bi-x-circle me-1"></i>
                    {{ $correctionRequest->isDeletionRequest()
                        ? 'Reject Deletion'
                        : 'Reject Request' }}
                </button>
            </form>
        </div>

    </div>
</td>


                            </tr>

                        @empty

                            <tr>

                                <td
                                    colspan="8"
                                    class="text-center py-5 text-muted"
                                >

                                    <i
                                        class="bi bi-check2-circle fs-3 d-block mb-2"
                                    ></i>

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

</div>

@endsection