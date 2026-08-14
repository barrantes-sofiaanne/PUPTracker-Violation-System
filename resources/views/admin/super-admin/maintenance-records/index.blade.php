@extends('layouts.admin')

@section('title', 'Maintenance History')

@section('content')

<div class="container-fluid">

    <div class="page-header-modern d-flex justify-content-between align-items-center flex-wrap mb-4">
        <div>
            <h2 class="fw-bold mb-1">Maintenance History</h2>
            <p class="mb-0">Quarterly maintenance records for dependencies, framework, PHP/runtime, and server/OS (Risk No. 6).</p>
        </div>
        <a href="{{ route('admin.super-admin.maintenance-records.create') }}" class="btn btn-success">
            <i class="bi bi-plus-lg"></i> New Maintenance Record
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Filters --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.super-admin.maintenance-records.index') }}" class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Maintenance Type</label>
                    <select name="maintenance_type" class="form-select">
                        <option value="">All Types</option>
                        @foreach (\App\Models\MaintenanceRecord::TYPES as $type)
                            <option value="{{ $type }}" @selected(request('maintenance_type') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Overall Status</label>
                    <select name="overall_status" class="form-select">
                        <option value="">All Statuses</option>
                        @foreach (\App\Models\MaintenanceRecord::STATUSES as $status)
                            <option value="{{ $status }}" @selected(request('overall_status') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
                </div>
            </form>
        </div>
    </div>

    {{-- History table --}}
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Performed By</th>
                        <th>Status</th>
                        <th>Summary</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr>
                            <td>{{ $record->maintenance_date?->format('M d, Y') }}</td>
                            <td>{{ $record->maintenance_type }}</td>
                            <td>{{ $record->performedBy?->email ?? '—' }}</td>
                            <td>
                                @php
                                    $badge = match ($record->overall_status) {
                                        'Completed' => 'success',
                                        'Completed with Issues' => 'warning',
                                        'Failed' => 'danger',
                                        default => 'secondary',
                                    };
                                @endphp
                                <span class="badge bg-{{ $badge }}">{{ $record->overall_status }}</span>
                            </td>
                            <td class="text-truncate" style="max-width: 300px;">{{ \Illuminate\Support\Str::limit($record->description ?? $record->notes ?? '—', 80) }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.super-admin.maintenance-records.show', $record->maintenance_id) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No maintenance records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($records->hasPages())
            <div class="card-footer bg-white">
                {{ $records->links() }}
            </div>
        @endif
    </div>

</div>

@endsection
