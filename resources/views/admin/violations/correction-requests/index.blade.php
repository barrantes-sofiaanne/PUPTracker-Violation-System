@extends('layouts.admin')

@section('title', 'Violation Correction Requests')

@section('content')

<div class="container-fluid">

    <div class="page-header-modern mb-3">

        <div>
            <h3 class="mb-1">
                Violation Correction Requests
            </h3>

            <p class="mb-0">
                Review student requests to correct factual information
                in violation records.
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
                Pending Correction Requests
            </h5>

        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0" style="min-width: 1200px;">

                    <thead>

                        <tr>

                            <th>
                                Student
                            </th>

                            <th>
                                Violation Category
                            </th>

                            <th>
                                Current Record
                            </th>

                            <th>
                                Evidence
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

                                {{-- Violation Category --}}
                                <td style="min-width: 190px;">

                                    <span class="badge bg-light text-dark">

                                        {{ $violation?->violation_category_display ?: 'N/A' }}

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

                                {{-- Evidence --}}
                                <td style="min-width: 180px;">

                                    @php
                                        $evidenceFiles = $violation?->evidence ?? collect();
                                    @endphp

                                    @if($evidenceFiles->isEmpty())

                                        <span class="text-muted small">
                                            No evidence attached
                                        </span>

                                    @else

                                        <div class="d-flex flex-column gap-1">

                                            @foreach($evidenceFiles as $evidence)

                                                @if(!empty($evidence->access_key))

                                                    <a
                                                        href="{{ route('admin.violations.evidence', ['evidence' => $evidence->access_key]) }}"
                                                        target="_blank"
                                                        rel="noopener"
                                                        class="btn btn-sm btn-outline-secondary text-start"
                                                    >
                                                        <i class="bi bi-paperclip me-1"></i>
                                                        {{ $evidenceFiles->count() > 1 ? 'Evidence ' . $loop->iteration : 'View Evidence' }}
                                                    </a>

                                                @else

                                                    <span class="text-muted small">
                                                        Evidence unavailable
                                                    </span>

                                                @endif

                                            @endforeach

                                        </div>

                                    @endif

                                </td>

                                {{-- Requested --}}
                                <td style="min-width: 230px;">

                                    <div class="fw-semibold">

                                        {{ $requestedType }}

                                    </div>

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

                                    @if($hasAssignedSanction)

                                        <div class="mt-2">

                                            <span class="badge bg-warning text-dark">

                                                Sanction Assigned

                                            </span>

                                        </div>

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
                                <td style="min-width: 190px;">
                                    <div class="d-flex flex-column gap-2">
                                        <button type="button"
                                            class="admin-btn admin-btn-success admin-btn-sm w-100"
                                            data-bs-toggle="modal"
                                            data-bs-target="#approveCorrectionModal{{ $correctionRequest->request_id }}">
                                            <i class="bi bi-check-circle me-1"></i>
                                            Approve &amp; Correct
                                        </button>
                                        <button type="button"
                                            class="admin-btn admin-btn-danger admin-btn-sm w-100"
                                            data-bs-toggle="modal"
                                            data-bs-target="#rejectCorrectionModal{{ $correctionRequest->request_id }}">
                                            <i class="bi bi-x-circle me-1"></i>
                                            Reject Request
                                        </button>
                                    </div>
                                </td>
                            </tr>


                            {{-- Approve Correction Modal --}}
                            <div class="modal fade admin-modal" id="approveCorrectionModal{{ $correctionRequest->request_id }}" tabindex="-1" aria-labelledby="approveCorrectionModalLabel{{ $correctionRequest->request_id }}" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content admin-modal-content">
                                        <div class="modal-header admin-modal-header">
                                            <h5 class="modal-title admin-modal-title" id="approveCorrectionModalLabel{{ $correctionRequest->request_id }}">
                                                Approve Correction Request
                                            </h5>
                                            <button type="button" class="btn-close admin-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>

                                        <form method="POST" action="{{ route('violation-correction-requests.approve', ['correctionRequest' => $correctionRequest->request_id]) }}">
                                            @csrf

                                            <div class="modal-body admin-modal-body">
                                                <div class="row g-3 mb-3">
                                                    <div class="col-md-8">
                                                        <span class="admin-filter-label d-block">Student</span>
                                                        <strong>
                                                            {{ trim(($student?->first_name ?? '') . ' ' . ($student?->last_name ?? '')) ?: 'Student' }}
                                                        </strong>
                                                        <div class="text-muted small">{{ $correctionRequest->student_number }}</div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <span class="admin-filter-label d-block">Violation Category</span>
                                                        <strong>{{ $violation?->violation_category_display ?: 'N/A' }}</strong>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <span class="admin-filter-label d-block">Student's Reason</span>
                                                    <div class="border rounded p-3 bg-light" style="white-space: pre-line;">{{ $correctionRequest->reason ?: 'No reason provided.' }}</div>
                                                </div>

                                                <div class="row g-3 mb-3">
                                                    <div class="col-md-6">
                                                        <span class="admin-filter-label d-block">Current Violation</span>
                                                        <strong>{{ $currentType }}</strong>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <span class="admin-filter-label d-block">Requested Correction</span>
                                                        <strong>{{ $requestedType }}</strong>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">Evidence Provided</label>
                                                    <div class="border rounded p-3 bg-light d-flex flex-column gap-2">
                                                        @if($evidenceFiles->isEmpty())
                                                            <span class="text-muted small">No evidence attached to this violation.</span>
                                                        @else
                                                            @foreach($evidenceFiles as $evidence)
                                                                @if(!empty($evidence->access_key))
                                                                    <a
                                                                        href="{{ route('admin.violations.evidence', ['evidence' => $evidence->access_key]) }}"
                                                                        target="_blank"
                                                                        rel="noopener"
                                                                        class="btn btn-sm btn-outline-secondary text-start"
                                                                    >
                                                                        <i class="bi bi-paperclip me-1"></i>
                                                                        {{ $evidence->original_name ?: 'View Evidence' }}
                                                                    </a>
                                                                @else
                                                                    <span class="text-muted small">Evidence unavailable</span>
                                                                @endif
                                                            @endforeach
                                                        @endif
                                                    </div>
                                                </div>

                                                <div class="mb-0">
                                                    <label for="approveRemarks{{ $correctionRequest->request_id }}" class="form-label fw-semibold">
                                                        Approval Remarks <span class="text-muted fw-normal">(optional)</span>
                                                    </label>
                                                    <textarea
                                                        id="approveRemarks{{ $correctionRequest->request_id }}"
                                                        name="review_remarks"
                                                        class="form-control"
                                                        rows="4"
                                                        maxlength="2000"
                                                        placeholder="Enter any remarks about the approved correction..."
                                                    ></textarea>
                                                    <div class="form-text">These remarks will be recorded with the correction decision.</div>
                                                </div>
                                            </div>

                                            <div class="modal-footer admin-modal-footer">
                                                <button type="button" class="admin-btn admin-btn-neutral" data-bs-dismiss="modal">
                                                    Cancel
                                                </button>
                                                <button type="submit" class="admin-btn admin-btn-success">
                                                    <i class="bi bi-check-circle me-1"></i>
                                                    Approve & Correct
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            {{-- Reject Correction Modal --}}
                            <div class="modal fade admin-modal" id="rejectCorrectionModal{{ $correctionRequest->request_id }}" tabindex="-1" aria-labelledby="rejectCorrectionModalLabel{{ $correctionRequest->request_id }}" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content admin-modal-content">
                                        <div class="modal-header admin-modal-header">
                                            <h5 class="modal-title admin-modal-title" id="rejectCorrectionModalLabel{{ $correctionRequest->request_id }}">
                                                Reject Correction Request
                                            </h5>
                                            <button type="button" class="btn-close admin-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>

                                        <form method="POST" action="{{ route('violation-correction-requests.reject', ['correctionRequest' => $correctionRequest->request_id]) }}">
                                            @csrf

                                            <div class="modal-body admin-modal-body">
                                                <div class="row g-3 mb-3">
                                                    <div class="col-md-8">
                                                        <span class="admin-filter-label d-block">Student</span>
                                                        <strong>
                                                            {{ trim(($student?->first_name ?? '') . ' ' . ($student?->last_name ?? '')) ?: 'Student' }}
                                                        </strong>
                                                        <div class="text-muted small">{{ $correctionRequest->student_number }}</div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <span class="admin-filter-label d-block">Violation Category</span>
                                                        <strong>{{ $violation?->violation_category_display ?: 'N/A' }}</strong>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <span class="admin-filter-label d-block">Student's Reason</span>
                                                    <div class="border rounded p-3 bg-light" style="white-space: pre-line;">{{ $correctionRequest->reason ?: 'No reason provided.' }}</div>
                                                </div>

                                                <div class="mb-3">
                                                    <span class="admin-filter-label d-block">Requested Correction</span>
                                                    <div class="border rounded p-3 bg-light">{{ $requestedType }}</div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">Evidence Provided</label>
                                                    <div class="border rounded p-3 bg-light d-flex flex-column gap-2">
                                                        @if($evidenceFiles->isEmpty())
                                                            <span class="text-muted small">No evidence attached to this violation.</span>
                                                        @else
                                                            @foreach($evidenceFiles as $evidence)
                                                                @if(!empty($evidence->access_key))
                                                                    <a
                                                                        href="{{ route('admin.violations.evidence', ['evidence' => $evidence->access_key]) }}"
                                                                        target="_blank"
                                                                        rel="noopener"
                                                                        class="btn btn-sm btn-outline-secondary text-start"
                                                                    >
                                                                        <i class="bi bi-paperclip me-1"></i>
                                                                        {{ $evidence->original_name ?: 'View Evidence' }}
                                                                    </a>
                                                                @else
                                                                    <span class="text-muted small">Evidence unavailable</span>
                                                                @endif
                                                            @endforeach
                                                        @endif
                                                    </div>
                                                </div>

                                                <div class="mb-0">
                                                    <label for="rejectReason{{ $correctionRequest->request_id }}" class="form-label fw-semibold">
                                                        Reason for Rejection <span class="text-danger">*</span>
                                                    </label>
                                                    <textarea
                                                        id="rejectReason{{ $correctionRequest->request_id }}"
                                                        name="review_remarks"
                                                        class="form-control"
                                                        rows="4"
                                                        minlength="10"
                                                        maxlength="2000"
                                                        required
                                                        placeholder="Explain why this correction request is being rejected..."
                                                    ></textarea>
                                                    <div class="form-text">Please provide at least 10 characters so the student understands the decision.</div>
                                                </div>
                                            </div>

                                            <div class="modal-footer admin-modal-footer">
                                                <button type="button" class="admin-btn admin-btn-neutral" data-bs-dismiss="modal">
                                                    Cancel
                                                </button>
                                                <button type="submit" class="admin-btn admin-btn-danger">
                                                    <i class="bi bi-x-circle me-1"></i>
                                                    Reject Request
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                        @empty

                            <tr>

                                <td
                                    colspan="8"
                                    class="text-center py-5 text-muted"
                                >

                                    <i
                                        class="bi bi-check2-circle fs-3 d-block mb-2"
                                    ></i>

                                    No pending violation correction requests.

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