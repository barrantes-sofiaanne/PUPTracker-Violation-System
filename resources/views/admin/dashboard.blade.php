@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin-dashboard.css') }}">
@endpush

@section('content')

<div class="container-fluid">
    
    {{-- Page Header --}}
    <div class="page-header-modern d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1 class="fw-bold mb-1">Dashboard</h1>
            <p class="text-muted mb-0">Welcome back! Here's your system overview.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-3">
            <div class="dashboard-time-wrap">
                <div class="dashboard-time-label">Philippine Standard Time</div>
                <div id="phTimeDisplay" class="dashboard-time-value">
                    <i class="bi bi-clock-history"></i>
                    <span>Loading...</span>
                </div>
            </div>
            <div class="quick-actions d-flex flex-wrap gap-2">
                <a href="{{ route('admin.violations.index') }}" class="admin-btn admin-btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Record Violation
                </a>
                <a href="{{ route('admin.sanctions.index', ['sanctions_tab' => 'sanction-requests']) }}" class="admin-btn admin-btn-secondary">
                    <i class="bi bi-shield-check me-2"></i>View Pending Sanctions
                </a>
                <a href="{{ route('admin.reports') }}" class="admin-btn admin-btn-neutral">
                    <i class="bi bi-file-earmark-bar-graph me-2"></i>View Reports
                </a>
                @if(Auth::guard('admin')->user()?->isItAdministrator())
                    <a href="{{ route('admin.announcements.create') }}" class="admin-btn admin-btn-secondary">
                        <i class="bi bi-megaphone me-2"></i>New Announcement
                    </a>
                @endif
            </div>
        </div>
    </div>

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
            <div class="card stat-card shadow-sm bg-primary-light">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="stat-label text-muted mb-2">Announcements</p>
                            <h2 class="stat-value text-primary mb-0">{{ $announcementCount }}</h2>
                        </div>
                        <div class="icon-badge primary">
                            <i class="bi bi-megaphone-fill"></i>
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
            
            {{-- Top Offenders --}}
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

    {{-- Bottom Row: Category Breakdown --}}
    <div class="row g-3">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="fw-bold mb-0">Violations by Category</h5>
                    <small class="text-muted"><i class="bi bi-arrow-repeat me-1"></i>Updates automatically</small>
                </div>
                <div class="card-body">
                    @php
                        $categoryChartColors = ['#800000', '#a31515', '#daa520', '#f0c447', '#5f0000', '#c9981f', '#7a0f1e', '#e6c36a', '#8e1d29', '#b8860b'];
                        $categoryLabels = $violationsByCategory->pluck('category_name')->values();
                        $categoryValues = $violationsByCategory->pluck('violations_count')->values();
                    @endphp
                    <div class="row align-items-center">
                        <div class="col-lg-7">
                            <canvas
                                id="categoryChart"
                                height="260"
                                data-labels="{{ json_encode($categoryLabels) }}"
                                data-values="{{ json_encode($categoryValues) }}"
                                data-chart-data-url="{{ route('admin.dashboard.chart-data') }}"></canvas>
                        </div>
                        <div class="col-lg-5">
                            <div class="row" id="categoryLegend">
                                @forelse($violationsByCategory as $category)
                                <div class="col-6 mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="d-inline-block category-legend-dot" data-color="{{ $categoryChartColors[$loop->index % count($categoryChartColors)] }}"></span>
                                        <small class="text-muted text-truncate">{{ $category->category_name }}</small>
                                        <strong class="ms-auto">{{ $category->violations_count }}</strong>
                                    </div>
                                </div>
                                @empty
                                <div class="col-12">
                                    <p class="text-muted text-center py-4 mb-0">No category data available</p>
                                </div>
                                @endforelse
                            </div>
                        </div>
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