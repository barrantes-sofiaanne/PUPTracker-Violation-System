@extends('layouts.admin')

@section('title', 'Admin Settings')

@section('content')

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3>Settings</h3>
            <p class="text-muted">Manage your administrator account and application preferences.</p>
        </div>
    </div>

    <div class="row gy-4">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Account Details</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" value="{{ optional($admin)->name ?? 'Administrator' }}" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" value="{{ optional($admin)->email ?? '-' }}" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <input type="text" class="form-control" value="Administrator" readonly>
                    </div>
                    <div class="alert alert-info mb-0">
                        The system currently keeps administrator profile updates in the backend. Contact your system administrator to request account changes.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">System Preferences</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">The settings shown here are informational and can be configured by the application administrator.</p>
                    <div class="d-flex gap-3 flex-wrap">
                        <div class="badge bg-secondary px-3 py-2">Theme: Light</div>
                        <div class="badge bg-secondary px-3 py-2">Notifications: Enabled</div>
                        <div class="badge bg-secondary px-3 py-2">Version: 2.0.0</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

@endsection
