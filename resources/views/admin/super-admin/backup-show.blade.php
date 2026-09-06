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
    <div>
        @if($backup->backup_type === 'full')
            <span class="badge bg-primary fs-6">{{ $backup->typeLabel() }}</span>
        @elseif($backup->backup_type === 'incremental')
            <span class="badge bg-info text-dark fs-6">{{ $backup->typeLabel() }}</span>
        @elseif($backup->backup_type === 'daily_snapshot')
            <span class="badge bg-secondary fs-6">{{ $backup->typeLabel() }}</span>
        @else
            <span class="badge bg-light text-dark fs-6">—</span>
        @endif
    </div>
</div>

<div class="card shadow-sm border-0 mb-3">
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
                <div>{{ $backup->displayDestination() }} <span class="text-muted small">(private)</span></div>
            </div>
            <div class="col-md-6">
                <span class="admin-filter-label">Parent / Base Backup</span>
                <div>
                    @if($backup->parent_backup_id)
                        <a href="{{ route('admin.super-admin.backups.show', $backup->parent_backup_id) }}">#{{ $backup->parent_backup_id }}</a>
                        @if($backup->parent)
                            <span class="text-muted small">({{ $backup->parent->backup_name }})</span>
                        @endif
                    @else
                        —
                    @endif
                </div>
            </div>
            <div class="col-md-3">
                <span class="admin-filter-label">Backup Size</span>
                <div>{{ $backup->backup_size !== null ? number_format($backup->backup_size / 1048576, 2) . ' MB' : '-' }}</div>
            </div>
            <div class="col-md-3">
                <span class="admin-filter-label">File Count</span>
                <div>{{ $backup->file_count !== null ? number_format($backup->file_count) : '-' }}</div>
            </div>
            <div class="col-md-6">
                <span class="admin-filter-label">Checksum / Integrity</span>
                <div>
                    @if($backup->isChecksumVerified())
                        <span class="badge bg-success">SHA-256 VERIFIED</span>
                        <div class="text-muted small text-break mt-1">{{ $backup->checksum_algorithm }}: {{ $backup->checksum }}</div>
                    @elseif($backup->checksum)
                        <div class="text-muted small text-break">{{ $backup->checksum_algorithm }}: {{ $backup->checksum }}</div>
                        <span class="badge bg-secondary">Not verified</span>
                    @else
                        —
                    @endif
                </div>
            </div>
            <div class="col-md-6">
                <span class="admin-filter-label">Verification Time</span>
                <div>{{ optional($backup->verified_at)->format('M d, Y h:i A') ?: '-' }}</div>
            </div>
            @if($backup->storage_path)
                <div class="col-12">
                    <span class="admin-filter-label">Storage Object (private)</span>
                    <div class="text-muted small text-break">{{ $backup->storage_path }}</div>
                </div>
            @endif
            @if($backup->binlog_file_start || $backup->binlog_file_end)
                <div class="col-md-6">
                    <span class="admin-filter-label">Binlog Range (incremental)</span>
                    <div class="text-muted small">
                        {{ $backup->binlog_file_start ?: '-' }} @ {{ $backup->binlog_position_start ?? '-' }}
                        → {{ $backup->binlog_file_end ?: '-' }} @ {{ $backup->binlog_position_end ?? '-' }}
                    </div>
                </div>
            @endif
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
