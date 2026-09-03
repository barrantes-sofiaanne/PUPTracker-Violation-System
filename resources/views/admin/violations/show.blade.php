@extends('layouts.admin')

@section('title', 'Student Record')

@section('content')
<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Admin</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.violations.index') }}" class="text-decoration-none">Violations</a></li>
            <li class="breadcrumb-item active" aria-current="page">Student Record</li>
        </ol>
    </nav>

    <div class="page-header-modern d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-0">Student Record</h3>
            <small class="text-muted">Review the student’s disciplinary status and history.</small>
        </div>
        <a href="{{ $backRoute ?? route('admin.violations.index') }}" class="admin-btn admin-btn-neutral">
            <i class="bi bi-arrow-left"></i>
            {{ $backLabel ?? 'Back to Results' }}
        </a>
    </div>

    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-white">
            <h5 class="mb-0">Student Information</h5>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-lg-8">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <small class="text-muted d-block">Student Name</small>
                            <div class="fw-semibold">{{ $student->formal_name ?: '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Student Number</small>
                            <div class="fw-semibold">{{ $student->student_number }}</div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Program</small>
                            <div>{{ $student->studentInfo?->program?->program_name ?? '-' }}</div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Year</small>
                            <div>{{ $student->studentInfo?->year?->year ?? '-' }}</div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Section</small>
                            <div>{{ $student->studentInfo?->section?->section_name ?? '-' }}</div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Current Status</small>
                            <div>{{ $student->studentInfo?->studentStatus?->status_name ?? '-' }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="border rounded-3 bg-light p-3 h-100">
                        <small class="text-muted d-block">Disciplinary Summary</small>
                        <div class="fw-bold text-danger mt-2">{{ $totalViolations }} total violations</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="text-muted small text-uppercase">Total Violations</div>
                    <div class="fs-3 fw-bold text-danger">{{ $totalViolations }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="text-muted small text-uppercase">Major Violations</div>
                    <div class="fs-3 fw-bold text-maroon">{{ $majorViolations }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="text-muted small text-uppercase">Total Sanctions</div>
                    <div class="fs-3 fw-bold text-warning">{{ $outstandingSanctions + $completedSanctions }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="text-muted small text-uppercase">Outstanding</div>
                    <div class="fs-3 fw-bold text-success">{{ $outstandingSanctions }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-white">
            <h5 class="mb-0">Violation Summary</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Category</th>
                            <th>Violation</th>
                            <th>Offense Level</th>
                            <th>Sanction</th>
                            <th>Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($summary as $item)
                            <tr>
                                <td>{{ $item['category'] ?? '-' }}</td>
                                <td>{{ $item['violation_type'] }}</td>
                                <td><span class="admin-badge admin-badge-gold">{{ $item['offense_level'] }}</span></td>
                                <td>{{ $item['sanction'] }}</td>
                                <td>
                                    @php $status = strtolower($item['status']); @endphp
                                    @if($status == 'approved')
                                        <x-admin.badge status="approved" />
                                    @elseif($status == 'pending')
                                        <x-admin.badge status="pending" />
                                    @elseif($status == 'rejected')
                                        <x-admin.badge status="rejected" />
                                    @else
                                        <x-admin.badge status="gray" :label="$item['status']" />
                                    @endif
                                </td>
                                <td>{{ $item['remarks'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No violations found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">Violation History</h5>
            <span class="text-muted small">{{ $individualViolations->total() }} records</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Violation</th>
                            <th>Category</th>
                            <th>Offense Level</th>
                            <th>Sanction</th>
                            <th>Status</th>
                            <th>Recorded By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($individualViolations as $violation)
                            <tr>
                                <td>{{ $violation->violation_date ? \Carbon\Carbon::parse($violation->violation_date)->format('M d, Y') : '-' }}</td>
                                <td>{{ $violation->violation_type }}</td>
                                <td>{{ optional($violation->violationType?->violationCategory)->category_name ?? '-' }}</td>
                                <td><span class="admin-badge admin-badge-gold">{{ \App\Http\Controllers\Admin\ViolationController::formatOffenseLevelStatic($violation->violations_count ?? 1) }}</span></td>
                                <td>{{ optional($violation->sanction)->disciplinary_sanction ?? '-' }}</td>
                                <td>
                                    @php
                                        $recordStatus = optional(\App\Models\SanctionRequest::where('student_number', $student->student_number)->where('violation_type_id', optional($violation->violationType)->violation_type_id)->latest('request_date')->first())->status ?? 'No Request';
                                    @endphp
                                    @if(strtolower($recordStatus) === 'approved')
                                        <x-admin.badge status="approved" />
                                    @elseif(strtolower($recordStatus) === 'pending')
                                        <x-admin.badge status="pending" />
                                    @elseif(strtolower($recordStatus) === 'declined')
                                        <x-admin.badge status="rejected" />
                                    @else
                                        <x-admin.badge status="gray" :label="$recordStatus" />
                                    @endif
                                </td>
                                <td>{{ $violation->recorded_by_display }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No violation records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($individualViolations->hasPages())
            <div class="card-footer bg-white">
                {{ $individualViolations->links() }}
            </div>
        @endif
    </div>
</div>
@endsection