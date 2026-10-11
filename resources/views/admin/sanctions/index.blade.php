@extends('layouts.admin')

@section('title', 'Sanctions')

@section('content')
<div class="container-fluid">
    <div class="page-header-modern mb-3">
        <div>
            <h3 class="mb-1">Sanctions</h3>
            <p class="mb-0">Manage disciplinary sanctions and review all student sanction requests.</p>
        </div>
    </div>

    @error('sanction_request')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror
    @error('sanction_record')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <ul class="nav nav-tabs" id="sanctionTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ (request('sanctions_tab', 'sanction-requests') === 'sanction-requests' && !request('tab')) ? 'active' : '' }}" id="sanction-request-tab" data-bs-toggle="tab" data-bs-target="#sanction-requests" type="button" role="tab">
                        <i class="bi bi-inbox me-1"></i>
                        Sanction Requests
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ request('sanctions_tab') === 'student-sanctions' ? 'active' : '' }}" id="student-sanctions-tab" data-bs-toggle="tab" data-bs-target="#student-sanctions" type="button" role="tab">
                        <i class="bi bi-list-check me-1"></i>
                        Student Sanctions
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ request('sanctions_tab') === 'sanction-management' ? 'active' : '' }}" id="sanction-management-tab" data-bs-toggle="tab" data-bs-target="#sanction-management" type="button" role="tab">
                        <i class="bi bi-shield-check me-1"></i>
                        Sanction Configuration
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body">
            <div class="tab-content">
                <div class="tab-pane fade {{ (request('sanctions_tab', 'sanction-requests') === 'sanction-requests' && !request('tab')) ? 'show active' : '' }}" id="sanction-requests" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                        <span class="text-muted small">
                            Showing {{ $requests->firstItem() ?? 0 }}–{{ $requests->lastItem() ?? 0 }} of {{ $requests->total() }} pending requests
                        </span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Violation Type</th>
                                    <th>Requested At</th>
                                    <th>Offense Level</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($requests as $request)
                                    <tr>
                                        <td>
                                            <strong>{{ optional($request->student)->student_number }}</strong>
                                            <div class="text-muted small">
                                                {{ optional($request->student)->first_name }} {{ optional($request->student)->last_name }}
                                            </div>
                                        </td>
                                        <td>{{ optional($request->violationType)->violation_type ?? 'Unknown' }}</td>
                                        <td>{{ optional($request->request_date)->format('M d, Y h:i A') }}</td>
                                        <td>{{ $request->resolved_offense_level }}</td>
                                        <td class="text-end">
                                            <button
                                                type="button"
                                                class="admin-btn admin-btn-secondary admin-btn-sm review-sanction-request-btn"
                                                data-request-id="{{ $request->request_id }}"
                                                data-student="{{ optional($request->student)->student_number }}"
                                                data-violation="{{ optional($request->violationType)->violation_type ?? 'Unknown' }}"
                                                data-offense="{{ $request->resolved_offense_level }}"
                                                data-sanction="{{ e($request->resolved_sanction_description) }}"
                                                data-description="{{ e($request->resolved_violation_description) }}"
                                                data-bs-toggle="modal"
                                                data-bs-target="#sanctionRequestReviewModal">
                                                Review Request
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <x-admin.empty-state
                                        :colspan="5"
                                        icon="bi-inbox"
                                        title="No pending sanction requests"
                                        message="No pending sanction requests were found."
                                        :clearUrl="route('admin.sanctions.index')" />
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($requests->hasPages())
                        <div class="mt-3">
                            {{ $requests->appends(request()->except('requests_page'))->links() }}
                        </div>
                    @endif
                </div>

                <div class="tab-pane fade {{ request('sanctions_tab') === 'student-sanctions' ? 'show active' : '' }}" id="student-sanctions" role="tabpanel">
                    <form method="GET" action="{{ route('admin.sanctions.index') }}" class="mb-4 admin-filter-form">
                        <input type="hidden" name="sanctions_tab" value="student-sanctions">
                        <div class="border rounded-3 p-3 bg-light-subtle">
                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                <h6 class="mb-0 fw-semibold admin-section-title">Filter Student Sanctions</h6>
                                <small class="admin-section-subtitle">Use one or more filters to narrow records.</small>
                            </div>

                            <div class="row g-3 align-items-end">
                                <div class="col-lg-6 col-xl-4">
                                    <label class="admin-filter-label">Search</label>
                                    <input
                                        type="text"
                                        name="record_search"
                                        class="form-control"
                                        placeholder="Student #, name, or violation type"
                                        value="{{ request('record_search') }}">
                                </div>

                                <div class="col-lg-3 col-xl-3">
                                    <label class="admin-filter-label">Violation Type</label>
                                    <select name="record_violation_type" class="form-select">
                                        <option value="">All Violation Types</option>
                                        @foreach($violationTypes as $type)
                                            <option value="{{ $type->violation_type_id }}" @selected((string) request('record_violation_type') === (string) $type->violation_type_id)>
                                                {{ $type->violation_type }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-lg-3 col-xl-2">
                                    <label class="admin-filter-label">From Date</label>
                                    <input
                                        type="date"
                                        class="form-control"
                                        name="record_from_date"
                                        value="{{ request('record_from_date') }}">
                                </div>

                                <div class="col-lg-3 col-xl-2">
                                    <label class="admin-filter-label">To Date</label>
                                    <input
                                        type="date"
                                        class="form-control"
                                        name="record_to_date"
                                        value="{{ request('record_to_date') }}">
                                </div>

                                <div class="col-lg-12 col-xl-6">
                                    <label class="admin-filter-label d-block">Status</label>
                                    <div class="d-flex gap-3 flex-wrap pt-1">
                                        <div class="form-check">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                name="record_statuses[]"
                                                value="Pending"
                                                id="record_status_pending"
                                                @checked(in_array('Pending', (array) request('record_statuses', []), true))>
                                            <label class="form-check-label" for="record_status_pending">Pending</label>
                                        </div>
                                        <div class="form-check">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                name="record_statuses[]"
                                                value="In Progress"
                                                id="record_status_in_progress"
                                                @checked(in_array('In Progress', (array) request('record_statuses', []), true))>
                                            <label class="form-check-label" for="record_status_in_progress">In Progress</label>
                                        </div>
                                        <div class="form-check">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                name="record_statuses[]"
                                                value="Overdue"
                                                id="record_status_overdue"
                                                @checked(in_array('Overdue', (array) request('record_statuses', []), true))>
                                            <label class="form-check-label" for="record_status_overdue">Overdue</label>
                                        </div>
                                        <div class="form-check">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                name="record_statuses[]"
                                                value="Completed"
                                                id="record_status_completed"
                                                @checked(in_array('Completed', (array) request('record_statuses', []), true))>
                                            <label class="form-check-label" for="record_status_completed">Completed</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-12 col-xl-6">
                                    <div class="d-flex gap-2 justify-content-xl-end">
                                        <button type="submit" class="admin-btn admin-btn-primary">
                                            <i class="bi bi-search me-1"></i>
                                            Apply Filters
                                        </button>
                                        <a href="{{ route('admin.sanctions.index', ['sanctions_tab' => 'student-sanctions']) }}" class="admin-btn admin-btn-neutral">
                                            <i class="bi bi-arrow-clockwise me-1"></i>
                                            Reset
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>

                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                        <span class="text-muted small">
                            Showing {{ $studentSanctions->firstItem() ?? 0 }}–{{ $studentSanctions->lastItem() ?? 0 }} of {{ $studentSanctions->total() }} student sanctions
                        </span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Violation Type</th>
                                    <th>Sanction</th>
                                    <th>Date Assigned</th>
                                    <th>Deadline</th>
                                    <th>Status</th>
                                    <th>Proof of Completion</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($studentSanctions as $record)
                                    <tr @if((int) $record->record_id === (int) request('record_id')) class="table-warning" @endif>
                                        <td>
                                            <strong>{{ $record->student_number }}</strong>
                                            <div class="text-muted small">
                                                {{ optional(optional($record->violation)->student)->first_name }} {{ optional(optional($record->violation)->student)->last_name }}
                                            </div>
                                        </td>
                                        <td>{{ optional(optional($record->violation)->violationType)->violation_type ?? 'N/A' }}</td>
                                        <td style="max-width: 240px; white-space: normal;">
                                            {{ optional($record->assignedSanction)->disciplinary_sanction ?? 'N/A' }}
                                            @if($record->is_service_sanction)
                                                <div class="small text-muted mt-1">
                                                    <i class="bi bi-building me-1"></i>{{ $record->designated_office ?: '—' }}
                                                </div>
                                                <div class="small text-muted">
                                                    <i class="bi bi-calendar-event me-1"></i>Starts {{ optional($record->starting_date)->format('M d, Y') ?? '—' }}
                                                </div>
                                            @endif
                                        </td>
                                        <td>{{ optional($record->date_assigned)->format('M d, Y') ?? '-' }}</td>
                                        <td>
                                            @php
                                                $isOpen = strcasecmp((string) $record->status, 'Completed') !== 0;
                                                $isOverdue = $isOpen && $record->deadline_date && $record->deadline_date->isPast();
                                                $isOverdueStatus = strcasecmp((string) $record->status, 'Overdue') === 0;
                                            @endphp
                                            @if($record->deadline_date)
                                                <span class="{{ ($isOverdue || $isOverdueStatus) ? 'text-danger fw-semibold' : '' }}">
                                                    {{ $record->deadline_date->format('M d, Y') }}
                                                </span>
                                                @if($isOverdue || $isOverdueStatus)
                                                    <span class="d-block small text-danger">Overdue</span>
                                                @endif
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(strcasecmp((string) $record->status, 'Pending') === 0)
                                                @if($record->has_pending_proof)
                                                    <x-admin.badge status="pending" label="For Review" />
                                                @else
                                                    <x-admin.badge status="pending" />
                                                @endif
                                            @elseif(strcasecmp((string) $record->status, 'In Progress') === 0)
                                                <x-admin.badge status="in progress" />
                                            @elseif(strcasecmp((string) $record->status, 'Overdue') === 0)
                                                <x-admin.badge status="overdue" />
                                            @elseif(strcasecmp((string) $record->status, 'Completed') === 0)
                                                <x-admin.badge status="completed" />
                                            @else
                                                <x-admin.badge status="gray" :label="$record->status" />
                                            @endif
                                        </td>
                                        <td style="min-width:200px;">
                                            @if(! $record->requires_proof_of_completion)
                                                {{-- Non-service sanctions take NO proof of
                                                     completion: the student is directed to the
                                                     Office of Student Services for a personal
                                                     consultation. --}}
                                                @if($isOpen)
                                                    <div class="small text-muted">
                                                        <i class="bi bi-info-circle me-1"></i>Personal consultation required at the Office of Student Services.
                                                    </div>
                                                @else
                                                    <span class="text-muted small">No proof required</span>
                                                @endif
                                            @elseif($record->has_pending_proof)
                                                <div class="small text-muted mb-1">
                                                    <i class="bi bi-file-earmark-arrow-up me-1"></i>
                                                    {{ $record->proof_original_name ?: 'Proof submitted' }}
                                                </div>
                                                <div class="small text-muted mb-1">
                                                    {{ optional($record->proof_submitted_at)->format('M d, Y h:i A') }}
                                                </div>
                                                
@if($record->proof_path && filled($record->proof_access_key))
    <a href="{{ route('admin.sanctions.proof.download', [
        'studentSanctionRecord' => $record->proof_access_key,
    ]) }}"
       target="_blank"
       rel="noopener noreferrer"
       class="admin-btn admin-btn-view admin-btn-sm">
        <i class="bi bi-eye me-1"></i>View Proof
    </a>
@endif

                                          
@elseif($record->proof_path
    && strcasecmp((string) $record->status, 'Completed') === 0
    && filled($record->proof_access_key))
    <a href="{{ route('admin.sanctions.proof.download', [
        'studentSanctionRecord' => $record->proof_access_key,
    ]) }}"
       target="_blank"
       rel="noopener noreferrer"
       class="admin-btn admin-btn-view admin-btn-sm">
        <i class="bi bi-eye me-1"></i>View Proof
    </a>

                                            @elseif($isOpen && $record->completion_remarks)
                                                <div class="small text-danger mb-1">
                                                    <i class="bi bi-x-circle me-1"></i>Previous proof rejected:
                                                    {{ $record->completion_remarks }}
                                                </div>
                                                <span class="text-muted small">Awaiting new proof</span>
                                            @else
                                                <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if(! $record->requires_proof_of_completion)
                                                {{-- Non-service sanction: no proof to review — the
                                                     administrator closes the record once the student
                                                     has consulted the Office of Student Services. --}}
                                                @if($isOpen)
                                                    <form action="{{ route('admin.sanctions.records.complete', $record) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="admin-btn admin-btn-success admin-btn-sm" title="Mark as completed after the personal consultation">
                                                            Mark Completed
                                                        </button>
                                                    </form>
                                                @else
                                                    <button type="button"
                                                            class="admin-btn admin-btn-view admin-btn-sm reopen-sanction-btn"
                                                            data-record-id="{{ $record->record_id }}"
                                                            data-student="{{ $record->student_number }}">
                                                        Reopen
                                                    </button>
                                                @endif
                                            @elseif($record->has_pending_proof)
                                                <form action="{{ route('admin.sanctions.records.complete', $record) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="admin-btn admin-btn-success admin-btn-sm">
                                                        Accept
                                                    </button>
                                                </form>
                                                <button
                                                    type="button"
                                                    class="admin-btn admin-btn-danger admin-btn-sm reject-proof-btn"
                                                    data-record-id="{{ $record->record_id }}"
                                                    data-student="{{ $record->student_number }}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#rejectProofModal">
                                                    Reject
                                                </button>
                                            @elseif($isOpen && $record->completion_remarks)
                                                <span class="text-muted small">Awaiting resubmission</span>
                                            @elseif($isOpen)
                                                <span class="text-muted small">Awaiting proof</span>
                                            @elseif(strcasecmp((string) $record->status, 'Completed') === 0)
                                                <button type="button"
                                                        class="admin-btn admin-btn-view admin-btn-sm reopen-sanction-btn"
                                                        data-record-id="{{ $record->record_id }}"
                                                        data-student="{{ $record->student_number }}">
                                                    Reopen
                                                </button>
                                            @else
                                                <span class="text-muted small">No action needed</span>
                                            @endif

                                            @if($record->is_service_sanction && $isOpen)
                                                <button
                                                    type="button"
                                                    class="admin-btn admin-btn-neutral admin-btn-sm service-assignment-btn mt-1"
                                                    data-record-id="{{ $record->record_id }}"
                                                    data-student="{{ $record->student_number }}"
                                                    data-office="{{ $record->designated_office }}"
                                                    data-starting-date="{{ optional($record->starting_date)->format('Y-m-d') }}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#serviceAssignmentModal">
                                                    <i class="bi bi-pencil-square me-1"></i>Edit Assignment
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <x-admin.empty-state
                                        :colspan="8"
                                        icon="bi-shield-check"
                                        title="No student sanctions found"
                                        message="No student sanctions match your current filters."
                                        :clearUrl="route('admin.sanctions.index', ['sanctions_tab' => 'student-sanctions'])" />
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($studentSanctions->hasPages())
                        <div class="mt-3">
                            {{ $studentSanctions->appends(request()->except('records_page'))->links() }}
                        </div>
                    @endif
                </div>

                <div class="tab-pane fade {{ request('sanctions_tab') === 'sanction-management' ? 'show active' : '' }}" id="sanction-management" role="tabpanel">
                    <div id="sanctionContainer">
                        @include('admin.violations.configuration.sanctions')
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin.violations.partials.sanction-modal')

<div class="modal fade admin-modal" id="sanctionRequestReviewModal" tabindex="-1" aria-labelledby="sanctionRequestReviewModalLabel" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content admin-modal-content">
            <div class="modal-header admin-modal-header">
                <h5 class="modal-title admin-modal-title" id="sanctionRequestReviewModalLabel">Sanction Request Review</h5>
                <button type="button" class="btn-close admin-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body admin-modal-body">
                <div class="mb-2"><strong>Student Number:</strong> <span id="requestModalStudent">-</span></div>
                <div class="mb-2"><strong>Violation Type:</strong> <span id="requestModalViolation">-</span></div>
                <div class="mb-3"><strong>Offense Level:</strong> <span id="requestModalOffense">-</span></div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Violation Remarks</label>
                    <div class="border rounded p-3 bg-light" id="requestModalDescription"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Sanction Details</label>
                    <div class="border rounded p-3 bg-light" id="requestModalSanction"></div>
                </div>

                <form id="approveSanctionRequestForm" method="POST" class="mb-2">
                    @csrf
                    <label for="notification_date" class="form-label fw-semibold">Date Assigned (shown in Student Sanction Record)</label>
                    <input type="date" id="notification_date" name="notification_date" class="form-control" required>
                    <small class="text-muted">The selected date will be shown in the student's Sanction Record tab.</small>
                    <label for="modal_deadline_date" class="form-label fw-semibold mt-3">Deadline (if needed)</label>
                    <input type="date" id="modal_deadline_date" name="deadline_date" class="form-control">
                    <small class="text-muted">Optional completion deadline shown to the student.</small>

                    {{-- Community Service / Campus Service assignment fields.
                         Hidden by default; the modal shows and requires them only
                         when the reviewed sanction is a service sanction. --}}
                    <div id="serviceAssignmentFields" class="d-none">
                        <label for="modal_designated_office" class="form-label fw-semibold mt-3">Designated Office <span class="text-danger">*</span></label>
                        <input type="text" id="modal_designated_office" name="designated_office" class="form-control" placeholder="e.g. Office of Student Affairs" maxlength="255" disabled>
                        <small class="text-muted">Office where the student will render the service.</small>
                        <label for="modal_starting_date" class="form-label fw-semibold mt-3">Starting Date <span class="text-danger">*</span></label>
                        <input type="date" id="modal_starting_date" name="starting_date" class="form-control" disabled>
                        <small class="text-muted">Date the student is authorized to start rendering the service.</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer admin-modal-footer d-flex justify-content-between">
                <form id="declineSanctionRequestForm" method="POST">
                    @csrf
                    <button type="submit" class="admin-btn admin-btn-danger">Decline</button>
                </form>

                <div class="d-flex gap-2">
                    <button type="button" class="admin-btn admin-btn-neutral" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="approveSanctionRequestForm" class="admin-btn admin-btn-success" id="approveSanctionRequestBtn">Approve & Notify</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Reopen completed sanction modal.
     A reason is REQUIRED and is re-validated server-side. --}}
<div class="modal fade admin-modal" id="reopenSanctionModal" tabindex="-1" aria-labelledby="reopenSanctionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content admin-modal-content">
            <div class="modal-header admin-modal-header">
                <h5 class="modal-title admin-modal-title" id="reopenSanctionModalLabel">Reopen Sanction</h5>
                <button type="button" class="btn-close admin-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="reopenSanctionForm" method="POST">
                @csrf
                <div class="modal-body admin-modal-body">
                    <p class="text-muted">
                        The sanction will be set back to <strong>In Progress</strong> and the student will be notified of the reason. They may submit a new proof of completion.
                    </p>
                    <div class="mb-2"><strong>Student Number:</strong> <span id="reopenModalStudent">-</span></div>
                    <label for="reopenReason" class="form-label fw-semibold mt-2">Reason for reopening <span class="text-danger">*</span></label>
                    <textarea name="reason" id="reopenReason" class="form-control" rows="3" placeholder="Required — explain why this sanction is being reopened..." maxlength="1000" required></textarea>
                    <div class="invalid-feedback d-block" id="reopenReasonError"></div>
                    @error('reason')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="modal-footer admin-modal-footer">
                    <button type="button" class="admin-btn admin-btn-neutral" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="admin-btn admin-btn-primary">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reopen Sanction
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Reject proof of completion modal --}}
<div class="modal fade admin-modal" id="rejectProofModal" tabindex="-1" aria-labelledby="rejectProofModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content admin-modal-content">
            <div class="modal-header admin-modal-header">
                <h5 class="modal-title admin-modal-title" id="rejectProofModalLabel">Reject Proof of Completion</h5>
                <button type="button" class="btn-close admin-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="rejectProofForm" method="POST">
                @csrf
                <div class="modal-body admin-modal-body">
                    <p class="text-muted">
                        The sanction will remain <strong>Pending</strong> and the student will be notified of the reason. They may submit a new proof for verification.
                    </p>
                    <div class="mb-2"><strong>Student Number:</strong> <span id="rejectModalStudent">-</span></div>
                    <label for="rejectReason" class="form-label fw-semibold mt-2">Reason <span class="text-danger">*</span></label>
                    <textarea name="reason" id="rejectReason" class="form-control" rows="3" placeholder="Required — explain why this proof is being rejected..." maxlength="1000" required></textarea>
                </div>
                <div class="modal-footer admin-modal-footer">
                    <button type="button" class="admin-btn admin-btn-neutral" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="admin-btn admin-btn-danger">
                        <i class="bi bi-x-circle me-1"></i>Reject Proof
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Community/Campus Service assignment modal (admin-only) --}}
<div class="modal fade admin-modal" id="serviceAssignmentModal" tabindex="-1" aria-labelledby="serviceAssignmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content admin-modal-content">
            <div class="modal-header admin-modal-header">
                <h5 class="modal-title admin-modal-title" id="serviceAssignmentModalLabel">Edit Service Assignment</h5>
                <button type="button" class="btn-close admin-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="serviceAssignmentForm" method="POST">
                @csrf
                <div class="modal-body admin-modal-body">
                    <p class="text-muted">Update the designated office and starting date for this Community/Campus Service assignment. The student is notified and the change is recorded in the audit log.</p>
                    <div class="mb-2"><strong>Student Number:</strong> <span id="serviceAssignmentStudent">-</span></div>
                    <label for="serviceAssignmentOffice" class="form-label fw-semibold mt-2">Designated Office <span class="text-danger">*</span></label>
                    <input type="text" name="designated_office" id="serviceAssignmentOffice" class="form-control" maxlength="255" required>
                    <label for="serviceAssignmentStartingDate" class="form-label fw-semibold mt-3">Starting Date <span class="text-danger">*</span></label>
                    <input type="date" name="starting_date" id="serviceAssignmentStartingDate" class="form-control" required>
                </div>
                <div class="modal-footer admin-modal-footer">
                    <button type="button" class="admin-btn admin-btn-neutral" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="admin-btn admin-btn-success">
                        <i class="bi bi-check2-circle me-1"></i>Save Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@php
    $sanctionPageData = [
        'requested_tab' => (string) request('sanctions_tab'),
        'routes' => [
            'sanctionIndex' => route('admin.disciplinary-sanctions.index'),
            'sanctionStore' => route('admin.disciplinary-sanctions.store'),
            'sanctionShow' => route('admin.disciplinary-sanctions.show', ':id'),
            'sanctionUpdate' => route('admin.disciplinary-sanctions.update', ':id'),
            'sanctionDelete' => route('admin.disciplinary-sanctions.destroy', ':id'),
            'approveUrlTemplate' => route('admin.sanctions.requests.approve', ['sanctionRequest' => '__REQUEST_ID__']),
            'declineUrlTemplate' => route('admin.sanctions.requests.decline', ['sanctionRequest' => '__REQUEST_ID__']),
            'rejectProofUrlTemplate' => route('admin.sanctions.records.reject-proof', ['studentSanctionRecord' => '__RECORD_ID__']),
            'revertUrlTemplate' => route('admin.sanctions.records.revert', ['studentSanctionRecord' => '__RECORD_ID__']),
            'serviceAssignmentUrlTemplate' => route('admin.sanctions.records.update-service-assignment', ['studentSanctionRecord' => '__RECORD_ID__']),
        ],
    ];
@endphp
<script type="application/json" id="sanctionPageData">
@json($sanctionPageData)
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const pageDataEl = document.getElementById('sanctionPageData');
    let pageData = {};
    if (pageDataEl) {
        try {
            pageData = JSON.parse(pageDataEl.textContent) || {};
        } catch (e) {
            pageData = {};
        }
    }

    window.ViolationRoutes = pageData.routes || {};

    const approveForm = document.getElementById('approveSanctionRequestForm');
    const declineForm = document.getElementById('declineSanctionRequestForm');
    const rejectProofForm = document.getElementById('rejectProofForm');
    const dateInput = document.getElementById('notification_date');
    const today = new Date().toISOString().split('T')[0];

    // Read the requested tab from the JSON data script (set server-side).
    let requestedTab = pageData.requested_tab || '';

    if (dateInput) {
        dateInput.value = today;
    }

    if (requestedTab === 'student-sanctions') {
        const studentSanctionsTab = document.getElementById('student-sanctions-tab');
        if (studentSanctionsTab) {
            bootstrap.Tab.getOrCreateInstance(studentSanctionsTab).show();
        }
    }

    if (requestedTab === 'sanction-requests') {
        const sanctionRequestsTab = document.getElementById('sanction-request-tab');
        if (sanctionRequestsTab) {
            bootstrap.Tab.getOrCreateInstance(sanctionRequestsTab).show();
        }
    }

    if (requestedTab === 'sanction-management') {
        const sanctionManagementTab = document.getElementById('sanction-management-tab');
        if (sanctionManagementTab) {
            bootstrap.Tab.getOrCreateInstance(sanctionManagementTab).show();
        }
    }

    document.querySelectorAll('.review-sanction-request-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            const requestId = button.dataset.requestId;
            const approveUrlTemplate = pageData.routes.approveUrlTemplate;
            const declineUrlTemplate = pageData.routes.declineUrlTemplate;

            approveForm.action = approveUrlTemplate.replace('__REQUEST_ID__', requestId);
            declineForm.action = declineUrlTemplate.replace('__REQUEST_ID__', requestId);

            document.getElementById('requestModalStudent').textContent = button.dataset.student || '-';
            document.getElementById('requestModalViolation').textContent = button.dataset.violation || '-';
            document.getElementById('requestModalOffense').textContent = button.dataset.offense || '-';
            document.getElementById('requestModalDescription').textContent = button.dataset.description || 'No remarks recorded.';
            document.getElementById('requestModalSanction').textContent = button.dataset.sanction || 'No matching sanction is configured yet.';

            // Community Service / Campus Service assignment fields are shown and
            // required ONLY for those two sanction types.
            const sanctionText = String(button.dataset.sanction || '').toLowerCase();
const isServiceSanction =
    sanctionText.includes('community service') ||
    sanctionText.includes('campus service') ||
    sanctionText.includes('student-assistance service') ||
    sanctionText.includes('student assistance service');            const serviceWrap = document.getElementById('serviceAssignmentFields');
            if (serviceWrap) {
                serviceWrap.classList.toggle('d-none', !isServiceSanction);
                const officeInput = document.getElementById('modal_designated_office');
                const startInput = document.getElementById('modal_starting_date');
                if (officeInput) { officeInput.disabled = !isServiceSanction; officeInput.required = isServiceSanction; if (!isServiceSanction) { officeInput.value = ''; } }
                if (startInput) { startInput.disabled = !isServiceSanction; startInput.required = isServiceSanction; if (!isServiceSanction) { startInput.value = ''; } }
            }
        });
    });

    // Reject proof modal wiring.
    const rejectProofModalEl = document.getElementById('rejectProofModal');
    if (rejectProofModalEl && rejectProofForm && pageData.routes.rejectProofUrlTemplate) {
        document.querySelectorAll('.reject-proof-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                const recordId = button.dataset.recordId;
                rejectProofForm.action = pageData.routes.rejectProofUrlTemplate.replace('__RECORD_ID__', recordId);
                document.getElementById('rejectModalStudent').textContent = button.dataset.student || '-';
                document.getElementById('rejectReason').value = '';
            });
        });
    }

    // Reopen completed sanction modal wiring. The reason is required, so the
    // modal is opened from every Reopen trigger rather than submitting directly.
    const reopenSanctionModalEl = document.getElementById('reopenSanctionModal');
    const reopenSanctionForm = document.getElementById('reopenSanctionForm');
    if (reopenSanctionModalEl && reopenSanctionForm && pageData.routes.revertUrlTemplate) {
        document.querySelectorAll('.reopen-sanction-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                const recordId = button.dataset.recordId;
                reopenSanctionForm.action = pageData.routes.revertUrlTemplate.replace('__RECORD_ID__', recordId);
                document.getElementById('reopenModalStudent').textContent = button.dataset.student || '-';

                const reasonInput = document.getElementById('reopenReason');
                reasonInput.value = '';
                const errorEl = document.getElementById('reopenReasonError');
                if (errorEl) { errorEl.textContent = ''; }

                bootstrap.Modal.getOrCreateInstance(reopenSanctionModalEl).show();
            });
        });

        // Client-side hint only — the server re-validates the same rule.
        reopenSanctionForm.addEventListener('submit', function (event) {
            const reasonInput = document.getElementById('reopenReason');
            const errorEl = document.getElementById('reopenReasonError');
            const value = (reasonInput.value || '').trim();

            if (value === '') {
                event.preventDefault();
                if (errorEl) {
                    errorEl.textContent = 'Please provide a reason for reopening this sanction.';
                }
                reasonInput.focus();
            }
        });
    }

    // Community/Campus Service assignment edit modal wiring (admin-only).
    const serviceAssignmentForm = document.getElementById('serviceAssignmentForm');
    if (serviceAssignmentForm && pageData.routes.serviceAssignmentUrlTemplate) {
        document.querySelectorAll('.service-assignment-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                const recordId = button.dataset.recordId;
                serviceAssignmentForm.action = pageData.routes.serviceAssignmentUrlTemplate.replace('__RECORD_ID__', recordId);
                document.getElementById('serviceAssignmentStudent').textContent = button.dataset.student || '-';
                document.getElementById('serviceAssignmentOffice').value = button.dataset.office || '';
                document.getElementById('serviceAssignmentStartingDate').value = button.dataset.startingDate || '';
            });
        });
    }
});
</script>

<script src="{{ asset('js/admin/sanction.js') }}"></script>
@endpush
