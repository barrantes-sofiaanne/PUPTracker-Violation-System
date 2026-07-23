@extends('layouts.admin')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin.css') }}">
<style>
    .stat-card {
        border: none;
        border-radius: 12px;
        transition: all 0.3s ease;
        overflow: hidden;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
    }
    
    .stat-card.bg-primary-light {
        background: linear-gradient(135deg, rgba(13, 110, 253, 0.1) 0%, rgba(13, 110, 253, 0.05) 100%);
        border-left: 4px solid #0d6efd;
    }
    
    .stat-card.bg-danger-light {
        background: linear-gradient(135deg, rgba(220, 53, 69, 0.1) 0%, rgba(220, 53, 69, 0.05) 100%);
        border-left: 4px solid #dc3545;
    }
    
    .stat-card.bg-success-light {
        background: linear-gradient(135deg, rgba(40, 167, 69, 0.1) 0%, rgba(40, 167, 69, 0.05) 100%);
        border-left: 4px solid #28a745;
    }
    
    .stat-card.bg-warning-light {
        background: linear-gradient(135deg, rgba(255, 193, 7, 0.1) 0%, rgba(255, 193, 7, 0.05) 100%);
        border-left: 4px solid #ffc107;
    }
    
    .stat-value {
        font-size: 2.5rem;
        font-weight: 700;
        line-height: 1;
    }
    
    .stat-label {
        font-size: 0.875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .icon-badge {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    
    .icon-badge.primary {
        background: rgba(13, 110, 253, 0.2);
        color: #0d6efd;
    }
    
    .icon-badge.danger {
        background: rgba(220, 53, 69, 0.2);
        color: #dc3545;
    }
    
    .icon-badge.success {
        background: rgba(40, 167, 69, 0.2);
        color: #28a745;
    }
    
    .icon-badge.warning {
        background: rgba(255, 193, 7, 0.2);
        color: #ffc107;
    }
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .quick-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .quick-actions .btn {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }
    
    .card-header-custom {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-bottom: 2px solid #dee2e6;
    }
    
    .recent-violation-item:hover {
        background-color: #f8f9fa;
    }
</style>
@endpush

@section('title', 'Admin Dashboard')

@section('content')

<div class="container-fluid">
    
    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h1 class="fw-bold mb-1">Dashboard</h1>
            <p class="text-muted mb-0">Welcome back! Here's your system overview.</p>
        </div>
        <div class="quick-actions">
            <a href="{{ route('admin.violations.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>Record Violation
            </a>
            <a href="{{ route('admin.announcements.create') }}" class="btn btn-outline-primary">
                <i class="bi bi-megaphone me-2"></i>New Announcement
            </a>
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
            <div class="card stat-card shadow-sm bg-success-light">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="stat-label text-muted mb-2">Announcements</p>
                            <h2 class="stat-value text-success mb-0">{{ $announcementCount }}</h2>
                        </div>
                        <div class="icon-badge success">
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
                        <a href="{{ route('admin.violations.index') }}" class="btn btn-sm btn-outline-primary">
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
                                        <span class="badge bg-light text-dark">
                                            {{ $violation->violationType->violation_type ?? '-' }}
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            {{ \Carbon\Carbon::parse($violation->violation_date)->format('M d, Y') }}
                                        </small>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.violations.show', $violation->student_number) }}" 
                                           class="btn btn-sm btn-outline-primary">
                                            View
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center py-5">
                                        <i class="bi bi-inbox display-4 text-muted"></i>
                                        <p class="text-muted mt-3">No recent violations</p>
                                    </td>
                                </tr>
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
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="fw-500">{{ $offender->student_number }}</span>
                        <span class="badge bg-danger">{{ $offender->violation_count }} violations</span>
                    </div>
                    @empty
                    <p class="text-muted text-center py-3">No violation records</p>
                    @endforelse
                </div>
            </div>

            {{-- Violations by Severity --}}
            <div class="card shadow-sm border-0">
                <div class="card-header card-header-custom">
                    <h5 class="fw-bold mb-0">Violations by Severity</h5>
                </div>
                <div class="card-body">
                    @forelse($violationsBySeverity as $severity => $count)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="fw-500 text-capitalize">{{ $severity }}</span>
                            <span class="badge bg-secondary">{{ $count }}</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar 
                                @if(strtolower($severity) == 'major') bg-danger 
                                @elseif(strtolower($severity) == 'minor') bg-warning 
                                @else bg-info @endif" 
                                 role="progressbar" 
                                 style="width: {{ ($count / $totalViolations * 100) }}%;">
                            </div>
                        </div>
                    </div>
                    @empty
                    <p class="text-muted text-center py-3">No severity data</p>
                    @endforelse
                </div>
            </div>

        </div>

    </div>

    {{-- Bottom Row: Category Breakdown --}}
    <div class="row g-3">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header card-header-custom">
                    <h5 class="fw-bold mb-0">Violations by Category</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        @forelse($violationsByCategory as $category)
                        <div class="col-lg-2 col-md-4 col-6 mb-3 text-center">
                            <div class="p-3 bg-light rounded-3">
                                <h4 class="fw-bold text-primary mb-1">{{ $category->violations_count }}</h4>
                                <small class="text-muted d-block">
                                    {{ $category->category_name }}
                                </small>
                            </div>
                        </div>
                        @empty
                        <div class="col-12">
                            <p class="text-muted text-center py-4">No category data available</p>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script src="{{ asset('assets/js/admin.js') }}"></script>
@endpush

@endsection