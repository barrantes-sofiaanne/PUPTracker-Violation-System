@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin-dashboard.css') }}">
@endpush

@section('content')

<div class="container-fluid">
    
    {{-- Page Header — Security-style hero with the PHT clock on the RIGHT.
         The clock is opt-in (:time="true") so other modules do not inherit it. --}}
    <x-admin.page-header
        title="Dashboard"
        description="Welcome back! Here's your system overview."
        :time="true" />

    {{-- Statistics Cards --}}
    <div class="row mb-4 g-3">
        
        <div class="col-lg-3 col-md-6">
            <div class="card stat-card shadow-sm bg-primary-light">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="stat-label text-muted mb-2">Total Students</p>
                            <h2 class="stat-value text-primary mb-0">{{ $totalStudents }}</h2>
                        </div>
                        <div class="icon-badge primary">
                            <i class="bi bi-people-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card stat-card shadow-sm bg-danger-light">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="stat-label text-muted mb-2">Total Violations</p>
                            <h2 class="stat-value text-danger mb-0">{{ $totalViolations }}</h2>
                        </div>
                        <div class="icon-badge danger">
                            <i class="bi bi-exclamation-octagon-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card stat-card shadow-sm bg-warning-light">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="stat-label text-muted mb-2">Pending Requests</p>
                            <h2 class="stat-value text-warning mb-0">{{ $pendingRequests }}</h2>
                        </div>
                        <div class="icon-badge warning">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card stat-card shadow-sm bg-info-light">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="stat-label text-muted mb-2">Pending Confirmations</p>
                            <h2 class="stat-value text-info mb-0">{{ $pendingConfirmations }}</h2>
                        </div>
                        <div class="icon-badge info">
                            <i class="bi bi-clipboard2-check-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- Main Content Row --}}
    <div class="row g-3 mb-4">
        
        {{-- Recent Violations --}}
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header card-header-custom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0">Recent Violations</h5>
                        <a href="{{ route('admin.violations.index') }}" class="admin-btn admin-btn-view admin-btn-sm">
                            View All
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Violation Type</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentViolations as $violation)
                                <tr class="recent-violation-item">
                                    <td>
                                        <strong>{{ $violation->student_number }}</strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-maroon-soft text-maroon">
                                            {{ $violation->violationType->violation_type ?? '-' }}
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            {{ \Carbon\Carbon::parse($violation->violation_date)->format('M d, Y') }}
                                        </small>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.violations.show', ['student_number' => $violation->student_number, 'source' => 'dashboard']) }}" 
                                           class="admin-btn admin-btn-view admin-btn-sm">
                                            <i class="bi bi-eye me-1"></i>
                                            View Record
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                    <x-admin.empty-state
                                        :colspan="4"
                                        icon="bi-inbox"
                                        title="No recent violations"
                                        message="Recent violation records will appear here."
                                        :clearUrl="null" />
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sidebar Widgets --}}
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header card-header-custom">
                    <h5 class="fw-bold mb-0">Top Offenders</h5>
                </div>
                <div class="card-body">
                    @forelse($topOffenders as $offender)
                    <a href="{{ route('admin.violations.show', ['student_number' => $offender->student_number, 'source' => 'dashboard']) }}" class="d-flex justify-content-between align-items-center py-2 border-bottom text-decoration-none">
                        <span class="fw-500 text-maroon">{{ $offender->student_number }}</span>
                        <span class="badge bg-danger">{{ $offender->violation_count }} violations</span>
                    </a>
                    @empty
                    <p class="text-muted text-center py-3">No violation records</p>
                    @endforelse
                </div>
            </div>
        </div>

    </div>

    {{-- Bottom Row: Daily Violations Bar Chart --}}
    <div class="row g-3">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header card-header-custom">
                    <h5 class="fw-bold mb-0">Daily Violations</h5>
                </div>
                <div class="card-body">
                    @php
                        $trendLabels = $dailyTrend['labels'];
                        $trendValues = $dailyTrend['values'];
                    @endphp
                    <div class="col-12">
                        <div class="daily-chart-wrap">
                            <canvas
                                id="dailyViolationChart"
                                data-labels="{{ json_encode($trendLabels) }}"
                                data-values="{{ json_encode($trendValues) }}"
                                data-chart-data-url="{{ route('admin.dashboard.chart-data') }}"></canvas>
                        </div>
                        <p class="text-muted small mb-0 mt-2" id="dailyViolationChartMeta"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="{{ asset('js/admin-dashboard.js') }}"></script>
<script src="{{ asset('assets/js/admin.js') }}"></script>
@endpush

@endsection