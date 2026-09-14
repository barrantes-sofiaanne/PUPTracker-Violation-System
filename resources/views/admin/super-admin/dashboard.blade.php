@extends('layouts.admin')

@section('title', 'Super Admin Dashboard')

@section('content')
{{-- Standardized header — matches the Security module header (hero gradient
     + PHT clock). --}}
<x-admin.page-header
    title="Super Admin Dashboard"
    description="Exclusive controls for the IT Administrator." />

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
                    @if(($backupHealthy ?? false))
                        <span class="badge bg-success">✓ Healthy</span>
                    @else
                        <span class="badge bg-warning text-dark">⚠ Attention Required</span>
                    @endif
                </p>

                <p class="text-muted mb-1">
                    Last backup:
                    <strong>
                        {{ $lastSuccessfulBackup?->completed_at?->format('M d, Y h:i A') ?: 'No record yet' }}
                    </strong>
                </p>
                @if($lastSuccessfulBackup)
                    <p class="text-muted mb-1">
                        Last backup type: <strong>{{ $lastSuccessfulBackup->typeLabel() }}</strong>
                    </p>
                    <p class="text-muted mb-1">
                        Integrity:
                        @if($lastSuccessfulBackup->isChecksumVerified())
                            <span class="badge bg-success">SHA-256 VERIFIED</span>
                        @elseif($lastSuccessfulBackup->isSuccessful())
                            <span class="badge bg-secondary">Not verified</span>
                        @else
                            <span class="badge bg-danger">Failed</span>
                        @endif
                    </p>
                @endif

                <p class="text-muted mb-1">
                    Last successful full:
                    <strong>{{ $lastSuccessfulFull?->completed_at?->format('M d, Y h:i A') ?: 'None yet' }}</strong>
                </p>
                <p class="text-muted mb-1">
                    Next full backup:
                    <strong>
                        {{ $nextFullDue?->format('M d, Y') ?: '—' }}
                        @if($fullOverdue ?? false)
                            <span class="badge bg-warning text-dark">overdue</span>
                        @endif
                    </strong>
                </p>

                @if(($lastFailedBackup ?? null) && ! ($backupHealthy ?? false))
                    <p class="text-danger mb-1">
                        Last failed attempt:
                        <strong>{{ $lastFailedBackup->completed_at?->format('M d, Y h:i A') }}</strong>
                    </p>
                    @if($lastFailedBackup->error_message)
                        <p class="text-danger small mb-1">{{ \Illuminate\Support\Str::limit($lastFailedBackup->error_message, 120) }}</p>
                    @endif
                @endif

                <p class="text-muted mb-3">Destination: Cloudflare R2 (private)</p>
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
