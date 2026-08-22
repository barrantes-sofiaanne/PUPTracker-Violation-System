@extends('layouts.admin')

@section('title', 'Maintenance Record #' . $record->maintenance_id)

@section('content')

<div class="container-fluid">

    <x-admin.page-header title="Maintenance Record #{{ $record->maintenance_id }}" :description="$record->maintenance_type . ' · ' . optional($record->maintenance_date)->format('M d, Y')">
        <x-slot name="actions">
            <a href="{{ route('admin.super-admin.maintenance-records.index') }}" class="admin-btn admin-btn-neutral">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <a href="{{ route('admin.super-admin.maintenance-records.edit', $record->maintenance_id) }}" class="admin-btn admin-btn-view">
                <i class="bi bi-pencil"></i> Edit
            </a>
            <form action="{{ route('admin.super-admin.maintenance-records.destroy', $record->maintenance_id) }}" method="POST"
                  data-confirm="This will permanently delete the maintenance record. This action cannot be undone."
                  data-confirm-title="Delete maintenance record?"
                  data-confirm-confirm-label="Delete">
                @csrf
                @method('DELETE')
                <button type="submit" class="admin-btn admin-btn-danger">
                    <i class="bi bi-trash"></i> Delete
                </button>
            </form>
        </x-slot>
    </x-admin.page-header>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

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
                    @php
                        $statusKey = strtolower((string) $record->overall_status);
                    @endphp
                    @if (str_contains($statusKey, 'completed') && str_contains($statusKey, 'issue'))
                        <x-admin.badge status="warning" label="Completed with Issues" />
                    @elseif (str_contains($statusKey, 'completed'))
                        <x-admin.badge status="completed" />
                    @elseif ($statusKey === 'failed')
                        <x-admin.badge status="failed" />
                    @else
                        <x-admin.badge status="gray" :label="$record->overall_status" />
                    @endif
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
