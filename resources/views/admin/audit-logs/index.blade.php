@extends('layouts.admin')

@section('title', 'Audit Logs')

@section('content')
<div class="page-header-modern d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h2 class="fw-bold mb-1">Audit Logs</h2>
        <p class="text-muted mb-0">Review system-wide administrative actions and security activity.</p>
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
                        <th>Module</th>
                        <th>Actor</th>
                        <th>Recorded</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td><strong>{{ $log->action }}</strong></td>
                            <td>{{ $log->module ?? '-' }}</td>
                            <td>{{ $log->actor_type ?? 'System' }} #{{ $log->actor_id ?? '-' }}</td>
                            <td>{{ optional($log->created_at)->format('M d, Y h:i A') }}</td>
                        </tr>
                    @empty
                        <x-admin.empty-state
                            :colspan="4"
                            icon="bi-journal-x"
                            title="No audit activity found"
                            message="No system-wide administrative or security actions have been recorded yet."
                            :clearUrl="null" />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-4 d-flex justify-content-center">
    {{ $logs->links() }}
</div>
@endsection
