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

                <table class="table table-hover align-middle mb-0">

                    <thead>

                        <tr>

                            <th>
                                Student
                            </th>

                            <th>
                                Violation
                            </th>

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
                                <td style="min-width: 180px;">

                                    <div class="d-flex flex-column gap-2">

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-success w-100"
                                            data-bs-toggle="modal"
                                            data-bs-target="#approveCorrectionModal{{ $correctionRequest->request_id }}"
                                        >
                                            <i class="bi bi-check-circle me-1"></i>
                                            Approve & Correct
                                        </button>

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger w-100"
                                            data-bs-toggle="modal"
                                            data-bs-target="#rejectCorrectionModal{{ $correctionRequest->request_id }}"
                                        >
                                            <i class="bi bi-x-circle me-1"></i>
                                            Reject Request
                                        </button>

                                    </div>

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td
                                    colspan="7"
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

            {{-- Action modals --}}
            @foreach($requests as $correctionRequest)

                @php
                    $violation = $correctionRequest->violation;
                    $student = $violation?->student;
                    $currentType = $violation?->violation_type_display ?? 'Unknown Violation';
                    $requestedType = $correctionRequest->requestedViolationType?->violation_type ?? $currentType;
                @endphp

                {{-- Approve modal --}}
                <div
                    class="modal fade"
                    id="approveCorrectionModal{{ $correctionRequest->request_id }}"
                    tabindex="-1"
                    aria-labelledby="approveCorrectionModalLabel{{ $correctionRequest->request_id }}"
                    aria-hidden="true"
                >
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content border-0 shadow-lg">

                            <form
                                method="POST"
                                action="{{ route('violation-correction-requests.approve', ['correctionRequest' => $correctionRequest->request_id]) }}"
                            >
                                @csrf

                                <div class="modal-header bg-success text-white">
                                    <div>
                                        <h5
                                            class="modal-title mb-1"
                                            id="approveCorrectionModalLabel{{ $correctionRequest->request_id }}"
                                        >
                                            <i class="bi bi-check-circle me-2"></i>
                                            Approve Correction Request
                                        </h5>
                                        <div class="small opacity-75">
                                            Review the request before applying the correction.
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        class="btn-close btn-close-white"
                                        data-bs-dismiss="modal"
                                        aria-label="Close"
                                    ></button>
                                </div>

                                <div class="modal-body">
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <div class="small text-muted mb-1">Student</div>
                                            <div class="fw-semibold">
                                                {{ trim(($student?->first_name ?? '') . ' ' . ($student?->last_name ?? '')) ?: 'Student' }}
                                            </div>
                                            <div class="small text-muted">
                                                {{ $correctionRequest->student_number }}
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="small text-muted mb-1">Violation #</div>
                                            <div class="fw-semibold">
                                                {{ $correctionRequest->violation_id }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="p-3 rounded border bg-light mb-3">
                                        <div class="small text-muted mb-1">Student's reason</div>
                                        <div class="fw-semibold text-dark">
                                            {{ $correctionRequest->reason ?: 'No reason provided.' }}
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <div class="small text-muted mb-1">Current violation</div>
                                            <div class="fw-semibold">{{ $currentType }}</div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="small text-muted mb-1">Requested correction</div>
                                            <div class="fw-semibold">{{ $requestedType }}</div>
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <label
                                            for="approveRemarks{{ $correctionRequest->request_id }}"
                                            class="form-label fw-semibold"
                                        >
                                            Approval remarks
                                            <span class="text-muted fw-normal">(optional)</span>
                                        </label>
                                        <textarea
                                            id="approveRemarks{{ $correctionRequest->request_id }}"
                                            name="review_remarks"
                                            class="form-control"
                                            rows="5"
                                            maxlength="2000"
                                            placeholder="Enter any remarks about the approved correction..."
                                        ></textarea>
                                        <div class="form-text">
                                            These remarks will be recorded with the correction decision.
                                        </div>
                                    </div>
                                </div>

                                <div class="modal-footer">
                                    <button
                                        type="button"
                                        class="btn btn-light border"
                                        data-bs-dismiss="modal"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        class="btn btn-success"
                                    >
                                        <i class="bi bi-check-circle me-1"></i>
                                        Approve & Correct
                                    </button>
                                </div>
                            </form>

                        </div>
                    </div>
                </div>

                {{-- Reject modal --}}
                <div
                    class="modal fade"
                    id="rejectCorrectionModal{{ $correctionRequest->request_id }}"
                    tabindex="-1"
                    aria-labelledby="rejectCorrectionModalLabel{{ $correctionRequest->request_id }}"
                    aria-hidden="true"
                >
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content border-0 shadow-lg">

                            <form
                                method="POST"
                                action="{{ route('violation-correction-requests.reject', ['correctionRequest' => $correctionRequest->request_id]) }}"
                            >
                                @csrf

                                <div class="modal-header bg-danger text-white">
                                    <div>
                                        <h5
                                            class="modal-title mb-1"
                                            id="rejectCorrectionModalLabel{{ $correctionRequest->request_id }}"
                                        >
                                            <i class="bi bi-x-circle me-2"></i>
                                            Reject Correction Request
                                        </h5>
                                        <div class="small opacity-75">
                                            Provide the reason for rejecting this request.
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        class="btn-close btn-close-white"
                                        data-bs-dismiss="modal"
                                        aria-label="Close"
                                    ></button>
                                </div>

                                <div class="modal-body">
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <div class="small text-muted mb-1">Student</div>
                                            <div class="fw-semibold">
                                                {{ trim(($student?->first_name ?? '') . ' ' . ($student?->last_name ?? '')) ?: 'Student' }}
                                            </div>
                                            <div class="small text-muted">
                                                {{ $correctionRequest->student_number }}
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="small text-muted mb-1">Violation #</div>
                                            <div class="fw-semibold">
                                                {{ $correctionRequest->violation_id }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="p-3 rounded border bg-light mb-3">
                                        <div class="small text-muted mb-1">Student's reason</div>
                                        <div class="fw-semibold text-dark">
                                            {{ $correctionRequest->reason ?: 'No reason provided.' }}
                                        </div>
                                    </div>

                                    <div class="p-3 rounded border bg-light mb-3">
                                        <div class="small text-muted mb-1">Requested correction</div>
                                        <div class="fw-semibold text-dark">
                                            {{ $requestedType }}
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <label
                                            for="rejectRemarks{{ $correctionRequest->request_id }}"
                                            class="form-label fw-semibold"
                                        >
                                            Reason for rejection
                                            <span class="text-danger">*</span>
                                        </label>
                                        <textarea
                                            id="rejectRemarks{{ $correctionRequest->request_id }}"
                                            name="review_remarks"
                                            class="form-control"
                                            rows="5"
                                            minlength="10"
                                            maxlength="2000"
                                            required
                                            placeholder="Explain why this correction request is being rejected..."
                                        ></textarea>
                                        <div class="form-text">
                                            Please provide at least 10 characters so the student understands the decision.
                                        </div>
                                    </div>
                                </div>

                                <div class="modal-footer">
                                    <button
                                        type="button"
                                        class="btn btn-light border"
                                        data-bs-dismiss="modal"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        class="btn btn-danger"
                                    >
                                        <i class="bi bi-x-circle me-1"></i>
                                        Reject Request
                                    </button>
                                </div>
                            </form>

                        </div>
                    </div>
                </div>

            @endforeach

        </div>

        <div class="card-footer bg-white">

            {{ $requests->links() }}

        </div>

    </div>

</div>

@endsection