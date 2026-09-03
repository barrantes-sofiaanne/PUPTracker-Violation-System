@extends('layouts.student')

@section('title', 'Student Record')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/student.css') }}"> 
@endpush

@section('content')

<div class="container-fluid py-1">

            {{-- Student Information --}}
            <div class="portal-hero mb-4">
                <h2 class="fw-bold mb-2">Student Record</h2>
                <p class="mb-0">Review violation and sanction history with clear offense tracking.</p>
            </div>
            <div class="card portal-card mb-4">

                <div class="card-body">

                    <h2 class="fw-bold mb-3">

                        Student Record

                    </h2>

                    <div class="row align-items-center">
                        <div class="col-md-7">
                            <h4 class="mb-3 fw-bold">
                                {{ $user->first_name }}
                                {{ $user->middle_name }}
                                {{ $user->last_name }}
                            </h4>
                            <p class="mb-2">
                                <strong>Student Number:</strong>
                                {{ $user->student_number }}
                            </p>
                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <span class="portal-badge maroon px-3 py-2">
                                    Program: {{ optional(optional($user->studentInfo)->program)->program_name ?? 'N/A' }}
                                </span>
                                <span class="portal-badge goldenrod px-3 py-2">
                                    Year: {{ optional(optional($user->studentInfo)->year)->year ?? 'N/A' }}
                                </span>
                                <span class="portal-badge muted px-3 py-2">
                                    Section: {{ optional(optional($user->studentInfo)->section)->section_name ?? 'N/A' }}
                                </span>
                            </div>
                        </div>

                        <div class="col-md-5">
                            <div class="portal-muted-box">
                                <p class="mb-2">
                                    <strong>Violation Summary</strong>
                                </p>
                                <p class="mb-0 portal-subtitle">
                                    Track your disciplinary history and request sanction review when needed.
                                </p>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

            {{-- Tabs --}}
            <ul
                class="nav nav-tabs mb-4"
                id="recordTabs"
                role="tablist">

               <li class="nav-item">
                   <button
                       class="nav-link active"
                       id="violation-tab"
                       data-bs-toggle="tab"
                       data-bs-target="#violationRecord"
                       type="button"
                       role="tab"
                       aria-controls="violationRecord"
                       aria-selected="true">
                       Violation Record
                   </button>
               </li>

               <li class="nav-item">
                   <button
                       class="nav-link"
                       id="sanction-tab"
                       data-bs-toggle="tab"
                       data-bs-target="#sanctionRecord"
                       type="button"
                       role="tab"
                       aria-controls="sanctionRecord"
                       aria-selected="false">
                       Sanction Record
                   </button>
               </li>

            </ul>

            <div class="tab-content">

                {{-- =============================== --}}
                {{-- Violation Record --}}
                {{-- =============================== --}}

              <div
    class="tab-pane fade show active"
    id="violationRecord"
    role="tabpanel"
    aria-labelledby="violation-tab">

                    <div class="card shadow-sm border-0 mb-4">

                        <div class="card-header bg-white">

                            <h5 class="mb-0">

                                Summary by Violation

                            </h5>

                        </div>

                        <div class="card-body p-0">

                                <table class="table table-hover align-middle mb-0 portal-table">

                                <thead>

                                    <tr>

                                        <th>

                                            Category

                                        </th>

                                        <th>

                                            Violation Type

                                        </th>

                                        <th>

                                            Offense Level

                                        </th>

                                        <th>

                                            Status

                                        </th>

                                        <th>

                                            Remarks

                                        </th>

                                        <th>

                                            Disciplinary Sanction

                                        </th>

                                        <th>

                                            Action

                                        </th>

                                    </tr>

                                </thead>

                                <tbody>
                                    @forelse($violationSummary as $summary)

