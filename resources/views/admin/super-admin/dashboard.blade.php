@extends('layouts.admin')

@section('title', 'Super Admin Dashboard')

@section('content')
<div class="page-header-modern d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h2 class="fw-bold mb-1">Super Admin Dashboard</h2>
        <p class="text-muted mb-0">Exclusive controls for the IT Administrator.</p>
    </div>
</div>

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
                <h6 class="text-muted mb-2">Maintenance Status</h6>
                <h3 class="mb-0 {{ $maintenanceStatus ? 'text-danger' : 'text-success' }}">
                    {{ $maintenanceStatus ? 'Enabled' : 'Disabled' }}
                </h3>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h5>Authenticator MFA</h5>
                <p class="text-muted">
                    Status:
                    <strong>{{ auth('admin')->user()?->mfa_totp_enabled ? 'Enabled' : 'Not Enabled' }}</strong>
                </p>
                <a href="{{ route('totp.setup', ['guard' => 'admin']) }}" class="admin-btn admin-btn-primary admin-btn-sm">
                    {{ auth('admin')->user()?->mfa_totp_enabled ? 'Manage Authenticator Setup' : 'Enable Authenticator App' }}
                </a>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h5>Audit Logs</h5>
                <p class="text-muted">Monitor and search all recorded system activity.</p>
                <a href="{{ route('admin.audit-logs') }}" class="admin-btn admin-btn-primary admin-btn-sm">Open Audit Logs</a>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h5>Maintenance Settings</h5>
                <p class="text-muted">Toggle system maintenance mode when needed.</p>
                <a href="{{ route('admin.super-admin.maintenance') }}" class="admin-btn admin-btn-primary admin-btn-sm">Open Maintenance Settings</a>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h5>Maintenance History</h5>
                <p class="text-muted">Record and review quarterly maintenance activities (Risk No. 6).</p>
                <a href="{{ route('admin.super-admin.maintenance-records.index') }}" class="admin-btn admin-btn-primary admin-btn-sm">Open Maintenance History</a>
            </div>
        </div>
    </div>
</div>

@if($latestLog)
    <div class="alert alert-light border mt-4 mb-0">
        <strong>Latest Activity:</strong>
        {{ $latestLog->action }} on {{ $latestLog->module ?? 'General' }}
        <span class="text-muted">({{ optional($latestLog->created_at)->format('M d, Y h:i A') }})</span>
    </div>
@endif
@endsection
