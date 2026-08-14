@extends('layouts.admin')

@section('title', 'System Logs — Email Incidents')

@section('content')

<div class="container-fluid">

    <div class="page-header-modern d-flex justify-content-between align-items-center flex-wrap mb-4">
        <div>
            <h2 class="fw-bold mb-1">System Logs — Email Incidents</h2>
            <p class="mb-0">Recorded email dispatch failures and the corresponding Super Admin notifications (Risk No. 7).</p>
        </div>
        <div class="d-flex gap-2">
            <form action="{{ route('admin.super-admin.email-incidents.simulate-failure') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-outline-warning" title="DEVELOPMENT / TEST ONLY — does not touch production SMTP">
                    <i class="bi bi-exclamation-triangle"></i>
                    Simulate Email Failure
                </button>
            </form>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->has('simulate'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            {{ $errors->first('simulate') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="alert alert-warning d-flex align-items-center gap-2 py-2 small mb-4" role="alert">
        <i class="bi bi-info-circle"></i>
        <span><strong>DEVELOPMENT / TEST ONLY:</strong> The <em>Simulate Email Failure</em> button exercises the exact same
        failure-handling path as a real email failure (error log &rarr; incident &rarr; Super Admin notification) without touching
        production SMTP or mailgun credentials.</span>
    </div>

    {{-- Summary cards --}}
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <small class="text-muted">Open Incidents</small>
                    <h2 class="fw-bold text-danger mb-0">{{ $openCount }}</h2>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <small class="text-muted">Resolved</small>
                    <h2 class="fw-bold text-success mb-0">{{ $resolvedCount }}</h2>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <small class="text-muted">Total Recorded</small>
                    <h2 class="fw-bold mb-0">{{ $incidents->total() }}</h2>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <small class="text-muted">Notification Backup</small>
                    <span class="d-inline-block mt-2 badge bg-success">Active</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.super-admin.email-incidents.index') }}" class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        @foreach (\App\Models\EmailIncident::STATUSES as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Email Type</label>
                    <input type="text" name="email_type" class="form-control" value="{{ request('email_type') }}" placeholder="e.g. OTP, Password Reset">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Incidents table --}}
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Email Type</th>
                        <th>Related Record</th>
                        <th>Status</th>
                        <th>Occurred</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($incidents as $incident)
                        <tr>
                            <td>{{ $incident->incident_id }}</td>
                            <td>{{ $incident->email_type ?: '—' }}</td>
                            <td>
                                @if ($incident->related_record_type || $incident->related_record_id)
                                    {{ $incident->related_record_type ?: 'System' }}
                                    @if ($incident->related_record_id)
                                        <span class="text-muted">#{{ $incident->related_record_id }}</span>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $badge = match ($incident->status) {
                                        'failed' => 'danger',
                                        'acknowledged' => 'warning',
                                        'resolved' => 'success',
                                        default => 'secondary',
                                    };
                                @endphp
                                <span class="badge bg-{{ $badge }}">{{ ucfirst($incident->status) }}</span>
                            </td>
                            <td>{{ optional($incident->occurred_at)->format('M d, Y h:i A') }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.super-admin.email-incidents.show', $incident->incident_id) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No email incidents recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer bg-white">
            <div class="d-flex justify-content-center">
                {{ $incidents->links() }}
            </div>
        </div>
    </div>

</div>

@endsection