<tr>

    <td>

        {{ $summary['category'] }}

    </td>

    <td>

        {{ $summary['type'] }}

    </td>

    <td>

        {{ $summary['offense_level'] }}

    </td>

    <td>

        @if($summary['violation_status'] === 'Sanction')

            <span class="badge bg-danger">

                Sanction

            </span>

        @else

            <span class="portal-badge warning">

                Warning

            </span>

        @endif

    </td>

    <td>

        {{ $summary['remarks'] }}

    </td>

    <td style="max-width:300px; white-space:normal;">

        {{ $summary['disciplinary_sanction'] }}

    </td>

    <td>

        @if($summary['workflow_status'] === 'Requested')

            <button
                class="btn btn-secondary btn-sm"
                disabled>

                <i class="bi bi-clock-history"></i>

                Requested

            </button>

        @elseif($summary['workflow_status'] === 'Pending')

            <button
                class="btn btn-secondary btn-sm"
                disabled>

                <i class="bi bi-hourglass-split"></i>

                Pending

            </button>

        @elseif($summary['workflow_status'] === 'Approved')

            <button
                class="btn btn-success btn-sm"
                disabled>

                <i class="bi bi-check-circle"></i>

                Approved

            </button>

        @else

            @if($summary['violation_status'] !== 'Sanction')

                <span class="badge bg-secondary-subtle text-secondary-emphasis border">
                    No Request Needed
                </span>

            @else

                <button
                    class="btn portal-btn btn-sm request-sanction-btn"
                    data-violation-type-id="{{ $summary['violation_type_id'] }}">

                    <i class="bi bi-send"></i>

                    Request Sanction

                </button>

            @endif

        @endif

    </td>

</tr>

@empty

<tr>

    <td
        colspan="7"
        class="text-center py-5 text-muted">

        No outstanding violations found.

    </td>

</tr>

@endforelse

</tbody>

</table>

</div>

</div>

{{-- ================================================= --}}
{{-- Individual Violations Log --}}
{{-- ================================================= --}}

<div class="card shadow-sm border-0">

    <div class="card-header bg-white">

        <h5 class="mb-0">

            Individual Violations Log

        </h5>

    </div>

    <div class="card-body p-0">

        <table class="table table-hover align-middle mb-0 portal-table">

            <thead>

                <tr>

                    <th>

                        Violation Type

                    </th>

                    <th>

                        Date

                    </th>

                    <th>

                        Category

                    </th>

                    <th>

                        Remarks

                    </th>

                    <th>

                        Attachments

                    </th>

                </tr>

            </thead>

            <tbody>

                @forelse($violations as $violation)

                    <tr>

                        <td>

                            {{ $violation->violation_type_display }}

                        </td>

                        <td>

                            {{ \Carbon\Carbon::parse($violation->violation_date)->format('M d, Y h:i A') }}

                        </td>

                        <td>

                            {{ $violation->violation_category_display }}

                        </td>

                        <td>

                            {{ $violation->description ?: 'No remarks' }}

                        </td>

                        <td>

                            @php
                                $visibleEvidence = collect($violation->evidence ?? [])
                                    ->filter(fn ($e) => ! empty($e->disk_path));
                            @endphp

                            @if ($visibleEvidence->isNotEmpty())
                                @foreach ($visibleEvidence as $item)
                                    <a
                                        href="{{ route('student.violations.evidence', $item) }}"
                                        target="_blank"
                                        rel="noopener"
                                        class="btn btn-sm btn-outline-secondary me-1 mb-1"
                                        title="Open {{ $item->original_name ?: 'evidence' }}">
                                        <i class="bi bi-paperclip"></i>
                                        {{ Str::limit($item->original_name ?: 'Evidence', 18) }}
                                    </a>
                                @endforeach
                            @else
                                <span class="text-muted">—</span>
                            @endif

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="5"
                            class="text-center py-5">

                            No individual violations found.

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

    <div class="card-footer">

        {{ $violations->links() }}

    </div>

</div>

</div>
{{-- ================================================= --}}
{{-- Sanction Record --}}
{{-- ================================================= --}}

