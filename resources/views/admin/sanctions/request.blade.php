@extends('layouts.admin')

@section('title', 'Sanction Request')

@section('content')
<div class="container-fluid">
    <div class="admin-breadcrumb-standalone mb-3">
        <a href="{{ route('admin.sanctions.index', ['sanctions_tab' => 'sanction-requests']) }}" class="text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i>Sanction Requests
        </a>
    </div>

    <div class="page-header-modern mb-3">
        <div>
            <h3 class="mb-1">Disciplinary Sanction Request #{{ $sanctionRequest->request_id }}</h3>
            <p class="mb-0">Review the exact request associated with this notification.</p>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-6">
                    <span class="admin-filter-label">Student</span>
                    <h5 class="mb-1">{{ trim(($sanctionRequest->student?->first_name ?? '') . ' ' . ($sanctionRequest->student?->last_name ?? '')) ?: 'Student record unavailable' }}</h5>
                    <div class="text-muted">{{ $sanctionRequest->student_number }}</div>
                </div>
                <div class="col-md-3">
                    <span class="admin-filter-label">Status</span>
                    <div><x-admin.badge :status="strtolower($sanctionRequest->status ?: 'pending')" :label="$sanctionRequest->status ?: 'Pending'" /></div>
                </div>
                <div class="col-md-3">
                    <span class="admin-filter-label">Requested</span>
                    <div>{{ optional($sanctionRequest->request_date)->format('M d, Y h:i A') ?: 'Date unavailable' }}</div>
                </div>
                <div class="col-md-6">
                    <span class="admin-filter-label">Violation Type</span>
                    <div>{{ $sanctionRequest->violationType?->violation_type ?: 'Violation type unavailable' }}</div>
                </div>
                <div class="col-md-6">
                    <span class="admin-filter-label">Offense Level</span>
                    <div>{{ $offenseLevel ?: 'Not available' }}</div>
                </div>
                <div class="col-12">
                    <span class="admin-filter-label">Violation Remarks</span>
                    <div class="border rounded-3 p-3 bg-light">{{ $latestViolation?->description ?: 'No remarks recorded.' }}</div>
                </div>
                <div class="col-12">
                    <span class="admin-filter-label">Sanction Details</span>
                    <div class="border rounded-3 p-3 bg-light">{{ $assignedSanction?->disciplinary_sanction ?: 'No matching sanction is configured yet.' }}</div>
                </div>
            </div>

            @if($sanctionRequest->is_active)
                <div class="border-top mt-4 pt-4">
                    <form action="{{ route('admin.sanctions.requests.approve', $sanctionRequest) }}" method="POST" class="d-flex flex-wrap align-items-end gap-2">
                        @csrf
                        <div>
                            <label for="notification_date" class="admin-filter-label">Notification / Date Assigned</label>
                            <input type="date" id="notification_date" name="notification_date" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                        </div>
                        <div>
                            <label for="deadline_date" class="admin-filter-label">Deadline (if needed)</label>
                            <input type="date" id="deadline_date" name="deadline_date" class="form-control" value="">
                            <small class="text-muted">Optional completion deadline shown to the student.</small>
                        </div>
                        <button type="submit" class="admin-btn admin-btn-success"><i class="bi bi-check2-circle"></i>Approve &amp; Notify</button>
                    </form>
                    <form action="{{ route('admin.sanctions.requests.decline', $sanctionRequest) }}" method="POST" class="mt-2">
                        @csrf
                        <button type="submit" class="admin-btn admin-btn-danger"><i class="bi bi-x-circle"></i>Decline Request</button>
                    </form>
                </div>
            @else
                <div class="border-top mt-4 pt-4 text-muted">This request has already been processed.</div>
            @endif
        </div>
    </div>
</div>
@endsection