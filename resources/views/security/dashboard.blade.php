@extends('layouts.security')

@push('styles')
<style>
    .security-stat-card {
        border: none;
        border-radius: 12px;
        transition: all 0.3s ease;
        overflow: hidden;
    }
    
    .security-stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
    }
    
    .security-stat-card.primary-light {
        background: linear-gradient(135deg, rgba(13, 110, 253, 0.1) 0%, rgba(13, 110, 253, 0.05) 100%);
        border-left: 4px solid #0d6efd;
    }
    
    .security-stat-card.danger-light {
        background: linear-gradient(135deg, rgba(220, 53, 69, 0.1) 0%, rgba(220, 53, 69, 0.05) 100%);
        border-left: 4px solid #dc3545;
    }
    
    .security-stat-card.warning-light {
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
    
    .card-header-custom {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-bottom: 2px solid #dee2e6;
    }
    
    .offender-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: 600;
    }
    
    .search-bar-container {
        margin-bottom: 1.5rem;
    }
    
    .violation-item:hover {
        background-color: #f8f9fa;
    }
</style>
@endpush

@section('title', 'Security Dashboard')

@section('content')

<div class="container-fluid py-4">
    
    {{-- Page Header --}}
    <div class="portal-hero page-header-modern">
        <div>
            <h1 class="fw-bold mb-1">Security Officer Dashboard</h1>
            <p class="mb-0">Monitor student violations and manage campus security.</p>
        </div>
        <div>
            <span>
                <i class="bi bi-calendar"></i>
                {{ now()->format('M d, Y - H:i') }}
            </span>
        </div>
    </div>

    {{-- Statistics Cards --}}
    <div class="row mb-4 g-3">
        
        <div class="col-lg-3 col-md-6">
            <div class="card portal-stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="portal-stat-label mb-2">Active Students</p>
                            <h2 class="portal-stat-value mb-0">{{ $activeStudents }}</h2>
                        </div>
                        <div class="portal-icon-badge">
                            <i class="bi bi-people-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card portal-stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="portal-stat-label mb-2">Major Violations</p>
                            <h2 class="portal-stat-value mb-0">{{ $majorViolations }}</h2>
                        </div>
                        <div class="portal-icon-badge">
                            <i class="bi bi-exclamation-octagon-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card portal-stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="portal-stat-label mb-2">Minor Violations</p>
                            <h2 class="portal-stat-value mb-0">{{ $minorViolations }}</h2>
                        </div>
                        <div class="portal-icon-badge">
                            <i class="bi bi-info-circle-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card portal-stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="portal-stat-label mb-2">Total Violations</p>
                            <h2 class="portal-stat-value mb-0">{{ $totalViolations }}</h2>
                        </div>
                        <div class="portal-icon-badge">
                            <i class="bi bi-file-text-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- Quick Actions --}}
    <div class="mb-4">
        <div class="card portal-card">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Quick Actions</h5>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('security.violations.students') }}" class="btn portal-btn">
                        <i class="bi bi-people-fill me-2"></i>View Student Violations
                    </a>
                    <button class="btn portal-btn-outline" data-bs-toggle="modal" data-bs-target="#searchModal">
                        <i class="bi bi-search me-2"></i>Search Student
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Search Bar --}}
    <div class="search-bar-container">
        <div class="card portal-card">
            <div class="card-body">
                <form method="GET" action="" class="row g-2">
                    <div class="col-md-8">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0">
                                <i class="bi bi-search"></i>
                            </span>
                            <input
                                type="text"
                                class="form-control border-0 bg-light"
                                name="search"
                                placeholder="Search by student number or name..."
                                value="{{ request('search', '') }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn portal-btn w-100">
                            <i class="bi bi-search me-2"></i>Search Student
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Main Content Row --}}
    <div class="row g-3 mb-4">
        
        {{-- Recent Violations --}}
        <div class="col-lg-8">
            <div class="card portal-card">
                <div class="card-header card-header-custom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0">Recent Violations (Last 7 Days)</h5>
                        <span class="portal-badge muted">{{ $recentViolations->count() }} records</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 portal-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Violation Type</th>
                                    <th>Severity</th>
                                    <th>Date & Time</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentViolations as $violation)
                                <tr class="violation-item">
                                    <td>
                                        <strong>{{ $violation->student_number }}</strong>
                                        @if($violation->student)
                                        <br><small class="text-muted">
                                            {{ $violation->student->last_name }}, {{ $violation->student->first_name }}
                                        </small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="portal-badge muted">
                                            {{ $violation->violationType?->violation_type ?? '-' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="portal-badge danger">
                                            {{ $violation->offense_level }}
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            {{ \Carbon\Carbon::parse($violation->violation_date)->format('M d, Y H:i') }}
                                        </small>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm portal-btn-outline" data-bs-toggle="modal" data-bs-target="#violationDetailModal" data-violation="{{ json_encode($violation) }}">
                                            View
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <i class="bi bi-inbox display-4 text-muted"></i>
                                        <p class="text-muted mt-3">No recent violations in the last 7 days</p>
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
            
            {{-- Active Offenders --}}
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header card-header-custom">
                    <h5 class="fw-bold mb-0">Top Offenders</h5>
                </div>
                <div class="card-body">
                    @forelse($activeOffenders as $offender)
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <strong>{{ $offender->student_number }}</strong>
                        </div>
                        <span class="portal-badge danger">
                            {{ $offender->violation_count }} violations
                        </span>
                    </div>
                    @empty
                    <p class="text-muted text-center py-3">No records found</p>
                    @endforelse
                </div>
            </div>

            {{-- Violations by Category --}}
            <div class="card shadow-sm border-0">
                <div class="card-header card-header-custom">
                    <h5 class="fw-bold mb-0">Categories (This Week)</h5>
                </div>
                <div class="card-body">
                    @forelse($violationsByCategory as $category)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="fw-500 text-truncate">
                                {{ $category->category_name ?? 'Unknown' }}
                            </span>
                            <span class="portal-badge muted">{{ $category->count }}</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar"
                                 style="background-color: var(--portal-maroon);"
                                 role="progressbar"
                                 aria-valuemin="0"
                                 aria-valuemax="100"
                                 aria-valuenow="0"
                                 data-progress-width="{{ $recentViolations->count() > 0 ? round(($category->count / $recentViolations->count()) * 100, 1) : 0 }}">
                            </div>
                        </div>
                    </div>
                    @empty
                    <p class="text-muted text-center py-3">No category data</p>
                    @endforelse
                </div>
            </div>

        </div>

    </div>

</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-progress-width]').forEach(function (bar) {
        const width = parseFloat(bar.getAttribute('data-progress-width'));
        if (!Number.isNaN(width)) {
            const safeWidth = Math.max(0, Math.min(100, width));
            bar.style.width = safeWidth + '%';
            bar.setAttribute('aria-valuenow', safeWidth.toString());
        }
    });
});
</script>
@endpush

@endsection