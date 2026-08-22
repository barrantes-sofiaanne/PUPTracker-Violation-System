@extends('layouts.admin')

@section('title', 'User Management History')

@section('content')
<div class="page-header-modern d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h2 class="fw-bold mb-1">User Management History</h2>
        <p class="text-muted mb-0">Review student and account changes performed by administrators.</p>
    </div>
    <a href="{{ route('admin.dashboard') }}" class="admin-btn admin-btn-neutral">
        <i class="bi bi-arrow-left" aria-hidden="true"></i>
        Back to Dashboard
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Action</th>
                        <th>User</th>
                        <th>Performed By</th>
                        <th>Recorded</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($history as $entry)
                        <tr>
                            <td><strong>{{ $entry->action }}</strong></td>
                            <td>{{ $entry->user_id ?? '-' }}</td>
                            <td>{{ $entry->performed_by ?? '-' }}</td>
                            <td>{{ optional($entry->created_at)->format('M d, Y h:i A') }}</td>
                        </tr>
                    @empty
                        <x-admin.empty-state
                            :colspan="4"
                            icon="bi-person-x"
                            title="No history found"
                            message="No history entries have been recorded yet."
                            :clearUrl="null" />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-4 d-flex justify-content-center">
    {{ $history->links() }}
</div>
@endsection
