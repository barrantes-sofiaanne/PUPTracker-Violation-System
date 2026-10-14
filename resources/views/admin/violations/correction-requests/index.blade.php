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
        @php
            $violation = $correctionRequest->violation;
            $student = $violation?->student;

            $currentType = $violation?->violation_type_display
                ?? 'Unknown Violation';

            $currentCategory =
                $violation?->violationType?->violationCategory?->category_name
                ?? 'Not recorded';

            $requestedType =
                $correctionRequest->requestedViolationType?->violation_type
                ?? $currentType;

            $requestedCategory =
                $correctionRequest->requestedViolationType
                    ?->violationCategory?->category_name
                ?? 'Not specified';

            $evidenceFiles = $correctionRequest->isDeletionRequest()
                ? ($correctionRequest->deletion_evidence ?? [])
                : [];
        @endphp

        <div
            class="modal fade"
            id="reviewRequestModal{{ $correctionRequest->request_id }}"
            tabindex="-1"
            aria-labelledby="reviewRequestTitle{{ $correctionRequest->request_id }}"
            aria-hidden="true">

            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
                <div class="modal-content">

                    <div class="modal-header">
                        <div>
                            <h5
                                class="modal-title fw-bold"
                                id="reviewRequestTitle{{ $correctionRequest->request_id }}">

                                {{
                                    $correctionRequest->isDeletionRequest()
                                        ? 'Review Deletion Request'
                                        : 'Review Correction Request'
                                }}
                            </h5>

                            <div class="small text-muted">
                                Request #{{ $correctionRequest->request_id }}
                                · Violation #{{ $correctionRequest->violation_id }}
                            </div>
                        </div>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Close">
                        </button>
                    </div>

                    <div class="modal-body">

                        {{-- Student and request information --}}
                        <div class="row g-3 mb-3">

                            <div class="col-12 col-md-4">
                                <div class="review-modal-section">
                                    <div class="review-modal-label">Student</div>

                                    <div class="fw-semibold">
                                        {{ trim(
                                            ($student?->first_name ?? '') . ' ' .
                                            ($student?->last_name ?? '')
                                        ) ?: 'Student' }}
                                    </div>

                                    <div class="small text-muted">
                                        {{ $correctionRequest->student_number }}
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-md-4">
                                <div class="review-modal-section">
                                    <div class="review-modal-label">
                                        Request submitted
                                    </div>

                                    <div class="fw-semibold">
                                        {{ $correctionRequest->created_at?->format(
                                            'M d, Y h:i A'
                                        ) ?? 'N/A' }}
                                    </div>

                                    <div class="mt-2">
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
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-md-4">
                                <div class="review-modal-section">
                                    <div class="review-modal-label">
                                        Student's reason
                                    </div>

                                    <div style="white-space:pre-wrap;overflow-wrap:anywhere">
                                        {{ $correctionRequest->reason ?: 'No reason provided.' }}
                                    </div>
                                </div>
                            </div>

                        </div>

                        {{-- Correction request comparison --}}
                        @if(!$correctionRequest->isDeletionRequest())

                            <h6 class="fw-bold mb-2">
                                Compare violation details
                            </h6>

                            <div class="row g-3 mb-3">

                                <div class="col-12 col-md-6">
                                    <div class="review-modal-section bg-light">
                                        <div class="review-modal-label">
                                            Current record
                                        </div>

                                        <div class="mb-2">
                                            <span class="small text-muted">
                                                Category
                                            </span>
                                            <div class="fw-semibold">
                                                {{ $currentCategory }}
                                            </div>
                                        </div>

                                        <div class="mb-2">
                                            <span class="small text-muted">
                                                Violation type
                                            </span>
                                            <div class="fw-semibold">
                                                {{ $currentType }}
                                            </div>
                                        </div>

                                        <div class="mb-2">
                                            <span class="small text-muted">
                                                Date and time
                                            </span>
                                            <div class="fw-semibold">
                                                {{ $violation?->violation_date
                                                    ? \Carbon\Carbon::parse(
                                                        $violation->violation_date
                                                    )->format('M d, Y h:i A')
                                                    : 'N/A' }}
                                            </div>
                                        </div>

                                        <div>
                                            <span class="small text-muted">
                                                Description / remarks
                                            </span>

                                            <div style="white-space:pre-wrap;overflow-wrap:anywhere">
                                                {{ $violation?->description ?: 'No remarks' }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <div class="review-modal-section">
                                        <div class="review-modal-label">
                                            Student's requested correction
                                        </div>

                                        <div class="mb-2">
                                            <span class="small text-muted">
                                                Requested category
                                            </span>
                                            <div class="fw-semibold">
                                                {{ $requestedCategory }}
                                            </div>
                                        </div>

                                        <div class="mb-2">
                                            <span class="small text-muted">
                                                Requested violation type
                                            </span>
                                            <div class="fw-semibold">
                                                {{ $requestedType }}
                                            </div>
                                        </div>

                                        <div class="mb-2">
                                            <span class="small text-muted">
                                                Requested date and time
                                            </span>
                                            <div class="fw-semibold">
                                                {{ $correctionRequest->requested_violation_date?->format(
                                                    'M d, Y h:i A'
                                                ) ?? 'N/A' }}
                                            </div>
                                        </div>

                                        <div>
                                            <span class="small text-muted">
                                                Requested description / remarks
                                            </span>

                                            <div style="white-space:pre-wrap;overflow-wrap:anywhere">
                                                {{ $correctionRequest->requested_description ?: 'No remarks provided' }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>

                        @else

                            {{-- Deletion request review --}}
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                The student is requesting removal of this violation
                                from active records. Review the reason and evidence
                                before deciding.
                            </div>

                            <h6 class="fw-bold mb-2">
                                Violation record requested for deletion
                            </h6>

                            <div class="review-modal-section mb-3">
                                <div class="row g-3">

                                    <div class="col-12 col-md-4">
                                        <div class="review-modal-label">
                                            Category
                                        </div>
                                        <div>{{ $currentCategory }}</div>
                                    </div>

                                    <div class="col-12 col-md-4">
                                        <div class="review-modal-label">
                                            Violation type
                                        </div>
                                        <div>{{ $currentType }}</div>
                                    </div>

                                    <div class="col-12 col-md-4">
                                        <div class="review-modal-label">
                                            Date and time
                                        </div>
                                        <div>
                                            {{ $violation?->violation_date
                                                ? \Carbon\Carbon::parse(
                                                    $violation->violation_date
                                                )->format('M d, Y h:i A')
                                                : 'N/A' }}
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="review-modal-label">
                                            Description / remarks
                                        </div>

                                        <div style="white-space:pre-wrap;overflow-wrap:anywhere">
                                            {{ $violation?->description ?: 'No remarks' }}
                                        </div>
                                    </div>

                                </div>
                            </div>

                            <h6 class="fw-bold mb-2">
                                Student's supporting evidence
                            </h6>

                            @if(count($evidenceFiles))

                                <div class="row g-3 mb-3">

                                    @foreach($evidenceFiles as $fileIndex => $file)
                                        @php
                                            $evidenceUrl = route(
                                                'violation-change-requests.evidence',
                                                [
                                                    'correctionRequest' => $correctionRequest->request_id,
                                                    'fileIndex' => $fileIndex
                                                ]
                                            );

                                            $evidenceName =
                                                $file['name'] ?? 'Evidence file';

                                            $evidenceMime =
                                                strtolower($file['mime'] ?? '');

                                            $evidenceExtension =
                                                strtolower(
                                                    pathinfo(
                                                        $evidenceName,
                                                        PATHINFO_EXTENSION
                                                    )
                                                );

                                            $isImageEvidence =
                                                str_starts_with(
                                                    $evidenceMime,
                                                    'image/'
                                                ) ||
                                                in_array(
                                                    $evidenceExtension,
                                                    ['jpg', 'jpeg', 'png', 'webp', 'gif'],
                                                    true
                                                );

                                            $isPdfEvidence =
                                                $evidenceMime === 'application/pdf' ||
                                                $evidenceExtension === 'pdf';
                                        @endphp

                                        <div class="col-12 {{
                                            $isImageEvidence || $isPdfEvidence
                                                ? 'col-lg-6'
                                                : ''
                                        }}">
                                            <div class="review-modal-section">

                                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                                    <div class="fw-semibold text-break">
                                                        <i class="bi {{
                                                            $isPdfEvidence
                                                                ? 'bi-file-earmark-pdf text-danger'
                                                                : ($isImageEvidence
                                                                    ? 'bi-file-earmark-image text-primary'
                                                                    : 'bi-paperclip')
                                                        }} me-1"></i>

                                                        {{ $evidenceName }}
                                                    </div>

                                                    <a
                                                        class="btn btn-sm btn-outline-secondary flex-shrink-0"
                                                        href="{{ $evidenceUrl }}"
                                                        target="_blank"
                                                        rel="noopener">
                                                        <i class="bi bi-box-arrow-up-right me-1"></i>
                                                        Open
                                                    </a>
                                                </div>

                                                @if($isImageEvidence)

                                                    <a
                                                        href="{{ $evidenceUrl }}"
                                                        target="_blank"
                                                        rel="noopener">
                                                        <img
                                                            class="review-evidence-image"
                                                            src="{{ $evidenceUrl }}"
                                                            alt="Student-submitted evidence"
                                                            loading="lazy">
                                                    </a>

                                                @elseif($isPdfEvidence)

                                                    <iframe
                                                        class="review-evidence-pdf"
                                                        src="{{ $evidenceUrl }}#toolbar=1&navpanes=0"
                                                        title="PDF evidence: {{ $evidenceName }}">
                                                    </iframe>

                                                @else

                                                    <div class="small text-muted">
                                                        Preview is not available
                                                        for this file type. Use Open
                                                        to view or download it.
                                                    </div>

                                                @endif

                                            </div>
                                        </div>
                                    @endforeach

                                </div>

                            @else

                                <div class="alert alert-light border">
                                    No supporting evidence was attached to
                                    this deletion request.
                                </div>

                            @endif
                        @endif

                        <hr class="my-4">

                        <h6 class="fw-bold mb-3">
                            Administrator decision
                        </h6>

                        <div class="row g-3">

                            {{-- Approve --}}
                            <div class="col-12 col-lg-6">
                                <div class="review-action-card approve">

                                    <h6 class="text-success fw-bold">
                                        <i class="bi bi-check-circle-fill me-1"></i>
                                        Approve Request
                                    </h6>

                                    <p class="small text-muted">
                                        {{
                                            $correctionRequest->isDeletionRequest()
                                                ? 'Approving removes this violation from active records and preserves the decision in the audit log.'
                                                : 'Approving applies the requested correction to the violation record.'
                                        }}
                                    </p>

                                    <form
                                        method="POST"
                                        action="{{ route(
                                            'violation-correction-requests.approve',
                                            ['correctionRequest' => $correctionRequest->request_id]
                                        ) }}">

                                        @csrf

                                        <label
                                            class="form-label small"
                                            for="approveRemarks{{ $correctionRequest->request_id }}">
                                            Approval remarks (optional)
                                        </label>

                                        <textarea
                                            id="approveRemarks{{ $correctionRequest->request_id }}"
                                            name="review_remarks"
                                            class="form-control mb-3"
                                            rows="3"
                                            maxlength="2000"
                                            placeholder="Optional approval remarks"></textarea>

                                        <button
                                            type="submit"
                                            class="btn btn-success w-100">
                                            <i class="bi bi-check-circle me-1"></i>
                                            {{
                                                $correctionRequest->isDeletionRequest()
                                                    ? 'Approve Deletion'
                                                    : 'Approve & Correct'
                                            }}
                                        </button>
                                    </form>

                                </div>
                            </div>

                            {{-- Reject --}}
                            <div class="col-12 col-lg-6">
                                <div class="review-action-card reject">

                                    <h6 class="text-danger fw-bold">
                                        <i class="bi bi-x-circle-fill me-1"></i>
                                        Reject Request
                                    </h6>

                                    <p class="small text-muted">
                                        A rejection reason is required.
                                        The violation record will remain unchanged.
                                    </p>

                                    <form
                                        method="POST"
                                        action="{{ route(
                                            'violation-correction-requests.reject',
                                            ['correctionRequest' => $correctionRequest->request_id]
                                        ) }}">

                                        @csrf

                                        <label
                                            class="form-label small"
                                            for="rejectRemarks{{ $correctionRequest->request_id }}">
                                            Reason for rejection
                                            <span class="text-danger">(required)</span>
                                        </label>

                                        <textarea
                                            id="rejectRemarks{{ $correctionRequest->request_id }}"
                                            name="review_remarks"
                                            class="form-control mb-3"
                                            rows="3"
                                            minlength="10"
                                            maxlength="2000"
                                            required
                                            placeholder="Explain why this request is rejected (minimum 10 characters)"></textarea>

                                        <div class="form-text mb-3">
                                            Enter at least 10 characters.
                                        </div>

                                        <button
                                            type="submit"
                                            class="btn btn-outline-danger w-100">
                                            <i class="bi bi-x-circle me-1"></i>
                                            {{
                                                $correctionRequest->isDeletionRequest()
                                                    ? 'Reject Deletion'
                                                    : 'Reject Request'
                                            }}
                                        </button>
                                    </form>

                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection