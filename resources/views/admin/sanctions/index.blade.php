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
                    <button class="nav-link active" id="sanction-management-tab" data-bs-toggle="tab" data-bs-target="#sanction-management" type="button" role="tab">
                        <i class="bi bi-shield-check me-1"></i>
                        Sanctions
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="sanction-request-tab" data-bs-toggle="tab" data-bs-target="#sanction-requests" type="button" role="tab">
                        <i class="bi bi-inbox me-1"></i>
                        Sanction Requests
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="student-sanctions-tab" data-bs-toggle="tab" data-bs-target="#student-sanctions" type="button" role="tab">
                        <i class="bi bi-list-check me-1"></i>
                        Student Sanctions
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="sanction-management" role="tabpanel">
                    <div id="sanctionContainer">
                        @include('admin.violations.configuration.sanctions')
                    </div>
                </div>

                <div class="tab-pane fade" id="sanction-requests" role="tabpanel">
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
                                                class="btn btn-sm btn-primary review-sanction-request-btn"
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
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">No pending sanction requests found.</td>
                                    </tr>
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

                <div class="tab-pane fade" id="student-sanctions" role="tabpanel">
                    <form method="GET" action="{{ route('admin.sanctions.index') }}" class="mb-4">
                        <input type="hidden" name="sanctions_tab" value="student-sanctions">
                        <div class="border rounded-3 p-3 bg-light-subtle">
                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                <h6 class="mb-0 fw-semibold">Filter Student Sanctions</h6>
                                <small class="text-muted">Use one or more filters to narrow records.</small>
                            </div>

                            <div class="row g-3 align-items-end">
                                <div class="col-lg-4">
                                    <label class="form-label mb-1">Search</label>
                                    <input
                                        type="text"
                                        name="record_search"
                                        class="form-control"
                                        placeholder="Student #, name, or violation type"
                                        value="{{ request('record_search') }}">
                                </div>

                                <div class="col-lg-3">
                                    <label class="form-label mb-1">Violation Type</label>
                                    <select name="record_violation_type" class="form-select">
                                        <option value="">All Violation Types</option>
                                        @foreach($violationTypes as $type)
                                            <option value="{{ $type->violation_type_id }}" @selected((string) request('record_violation_type') === (string) $type->violation_type_id)>
                                                {{ $type->violation_type }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-lg-2">
                                    <label class="form-label mb-1">From Date</label>
                                    <input
                                        type="date"
                                        class="form-control"
                                        name="record_from_date"
                                        value="{{ request('record_from_date') }}">
                                </div>

                                <div class="col-lg-2">
                                    <label class="form-label mb-1">To Date</label>
                                    <input
                                        type="date"
                                        class="form-control"
                                        name="record_to_date"
                                        value="{{ request('record_to_date') }}">
                                </div>

                                <div class="col-lg-1 d-none d-lg-block"></div>

                                <div class="col-lg-6">
                                    <label class="form-label mb-1 d-block">Status</label>
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
                                                value="Completed"
                                                id="record_status_completed"
                                                @checked(in_array('Completed', (array) request('record_statuses', []), true))>
                                            <label class="form-check-label" for="record_status_completed">Completed</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-6">
                                    <div class="d-flex gap-2 justify-content-lg-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-search me-1"></i>
                                            Apply Filters
                                        </button>
                                        <a href="{{ route('admin.sanctions.index', ['sanctions_tab' => 'student-sanctions']) }}" class="btn btn-outline-secondary">
                                            <i class="bi bi-arrow-clockwise me-1"></i>
                                            Reset
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Violation Type</th>
                                    <th>Sanction</th>
                                    <th>Date Assigned</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($studentSanctions as $record)
                                    <tr>
                                        <td>
                                            <strong>{{ $record->student_number }}</strong>
                                            <div class="text-muted small">
                                                {{ optional(optional($record->violation)->student)->first_name }} {{ optional(optional($record->violation)->student)->last_name }}
                                            </div>
                                        </td>
                                        <td>{{ optional(optional($record->violation)->violationType)->violation_type ?? 'N/A' }}</td>
                                        <td style="max-width: 280px; white-space: normal;">{{ optional($record->assignedSanction)->disciplinary_sanction ?? 'N/A' }}</td>
                                        <td>{{ optional($record->date_assigned)->format('M d, Y') ?? '-' }}</td>
                                        <td>
                                            @if(strcasecmp((string) $record->status, 'Pending') === 0)
                                                <span class="badge bg-warning text-dark">Pending</span>
                                            @elseif(strcasecmp((string) $record->status, 'Completed') === 0)
                                                <span class="badge bg-success">Completed</span>
                                            @else
                                                <span class="badge bg-secondary">{{ $record->status }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if(strcasecmp((string) $record->status, 'Pending') === 0)
                                                <form action="{{ route('admin.sanctions.records.complete', $record) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-success">
                                                        Mark as Completed
                                                    </button>
                                                </form>
                                            @elseif(strcasecmp((string) $record->status, 'Completed') === 0)
                                                <form action="{{ route('admin.sanctions.records.revert', $record) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-warning">
                                                        Revert to Pending
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-muted small">No action needed</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">No student sanctions found.</td>
                                    </tr>
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
            </div>
        </div>
    </div>
</div>

@include('admin.violations.partials.sanction-modal')

<div class="modal fade" id="sanctionRequestReviewModal" tabindex="-1" aria-labelledby="sanctionRequestReviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sanctionRequestReviewModalLabel">Sanction Request Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
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
                    <label for="notification_date" class="form-label fw-semibold">Notification / Date Assigned (Student Sanction Tab)</label>
                    <input type="date" id="notification_date" name="notification_date" class="form-control" required>
                    <small class="text-muted">The selected date will be shown in the student's Sanction Record tab.</small>
                </form>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <form id="declineSanctionRequestForm" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger">Decline</button>
                </form>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="approveSanctionRequestForm" class="btn btn-success" id="approveSanctionRequestBtn">Approve & Notify</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
window.ViolationRoutes = {
    sanctionIndex: "{{ route('admin.disciplinary-sanctions.index') }}",
    sanctionStore: "{{ route('admin.disciplinary-sanctions.store') }}",
    sanctionShow: "{{ route('admin.disciplinary-sanctions.show', ':id') }}",
    sanctionUpdate: "{{ route('admin.disciplinary-sanctions.update', ':id') }}",
    sanctionDelete: "{{ route('admin.disciplinary-sanctions.destroy', ':id') }}",
};

document.addEventListener('DOMContentLoaded', function () {
    const approveForm = document.getElementById('approveSanctionRequestForm');
    const declineForm = document.getElementById('declineSanctionRequestForm');
    const dateInput = document.getElementById('notification_date');
    const today = new Date().toISOString().split('T')[0];
    const requestedTab = @json(request('sanctions_tab'));

    if (dateInput) {
        dateInput.value = today;
    }

    if (requestedTab === 'student-sanctions') {
        const studentSanctionsTab = document.getElementById('student-sanctions-tab');
        if (studentSanctionsTab) {
            bootstrap.Tab.getOrCreateInstance(studentSanctionsTab).show();
        }
    }

    document.querySelectorAll('.review-sanction-request-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            const requestId = button.dataset.requestId;
            const approveUrlTemplate = "{{ route('admin.sanctions.requests.approve', ['sanctionRequest' => '__REQUEST_ID__']) }}";
            const declineUrlTemplate = "{{ route('admin.sanctions.requests.decline', ['sanctionRequest' => '__REQUEST_ID__']) }}";

            approveForm.action = approveUrlTemplate.replace('__REQUEST_ID__', requestId);
            declineForm.action = declineUrlTemplate.replace('__REQUEST_ID__', requestId);

            document.getElementById('requestModalStudent').textContent = button.dataset.student || '-';
            document.getElementById('requestModalViolation').textContent = button.dataset.violation || '-';
            document.getElementById('requestModalOffense').textContent = button.dataset.offense || '-';
            document.getElementById('requestModalDescription').textContent = button.dataset.description || 'No remarks recorded.';
            document.getElementById('requestModalSanction').textContent = button.dataset.sanction || 'No matching sanction is configured yet.';
        });
    });
});
</script>

<script src="{{ asset('js/admin/sanction.js') }}"></script>
@endpush
