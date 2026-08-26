@extends('layouts.admin')

@section('title', 'Database Backup History')

@section('content')
<div class="page-header-modern d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h2 class="fw-bold mb-1">Database Backup History</h2>
        <p class="text-muted mb-0">Review the outcome of Railway → MyDumper → Cloudflare R2 backups.</p>
    </div>
    <a href="{{ route('admin.super-admin.dashboard') }}" class="admin-btn admin-btn-neutral">
        <i class="bi bi-arrow-left"></i>
        Back to Super Admin
    </a>
</div>

<div class="card shadow-sm border-0 mb-4 admin-filter-card">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.super-admin.backups.index') }}" class="row g-3 admin-filter-form">
            <div class="col-md-3">
                <label for="status" class="admin-filter-label">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">All</option>
                    <option value="success" @selected(request('status') === 'success')>Success</option>
                    <option value="failed" @selected(request('status') === 'failed')>Failed</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="search" class="admin-filter-label">Search</label>
                <input id="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search backup name">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="admin-btn admin-btn-primary w-100" type="submit">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <span class="text-muted small">
            Showing {{ $backups->firstItem() ?? 0 }}–{{ $backups->lastItem() ?? 0 }} of {{ $backups->total() }} backup records
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Backup</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Destination</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($backups as $backup)
                        <tr>
                            <td><strong>{{ $backup->backup_name }}</strong></td>
                            <td>{{ optional($backup->completed_at)->format('M d, Y h:i A') ?: '-' }}</td>
                            <td>
                                @if($backup->isSuccessful())
                                    <span class="badge bg-success">✓ Success</span>
                                @else
                                    <span class="badge bg-danger">✗ Failed</span>
                                @endif
                            </td>
                            <td>{{ $backup->destination ?: 'Cloudflare R2' }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.super-admin.backups.show', $backup) }}" class="admin-btn admin-btn-view admin-btn-sm">
                                    <i class="bi bi-eye me-1"></i>View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty-state
                            :colspan="5"
                            icon="bi-database-x"
                            title="No backup records found"
                            message="Backup results will appear here once the Railway backup job reports its outcome."
                            :clearUrl="route('admin.super-admin.backups.index')" />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-4 d-flex justify-content-center">
    {{ $backups->links() }}
</div>
@endsection
