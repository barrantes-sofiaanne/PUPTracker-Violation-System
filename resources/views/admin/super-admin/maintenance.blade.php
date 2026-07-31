@extends('layouts.admin')

@section('title', 'Super Admin Maintenance Configuration')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h2 class="mb-1">Maintenance Configuration</h2>
        <p class="text-muted mb-0">Control application availability during maintenance windows.</p>
    </div>
    <a href="{{ route('admin.super-admin.dashboard') }}" class="btn btn-outline-secondary">Back to Super Admin</a>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <div class="mb-4">
            <h6 class="text-muted mb-2">Current Status</h6>
            <span class="badge {{ $maintenanceStatus ? 'bg-danger' : 'bg-success' }} px-3 py-2">
                {{ $maintenanceStatus ? 'Maintenance Mode Enabled' : 'Maintenance Mode Disabled' }}
            </span>
        </div>

        <form action="{{ route('admin.super-admin.maintenance.update') }}" method="POST" class="d-flex flex-wrap gap-2">
            @csrf
            <button
                name="action"
                value="enable"
                type="submit"
                class="btn btn-danger"
                @disabled($maintenanceStatus)
            >
                Enable Maintenance Mode
            </button>

            <button
                name="action"
                value="disable"
                type="submit"
                class="btn btn-success"
                @disabled(!$maintenanceStatus)
            >
                Disable Maintenance Mode
            </button>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <h6 class="mb-2">Latest Maintenance Action</h6>
        @if($latestMaintenanceLog)
            <p class="mb-1"><strong>{{ $latestMaintenanceLog->action }}</strong></p>
            <p class="text-muted mb-0">
                {{ $latestMaintenanceLog->description }}
                ({{ optional($latestMaintenanceLog->created_at)->format('M d, Y h:i A') }})
            </p>
        @else
            <p class="text-muted mb-0">No maintenance actions have been recorded yet.</p>
        @endif
    </div>
</div>
@endsection
