@extends('layouts.admin')

@section('title', isset($record) ? 'Edit Maintenance Record' : 'New Maintenance Record')

@section('content')

<div class="container-fluid">

    @php
        $record = $record ?? null;
        $checklist = $record?->checklist_json ?? [];
    @endphp

    <x-admin.page-header title="{{ isset($record) ? 'Edit Maintenance Record' : 'New Maintenance Record' }}" description="Record quarterly maintenance performed (Risk No. 6).">
        <x-slot name="actions">
            <a href="{{ route('admin.super-admin.maintenance-records.index') }}" class="admin-btn admin-btn-neutral">
                <i class="bi bi-arrow-left"></i> Back to History
            </a>
        </x-slot>
    </x-admin.page-header>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        method="POST"
        action="{{ isset($record) ? route('admin.super-admin.maintenance-records.update', $record->maintenance_id) : route('admin.super-admin.maintenance-records.store') }}"
        class="row g-4">
        @csrf
        @if (isset($record))
            @method('PUT')
        @endif

        {{-- Core record --}}
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white"><strong>Maintenance Details</strong></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Maintenance Date</label>
                        <input type="date" name="maintenance_date" class="form-control"
                               value="{{ old('maintenance_date', isset($record) ? $record->maintenance_date->toDateString() : now()->toDateString()) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Maintenance Type</label>
                        <select name="maintenance_type" class="form-select" required>
                            @foreach (\App\Models\MaintenanceRecord::TYPES as $type)
                                <option value="{{ $type }}" @selected(old('maintenance_type', $record?->maintenance_type) === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="What was performed...">{{ old('description', $record?->description) }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Overall Status</label>
                        <select name="overall_status" class="form-select" required>
                            @foreach (\App\Models\MaintenanceRecord::STATUSES as $status)
                                <option value="{{ $status }}" @selected(old('overall_status', $record?->overall_status) === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- Statuses --}}
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white"><strong>Status Checks</strong></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Dependency / Framework Status</label>
                        <input type="text" name="dependency_status" class="form-control" value="{{ old('dependency_status', $record?->dependency_status) }}" placeholder="e.g. Up to date / Updates available">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">PHP / Runtime Status</label>
                        <input type="text" name="php_runtime_status" class="form-control" value="{{ old('php_runtime_status', $record?->php_runtime_status) }}" placeholder="e.g. PHP 8.2 OK">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Server / Container / OS Status</label>
                        <input type="text" name="server_status" class="form-control" value="{{ old('server_status', $record?->server_status) }}" placeholder="e.g. OS patched">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Testing Status</label>
                        <input type="text" name="testing_status" class="form-control" value="{{ old('testing_status', $record?->testing_status) }}" placeholder="e.g. All tests passed">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old('notes', $record?->notes) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- Checklist --}}
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white"><strong>Maintenance Checklist</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach (\App\Models\MaintenanceRecord::CHECKLIST_ITEMS as $key => $label)
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        id="checklist_{{ $key }}"
                                        name="checklist[{{ $key }}]"
                                        value="1"
                                        @checked(old("checklist.$key", $checklist[$key] ?? false))>
                                    <label class="form-check-label" for="checklist_{{ $key }}">{{ $label }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Post-maintenance report --}}
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white"><strong>Post-Maintenance Report</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Updates Identified</label>
                            <textarea name="updates_identified" class="form-control" rows="3">{{ old('updates_identified', $record?->updates_identified) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Updates Applied</label>
                            <textarea name="updates_applied" class="form-control" rows="3">{{ old('updates_applied', $record?->updates_applied) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tests Performed</label>
                            <textarea name="tests_performed" class="form-control" rows="3">{{ old('tests_performed', $record?->tests_performed) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Test Results</label>
                            <textarea name="test_results" class="form-control" rows="3">{{ old('test_results', $record?->test_results) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Problems Found</label>
                            <textarea name="problems_found" class="form-control" rows="3">{{ old('problems_found', $record?->problems_found) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Corrective Actions</label>
                            <textarea name="corrective_actions" class="form-control" rows="3">{{ old('corrective_actions', $record?->corrective_actions) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <button type="submit" class="admin-btn admin-btn-primary">
                <i class="bi bi-check2-circle"></i> {{ isset($record) ? 'Update Maintenance Record' : 'Save Maintenance Record' }}
            </button>
        </div>
    </form>

</div>

@endsection
