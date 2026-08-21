@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@push('styles')
<style>
    .dashboard-time-wrap {
        text-align: right;
        min-width: 240px;
    }

    .dashboard-time-label {
        font-size: 0.76rem;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        opacity: 0.8;
        margin-bottom: 0.2rem;
    }

    .dashboard-time-value {
        font-size: 1.05rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.22);
        border-radius: 999px;
        padding: 0.4rem 0.8rem;
        white-space: nowrap;
    }

    @media (max-width: 767.98px) {
        .dashboard-time-wrap {
            text-align: left;
            min-width: unset;
        }

        .dashboard-time-value {
            font-size: 0.95rem;
        }
    }
</style>
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
                <a href="{{ route('admin.violations.index') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Record Violation
                </a>
                @if(Auth::guard('admin')->user()?->isItAdministrator())
                    <a href="{{ route('admin.announcements.create') }}" class="btn announcements-new-btn">
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
                        <a href="{{ route('admin.violations.index') }}" class="btn btn-sm btn-primary">
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
                                        <a href="{{ route('admin.violations.show', $violation->student_number) }}" 
                                           class="btn btn-sm btn-primary">
                                            <i class="bi bi-eye me-1"></i>
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
                                 aria-valuemin="0"
                                 aria-valuemax="100"
                                 aria-valuenow="0"
                                 data-progress-width="{{ $totalViolations > 0 ? round(($count / $totalViolations) * 100, 1) : 0 }}">
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
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Philippine Standard Time live clock
    const phTimeDisplay = document.getElementById('phTimeDisplay');

    const updatePhilippineTime = function () {
        if (!phTimeDisplay) {
            return;
        }

        const now = new Date();
        const formatted = new Intl.DateTimeFormat('en-PH', {
            timeZone: 'Asia/Manila',
            month: 'short',
            day: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
        }).format(now);

        const span = phTimeDisplay.querySelector('span');
        if (span) {
            span.textContent = formatted + ' PHT';
        }
    };

    updatePhilippineTime();
    setInterval(updatePhilippineTime, 1000);

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
<script src="{{ asset('assets/js/admin.js') }}"></script>
@endpush

@endsection