<div
    class="tab-pane fade"
    id="sanctionRecord"
    role="tabpanel"
    aria-labelledby="sanction-tab">

    <div class="card shadow-sm border-0">

        <div class="card-header bg-white">

            <h5 class="mb-0">

                Your Sanction Records

            </h5>

        </div>

        <div class="card-body p-0">

            <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">

                <thead>

                    <tr>

                        <th>

                            Violation Type

                        </th>

                        <th>

                            Status

                        </th>

                        <th>

                            Date Assigned

                        </th>

                        <th>

                            Deadline

                        </th>

                        <th>

                            Sanction Details

                        </th>

                        <th>

                            Proof of Completion

                        </th>

                    </tr>

                </thead>

                <tbody>

                    @forelse($sanctionRecords as $record)

                        <tr>

                            <td>

                                {{ optional(optional($record->violation)->violationType)->violation_type ?? 'N/A' }}

                            </td>

                            <td>

                                @php
                                    $recStatus = strtolower(trim((string) $record->status));
                                    $recOpen = strcasecmp((string) $record->status, 'Completed') !== 0;
                                    $recOverdue = $recOpen && $record->deadline_date && $record->deadline_date->isPast();
                                @endphp

                                @if($recStatus === 'pending')
                                    @if($record->has_pending_proof)
                                        <span class="portal-badge maroon">
                                            For Review
                                        </span>
                                    @else
                                        <span class="portal-badge warning">
                                            Pending
                                        </span>
                                    @endif
                                @elseif($recStatus === 'in progress')
                                    <span class="portal-badge goldenrod">
                                        In Progress
                                    </span>
                                @elseif($recStatus === 'overdue' || $recOverdue)
                                    <span class="portal-badge maroon">
                                        Overdue
                                    </span>
                                @elseif($recStatus === 'completed')
                                    <span class="portal-badge success">
                                        Completed
                                    </span>
                                @else
                                    <span class="portal-badge muted">
                                        {{ $record->status }}
                                    </span>
                                @endif

                            </td>

                            <td>

                                {{ optional($record->date_assigned)->format('M d, Y h:i A') }}

                            </td>

                            <td>

                                @if($record->deadline_date)
                                    <span class="fw-semibold {{ ($recOverdue || $recStatus === 'overdue') ? 'text-danger' : '' }}">
                                        {{ $record->deadline_date->format('M d, Y') }}
                                    </span>
                                    @if($recOverdue || $recStatus === 'overdue')
                                        <span class="portal-badge maroon d-block mt-1">Overdue</span>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif

                            </td>

                            <td style="max-width:320px; white-space:normal;">

                                {{ optional($record->assignedSanction)->disciplinary_sanction ?? 'N/A' }}

                            </td>

                            <td style="min-width:220px;">

                                @if(strcasecmp($record->status, 'Completed') === 0)

                                    @if($record->proof_path)
                                        <div class="small text-success mb-1">
                                            <i class="bi bi-patch-check-fill me-1"></i>Proof accepted
                                        </div>
                                        <a href="{{ route('student.proof.download', $record) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-paperclip me-1"></i>View Proof
                                        </a>
                                    @else
                                        <span class="small text-muted">Completed by administration</span>
                                    @endif

                                @elseif($record->has_pending_proof)

                                    <div class="small text-muted mb-1">
                                        <i class="bi bi-hourglass-split me-1"></i>Proof submitted — awaiting admin review.
                                    </div>

                                    @if($record->proof_path)
                                        <a href="{{ route('student.proof.download', $record) }}" target="_blank" class="btn btn-sm btn-outline-secondary mb-1">
                                            <i class="bi bi-paperclip me-1"></i>View Submitted Proof
                                        </a>
                                    @endif

                                    @if($record->completion_remarks)
                                        <div class="small text-danger mt-1">
                                            <i class="bi bi-x-circle me-1"></i>Last review: {{ $record->completion_remarks }}
                                        </div>
                                    @endif

                                @elseif($recOpen)

                                    @if($record->completion_remarks)
                                        <div class="small text-danger mb-2">
                                            <i class="bi bi-x-circle me-1"></i>Your previous proof was not accepted: {{ $record->completion_remarks }}
                                        </div>
                                    @endif

                                    <button
                                        type="button"
                                        class="btn portal-btn btn-sm submit-proof-btn"
                                        data-record-id="{{ $record->record_id }}"
                                        data-violation="{{ optional(optional($record->violation)->violationType)->violation_type ?? 'N/A' }}">
                                        <i class="bi bi-upload me-1"></i>Submit Proof
                                    </button>

                                @else

                                    <span class="text-muted small">—</span>

                                @endif

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="6"
                                class="text-center py-5 text-muted">

                                No sanction records found.

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>
            </div>

        </div>

    </div>

