@extends('layouts.admin')

@section('title', 'Audit Logs')

@section('content')
<div class="page-header-modern d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h2 class="fw-bold mb-1">Audit Logs</h2>
        <p class="text-muted mb-0">Review administrative actions and security activity.</p>
    </div>
    <a href="{{ route('admin.dashboard') }}" class="admin-btn admin-btn-neutral">
        <i class="bi bi-arrow-left" aria-hidden="true"></i>
        Back to Dashboard
    </a>
</div>

@if($isSuperAdmin ?? false)
    <div class="mb-3 d-flex justify-content-end">
        <a href="{{ route('admin.audit-logs.export', request()->query()) }}" class="admin-btn admin-btn-secondary">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>
@endif

@if(! ($isSuperAdmin ?? false))
    <div class="alert alert-info small mb-3">
        <i class="bi bi-shield-lock me-1"></i>
        You are viewing <strong>operational audit activity</strong>. Privileged administrative activity
        (maintenance, backup, system configuration, and other Super Admin actions) is not shown here.
    </div>
@endif

<div class="card shadow-sm border-0 mb-4 admin-filter-card">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.audit-logs') }}" class="row g-3 admin-filter-form">
            <div class="col-md-2">
                <label for="from" class="admin-filter-label">From</label>
                <input id="from" type="date" name="from" value="{{ request('from') }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label for="to" class="admin-filter-label">To</label>
                <input id="to" type="date" name="to" value="{{ request('to') }}" class="form-control">
            </div>
            @if($isSuperAdmin ?? false)
            <div class="col-md-2">
                <label for="actor_type" class="admin-filter-label">Actor Type</label>
                <select id="actor_type" name="actor_type" class="form-select">
                    <option value="">All</option>
                    @foreach($actorTypes as $actorType)
                        <option value="{{ $actorType }}" @selected(request('actor_type') === $actorType)>{{ $actorType }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-md-2">
                <label for="module" class="admin-filter-label">Module</label>
                <select id="module" name="module" class="form-select">
                    <option value="">All</option>
                    @foreach($modules as $module)
                        <option value="{{ $module }}" @selected(request('module') === $module)>{{ $module }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="keyword" class="admin-filter-label">Keyword</label>
                <input id="keyword" name="keyword" value="{{ request('keyword') }}" class="form-control" placeholder="Search action or description">
            </div>
            <div class="col-md-1 d-flex align-items-end gap-2">
                <button class="admin-btn admin-btn-primary w-100" type="submit" title="Filter"><i class="bi bi-funnel"></i></button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <span class="text-muted small">
            Showing {{ $logs->firstItem() ?? 0 }}–{{ $logs->lastItem() ?? 0 }} of {{ $logs->total() }} audit entries
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Actor</th>
                        <th>Description</th>
                        <th>Recorded</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td><strong>{{ $log->action }}</strong></td>
                            <td>{{ $log->module ?? '-' }}</td>
                            <td>{{ $log->actor_type ?? 'System' }} #{{ $log->actor_id ?? '-' }}</td>
                            <td>{{ $log->description ?? '-' }}</td>
                            <td>{{ optional($log->created_at)->format('M d, Y h:i A') }}</td>
                        </tr>
                    @empty
                        <x-admin.empty-state
                            :colspan="5"
                            icon="bi-journal-x"
                            title="No audit activity found"
                            message="No audit entries match your filters."
                            :clearUrl="route('admin.audit-logs')" />
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
