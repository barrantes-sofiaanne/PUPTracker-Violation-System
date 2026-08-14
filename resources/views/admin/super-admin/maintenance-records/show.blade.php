@extends('layouts.admin')

@section('title', 'Maintenance Record #' . $record->maintenance_id)

@section('content')

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h3 class="mb-1">Maintenance Record #{{ $record->maintenance_id }}</h3>
            <p class="text-muted mb-0">
                {{ $record->maintenance_type }} · {{ $record->maintenance_date?->format('M d, Y') }}
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.super-admin.maintenance-records.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <a href="{{ route('admin.super-admin.maintenance-records.edit', $record->maintenance_id) }}" class="btn btn-outline-primary">
                <i class="bi bi-pencil"></i> Edit
            </a>
            <form action="{{ route('admin.super-admin.maintenance-records.destroy', $record->maintenance_id) }}" method="POST"
                  onsubmit="return confirm('Delete this maintenance record?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger">
                    <i class="bi bi-trash"></i> Delete
                </button>
            </form>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @php
        $badge = match ($record->overall_status) {
            'Completed' => 'success',
            'Completed with Issues' => 'warning',
            'Failed' => 'danger',
            default => 'secondary',
        };
    @endphp

    {{-- Header --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <small class="text-muted d-block">Date</small>
                    <strong>{{ $record->maintenance_date?->format('M d, Y') }}</strong>
                </div>
                <div class="col-md-3">
                    <small class="text-muted d-block">Type</small>
                    <strong>{{ $record->maintenance_type }}</strong>
                </div>
                <div class="col-md-3">
                    <small class="text-muted d-block">Performed By</small>
                    <strong>{{ $record->performedBy?->email ?? '—' }}</strong>
                </div>
                <div class="col-md-3">
                    <small class="text-muted d-block">Overall Status</small>
                    <span class="badge bg-{{ $badge }}">{{ $record->overall_status }}</span>
                </div>
            </div>
            @if ($record->description)
                <hr>
                <p class="mb-0">{{ $record->description }}</p>
            @endif
        </div>
    </div>

    {{-- Status checks --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white"><strong>Status Checks</strong></div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Dependency / Framework</span>
                            <strong>{{ $record->dependency_status ?: '—' }}</strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">PHP / Runtime</span>
                            <strong>{{ $record->php_runtime_status ?: '—' }}</strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Server / Container / OS</span>
                            <strong>{{ $record->server_status ?: '—' }}</strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Testing</span>
                            <strong>{{ $record->testing_status ?: '—' }}</strong>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Checklist --}}
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white"><strong>Maintenance Checklist</strong></div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        @foreach (\App\Models\MaintenanceRecord::CHECKLIST_ITEMS as $key => $label)
                            <li class="list-group-item d-flex justify-content-between">
                                <span>{{ $label }}</span>
                                @if (($record->checklist_json[$key] ?? false))
                                    <i class="bi bi-check-circle-fill text-success"></i>
                                @else
                                    <i class="bi bi-circle text-muted"></i>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- Post-maintenance report --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white"><strong>Post-Maintenance Report</strong></div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-6">
                    <h6 class="text-muted">Updates Identified</h6>
                    <p>{{ $record->updates_identified ?: '—' }}</p>
                </div>
                <div class="col-md-6">
                    <h6 class="text-muted">Updates Applied</h6>
                    <p>{{ $record->updates_applied ?: '—' }}</p>
                </div>
                <div class="col-md-6">
                    <h6 class="text-muted">Tests Performed</h6>
                    <p>{{ $record->tests_performed ?: '—' }}</p>
                </div>
                <div class="col-md-6">
                    <h6 class="text-muted">Test Results</h6>
                    <p>{{ $record->test_results ?: '—' }}</p>
                </div>
                <div class="col-md-6">
                    <h6 class="text-muted">Problems Found</h6>
                    <p>{{ $record->problems_found ?: '—' }}</p>
                </div>
                <div class="col-md-6">
                    <h6 class="text-muted">Corrective Actions</h6>
                    <p>{{ $record->corrective_actions ?: '—' }}</p>
                </div>
            </div>
            @if ($record->notes)
                <hr>
                <h6 class="text-muted">Notes</h6>
                <p class="mb-0">{{ $record->notes }}</p>
            @endif
        </div>
    </div>

</div>

@endsection
