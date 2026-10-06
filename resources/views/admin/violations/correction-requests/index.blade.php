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
                                                onclick="return confirm(
                                                    'Approve this correction request and update the violation record?'
                                                );"
                                            >

                                                <i class="bi bi-check-circle me-1"></i>

                                                Approve & Correct

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
                                                onclick="return confirm(
                                                    'Reject this correction request?'
                                                );"
                                            >

                                                <i class="bi bi-x-circle me-1"></i>

                                                Reject Request

                                            </button>

                                        </form>

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

        </div>

        <div class="card-footer bg-white">

            {{ $requests->links() }}

        </div>

    </div>

</div>

@endsection