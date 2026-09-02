@extends('layouts.security')

@section('title', 'Sanction Requests')

@section('content')
<div class="container-fluid py-1">
    <div class="page-header-modern d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">Sanction Requests</h2>
            <p class="text-muted mb-0">Review student requests for disciplinary sanctions and act on them.</p>
        </div>
        <a href="{{ route('security.dashboard') }}" class="admin-btn admin-btn-neutral">
            <i class="bi bi-speedometer2 me-1"></i>Back to Dashboard
        </a>
    </div>

    <div class="card portal-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 portal-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Requested Violation</th>
                            <th>Requested At</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $request)
                            <tr>
                                <td>
                                    <strong>{{ optional($request->student)->student_number }}</strong>
                                    <div class="text-muted small">{{ optional($request->student)->first_name }} {{ optional($request->student)->last_name }}</div>
                                </td>
                                <td>{{ optional($request->violationType)->violation_type ?? 'Unknown' }}</td>
                                <td>{{ optional($request->created_at)->format('M d, Y h:i A') }}</td>
                                <td class="text-end">
                                    <form action="{{ route('security.sanction-requests.approve', $request) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm portal-btn me-1">
                                            <i class="bi bi-check-circle me-1"></i>Approve
                                        </button>
                                    </form>
                                    <form action="{{ route('security.sanction-requests.decline', $request) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm portal-btn-outline">
                                            <i class="bi bi-x-circle me-1"></i>Decline
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">No pending sanction requests.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex justify-content-center">
        {{ $requests->links() }}
    </div>
</div>
@endsection