</div>

</div>

        </div>

<div class="modal fade" id="requestSanctionConfirmModal" tabindex="-1" aria-labelledby="requestSanctionConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requestSanctionConfirmModalLabel">Confirm Sanction Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to request this disciplinary sanction?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn portal-btn" id="confirmRequestSanctionBtn">
                    <i class="bi bi-send"></i>
                    Yes, Request Sanction
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Submit Proof of Completion modal --}}
<div class="modal fade" id="submitProofModal" tabindex="-1" aria-labelledby="submitProofModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="submitProofModalLabel">Submit Proof of Completion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="submitProofForm" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="record_id" id="proofRecordId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Violation Type</label>
                        <div class="form-control bg-light" id="proofViolationDisplay" readonly>—</div>
                    </div>
                    <div class="mb-3">
                        <label for="proofFile" class="form-label fw-semibold">Proof of Completion <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="proofFile" name="proof_file" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx" required>
                        <small class="text-muted">Accepted: JPG, PNG, GIF, PDF, DOC, DOCX. Max 5MB.</small>
                    </div>
                    <div class="mb-3">
                        <label for="proofNotes" class="form-label fw-semibold">Notes (Optional)</label>
                        <textarea class="form-control" id="proofNotes" name="proof_notes" rows="3" maxlength="1000" placeholder="Add any remarks about your completion..."></textarea>
                    </div>
                    <div class="alert alert-info small mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Your proof will be sent to the administrator for verification. You will be notified once it is accepted or rejected.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn portal-btn" id="submitProofBtn">
                        <i class="bi bi-send me-1"></i>Submit Proof
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Deep-link support: if the URL contains #sanctionRecord, open the
    // Sanction Record tab automatically (used by notification links).
    if (window.location.hash === '#sanctionRecord') {
        const sanctionTab = document.getElementById('sanction-tab');
        if (sanctionTab) {
            bootstrap.Tab.getOrCreateInstance(sanctionTab).show();
        }
    }

    // Proof of completion submission.
    const submitProofModalEl = document.getElementById('submitProofModal');
    const submitProofForm = document.getElementById('submitProofForm');

    if (submitProofModalEl && submitProofForm) {
        const submitProofModal = new bootstrap.Modal(submitProofModalEl);

        document.querySelectorAll('.submit-proof-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                document.getElementById('proofRecordId').value = button.dataset.recordId || '';
                document.getElementById('proofViolationDisplay').textContent = button.dataset.violation || '—';
                submitProofForm.reset();
                document.getElementById('proofRecordId').value = button.dataset.recordId || '';
                submitProofModal.show();
            });
        });

        submitProofForm.addEventListener('submit', function (event) {
            event.preventDefault();

            const recordId = document.getElementById('proofRecordId').value;
            const fileInput = document.getElementById('proofFile');
            const submitBtn = document.getElementById('submitProofBtn');

            if (!recordId || !fileInput.files.length) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Information',
                    text: 'Please select a proof file to upload.',
                });
                return;
            }

            if (submitBtn) { submitBtn.disabled = true; }

            const formData = new FormData(submitProofForm);

            fetch("{{ route('student.submit-proof') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                },
                body: formData,
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    Swal.fire({
                        icon: data.success ? 'success' : 'error',
                        title: data.success ? 'Success' : 'Error',
                        text: data.message,
                    });
                    if (data.success) {
                        submitProofModal.hide();
                        location.reload();
                    }
                })
                .catch(function () {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An unexpected error occurred while submitting your proof.',
                    });
                })
                .finally(function () {
                    if (submitBtn) { submitBtn.disabled = false; }
                });
        });
    }
});
</script>

<script src="{{ asset('js/student-record.js') }}"></script>

@endpush