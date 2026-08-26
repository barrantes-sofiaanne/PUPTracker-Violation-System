@extends('layouts.admin')

@section('title', 'Backup Details')

@section('content')
<div class="admin-breadcrumb-standalone mb-3">
    <a href="{{ route('admin.super-admin.backups.index') }}" class="text-decoration-none">
        <i class="bi bi-arrow-left me-1"></i>Backup History
    </a>
</div>

<div class="page-header-modern mb-3">
    <div>
        <h3 class="mb-1">Backup Details</h3>
        <p class="mb-0">{{ $backup->backup_name }}</p>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-6">
                <span class="admin-filter-label">Backup</span>
                <div class="fw-semibold">{{ $backup->backup_name }}</div>
            </div>
            <div class="col-md-6">
                <span class="admin-filter-label">Status</span>
                <div>
                    @if($backup->isSuccessful())
                        <span class="badge bg-success">✓ Successful</span>
                    @else
                        <span class="badge bg-danger">✗ Failed</span>
                    @endif
                </div>
            </div>
            <div class="col-md-6">
                <span class="admin-filter-label">Started</span>
                <div>{{ optional($backup->started_at)->format('M d, Y h:i A') ?: '-' }}</div>
            </div>
            <div class="col-md-6">
                <span class="admin-filter-label">Completed</span>
                <div>{{ optional($backup->completed_at)->format('M d, Y h:i A') ?: '-' }}</div>
            </div>
            <div class="col-md-6">
                <span class="admin-filter-label">Destination</span>
                <div>{{ $backup->destination ?: 'Cloudflare R2' }}</div>
            </div>
            <div class="col-md-3">
                <span class="admin-filter-label">Backup Size</span>
                <div>{{ $backup->backup_size !== null ? number_format($backup->backup_size / 1048576, 2) . ' MB' : '-' }}</div>
            </div>
            <div class="col-md-3">
                <span class="admin-filter-label">File Count</span>
                <div>{{ $backup->file_count !== null ? number_format($backup->file_count) : '-' }}</div>
            </div>
            @if($backup->error_message)
                <div class="col-12">
                    <span class="admin-filter-label">Error Message</span>
                    <div class="border rounded-3 p-3 bg-light text-danger">{{ $backup->error_message }}</div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
