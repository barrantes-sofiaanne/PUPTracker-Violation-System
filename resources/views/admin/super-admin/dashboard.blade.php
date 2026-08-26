@extends('layouts.admin')

@section('title', 'Super Admin Dashboard')

@section('content')
<div class="page-header-modern d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h2 class="fw-bold mb-1">Super Admin Dashboard</h2>
        <p class="text-muted mb-0">Exclusive controls for the IT Administrator.</p>
    </div>
</div>

@php
    $maintenanceModeOff = ! $maintenanceStatus;
@endphp

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h6 class="text-muted mb-2">Total Audit Entries</h6>
                <h3 class="mb-0">{{ number_format($totalLogs) }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h6 class="text-muted mb-2">Today's Audit Entries</h6>
                <h3 class="mb-0">{{ number_format($todayLogs) }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h6 class="text-muted mb-2">Maintenance Mode</h6>
                <h3 class="mb-0 {{ $maintenanceModeOff ? 'text-success' : 'text-danger' }}">
                    {{ $maintenanceModeOff ? 'OFF' : 'ON' }}
                </h3>
                <p class="text-muted mb-0 mt-1 small">
                    {{ $maintenanceModeOff ? 'System is currently accessible' : 'System is in maintenance' }}
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h5>Database Backup</h5>
                <p class="mb-1">
                    <span class="badge {{ ($backupHealthy ?? false) ? 'bg-success' : 'bg-warning text-dark' }}">
                        {{ ($backupHealthy ?? false) ? '✓ Healthy' : 'No recent record' }}
                    </span>
                </p>
                <p class="text-muted mb-1">
                    Last backup:
                    <strong>
                        {{ $latestBackup?->completed_at?->format('M d, Y h:i A') ?: 'No record yet' }}
                    </strong>
                </p>
                @if($latestBackup)
                    <p class="text-muted mb-1"><strong>{{ $latestBackup->backup_name }}</strong></p>
                @endif
                <p class="text-muted mb-3">Destination: Cloudflare R2</p>
                <a href="{{ route('admin.super-admin.backups.index') }}" class="admin-btn admin-btn-primary admin-btn-sm">View Backup History</a>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h5>Authenticator MFA</h5>
                <p class="text-muted mb-1">
                    Enforcement:
                    <strong>{{ $mfaEnabledAdmins > 0 ? 'Enabled' : 'Not enforced' }}</strong>
                </p>
                <p class="text-muted mb-3">
                    Protected administrators: <strong>{{ $mfaEnabledAdmins }}/{{ $totalAdmins }}</strong>
                </p>
                <a href="{{ route('totp.setup', ['guard' => 'admin']) }}" class="admin-btn admin-btn-primary admin-btn-sm">Manage MFA</a>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h5>Audit Logs</h5>
                <p class="text-muted mb-1"><strong>{{ number_format($totalLogs) }}</strong> total entries</p>
                <p class="text-muted mb-3"><strong>{{ number_format($todayLogs) }}</strong> recorded today</p>
                <a href="{{ route('admin.audit-logs') }}" class="admin-btn admin-btn-primary admin-btn-sm">Open Audit Logs</a>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h5>Maintenance History</h5>
                @if($latestMaintenance)
                    <p class="text-muted mb-1">
                        Last maintenance: <strong>{{ $latestMaintenance->maintenance_date->format('M d, Y') }}</strong>
                    </p>
                    <p class="text-muted mb-1">
                        Next review: <strong>{{ $maintenanceNextReview?->format('M d, Y') }}</strong>
                    </p>
                    <p class="text-muted mb-3">
                        Status: <strong>{{ $maintenanceUpToDate ? 'Up to date' : 'Review due' }}</strong>
                    </p>
                @else
                    <p class="text-muted mb-1">Last maintenance: <strong>No record yet</strong></p>
                    <p class="text-muted mb-3">Status: <strong>Pending</strong></p>
                @endif
                <a href="{{ route('admin.super-admin.maintenance-records.index') }}" class="admin-btn admin-btn-primary admin-btn-sm">View Maintenance History</a>
            </div>
        </div>
    </div>
</div>

@php
    $readableAction = match (strtolower((string) $latestLog?->action)) {
        'mfa.verify.success' => 'MFA verification successful',
        'mfa.verify.failed' => 'MFA verification failed',
        'mfa.challenge.started' => 'MFA challenge started',
        'mfa.method.selected' => 'MFA method selected',
        default => $latestLog?->action ?: 'No activity yet',
    };
@endphp
@if($latestLog)
    <div class="alert alert-light border mt-4 mb-0">
        <strong>Latest Activity:</strong>
        {{ $readableAction }}
        <span class="text-muted">
            &middot; {{ $latestLog->module ?? 'General' }} &middot;
            {{ optional($latestLog->created_at)->format('M d, Y h:i A') }}
        </span>
    </div>
@endif
@endsection
