@extends('layouts.admin')

@section('title', 'Violation Change Requests')

@section('content')

<div class="container-fluid">

    <div class="page-header-modern mb-3">

        <div>
            <h3 class="mb-1">
                Violation Change Requests
            </h3>

            <p class="mb-0">
                Review student requests to correct or delete violation records.
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
                Pending Violation Change Requests
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
                                <td style="min-width: 250px;">

                                    <div class="d-flex flex-column gap-2">

                                        {{-- APPROVE --}}
                                    <form
    method="POST"
    action="{{ route(
        'violation-correction-requests.approve',
        [
            'correctionRequest' =>
                $correctionRequest->request_id
        ]
    ) }}"
    data-confirm="{{ $correctionRequest->isDeletionRequest() ? 'Approve deletion? The violation will be removed from active records and the decision preserved in the audit log.' : 'Approve this correction request and update the violation record?' }}"
    data-confirm-title="{{ $correctionRequest->isDeletionRequest() ? 'Approve Deletion Request?' : 'Approve Correction Request?' }}"
    data-confirm-confirm-label="{{ $correctionRequest->isDeletionRequest() ? 'Approve Deletion' : 'Approve & Correct' }}"
>

                                            @csrf

                                            <textarea
                                                name="review_remarks"
                                                class="form-control form-control-sm mb-2"
                                                rows="2"
                                                maxlength="2000"
                                                placeholder="Optional approval remarks"
                                            ></textarea>

<button
    type="submit"
    class="btn btn-sm btn-success w-100"
>

                                                <i class="bi bi-check-circle me-1"></i>

                                                {{ $correctionRequest->isDeletionRequest() ? 'Approve Deletion' : 'Approve & Correct' }}

                                            </button>

                                        </form>

                                        {{-- REJECT --}}
<form
    method="POST"
    action="{{ route(
        'violation-correction-requests.reject',
        [
            'correctionRequest' =>
                $correctionRequest->request_id
        ]
    ) }}"
    data-confirm="{{ $correctionRequest->isDeletionRequest() ? 'Reject this deletion request? The violation record will remain unchanged.' : 'Reject this correction request? The violation record will not be changed.' }}"
    data-confirm-title="{{ $correctionRequest->isDeletionRequest() ? 'Reject Deletion Request?' : 'Reject Correction Request?' }}"
    data-confirm-confirm-label="{{ $correctionRequest->isDeletionRequest() ? 'Reject Deletion Request' : 'Reject Request' }}"
>

                                            @csrf

                                            <textarea
                                                name="review_remarks"
                                                class="form-control form-control-sm mb-2"
                                                rows="2"
                                                minlength="10"
                                                maxlength="2000"
                                                required
                                                placeholder="Reason for rejection (required)"
                                            ></textarea>

<button
    type="submit"
    class="btn btn-sm btn-outline-danger w-100"
>

                                                <i class="bi bi-x-circle me-1"></i>

                                                {{ $correctionRequest->isDeletionRequest() ? 'Reject Deletion Request' : 'Reject Request' }}

                                            </button>

                                        </form>

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