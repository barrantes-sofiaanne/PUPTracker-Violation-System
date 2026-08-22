@extends('layouts.admin')

@section('title', 'Super Admin Audit Trail')

@section('content')
<div class="page-header-modern d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h2 class="fw-bold mb-1">Audit Trail</h2>
        <p class="text-muted mb-0">Search and review administrative and security actions across the platform.</p>
    </div>
    <a href="{{ route('admin.super-admin.dashboard') }}" class="admin-btn admin-btn-neutral">
        <i class="bi bi-arrow-left"></i>
        Back to Super Admin
    </a>
</div>

<div class="card shadow-sm border-0 mb-4 admin-filter-card">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.super-admin.audit-trail') }}" class="row g-3 admin-filter-form">
            <div class="col-md-3">
                <label for="actor_type" class="admin-filter-label">Actor Type</label>
                <select id="actor_type" name="actor_type" class="form-select">
                    <option value="">All</option>
                    @foreach($actorTypes as $actorType)
                        <option value="{{ $actorType }}" @selected(request('actor_type') === $actorType)>{{ $actorType }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="module" class="admin-filter-label">Module</label>
                <select id="module" name="module" class="form-select">
                    <option value="">All</option>
                    @foreach($modules as $module)
                        <option value="{{ $module }}" @selected(request('module') === $module)>{{ $module }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label for="keyword" class="admin-filter-label">Keyword</label>
                <input id="keyword" name="keyword" value="{{ request('keyword') }}" class="form-control" placeholder="Search action or description">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="admin-btn admin-btn-primary w-100" type="submit">Filter</button>
            </div>
        </form>
    </div>
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
                            icon="bi-clipboard-x"
                            title="No audit entries found"
                            message="No audit entries were found for your selected filters."
                            :clearUrl="route('admin.super-admin.audit-trail')" />
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
