@extends('layouts.admin')

@section('title', 'User Management History')

@section('content')
<div class="page-header-modern d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h2 class="fw-bold mb-1">User Management History</h2>
        <p class="text-muted mb-0">Review activity associated with a specific user account.</p>
    </div>
    <a href="{{ route('admin.dashboard') }}" class="admin-btn admin-btn-neutral">
        <i class="bi bi-arrow-left" aria-hidden="true"></i>
        Back to Dashboard
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <span class="text-muted small">
            Showing {{ $history->firstItem() ?? 0 }}–{{ $history->lastItem() ?? 0 }} of {{ $history->total() }} history entries
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Action</th>
                        <th>User Account</th>
                        <th>Performed By</th>
                        <th>Changes</th>
                        <th>Recorded</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($history as $entry)
                        <tr>
                            <td><strong>{{ $entry->action }}</strong></td>
                            <td>{{ \App\Http\Controllers\Admin\UserManagementHistoryController::resolveUserIdLabel($entry->user_id) }}</td>
                            <td>{{ \App\Http\Controllers\Admin\UserManagementHistoryController::resolvePerformedByLabel($entry->performed_by) }}</td>
                            <td>
                                @if(! empty($entry->changes) && is_array($entry->changes))
                                    <ul class="mb-0 ps-3 change-list">
                                        @foreach($entry->changes as $change)
                                            <li>
                                                <strong>{{ $change['field'] ?? 'Field' }}</strong>:
                                                from <span class="text-danger text-decoration-line-through">{{ $change['from'] ?? '' }}</span>
                                                to <span class="text-success">{{ $change['to'] ?? '' }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td>{{ optional($entry->created_at)->format('M d, Y h:i A') }}</td>
                        </tr>
                    @empty
                        <x-admin.empty-state
                            :colspan="5"
                            icon="bi-person-x"
                            title="No user activity found"
                            message="No account activity has been recorded for any user yet."
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
