@extends('layouts.app')

@section('title', 'Student Dashboard')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/student.css') }}">
<style>
    .student-stat-card {
        border: none;
        border-radius: 12px;
        transition: all 0.3s ease;
        overflow: hidden;
    }
    
    .student-stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
    }
    
    .student-stat-card.primary-light {
        background: linear-gradient(135deg, rgba(13, 110, 253, 0.1) 0%, rgba(13, 110, 253, 0.05) 100%);
        border-left: 4px solid #0d6efd;
    }
    
    .student-stat-card.danger-light {
        background: linear-gradient(135deg, rgba(220, 53, 69, 0.1) 0%, rgba(220, 53, 69, 0.05) 100%);
        border-left: 4px solid #dc3545;
    }
    
    .student-stat-card.info-light {
        background: linear-gradient(135deg, rgba(23, 162, 184, 0.1) 0%, rgba(23, 162, 184, 0.05) 100%);
        border-left: 4px solid #17a2b8;
    }
    
    .stat-value {
        font-size: 2rem;
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
        width: 45px;
        height: 45px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
    }
    
    .icon-badge.primary {
        background: rgba(13, 110, 253, 0.2);
        color: #0d6efd;
    }
    
    .icon-badge.danger {
        background: rgba(220, 53, 69, 0.2);
        color: #dc3545;
    }
    
    .icon-badge.info {
        background: rgba(23, 162, 184, 0.2);
        color: #17a2b8;
    }
    
    .card-header-custom {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-bottom: 2px solid #dee2e6;
    }
    
    .page-header {
        margin-bottom: 2rem;
    }
    
    .welcome-section {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 15px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
    }
    
    .welcome-section h2 {
        font-size: 2rem;
        font-weight: 700;
    }
    
    .welcome-section p {
        font-size: 1rem;
        opacity: 0.9;
    }
    
    .notification-item {
        padding: 1rem;
        border-bottom: 1px solid #e9ecef;
        transition: all 0.2s ease;
    }
    
    .notification-item:hover {
        background-color: #f8f9fa;
        border-left: 3px solid #0d6efd;
        padding-left: calc(1rem - 3px);
    }
    
    .notification-item.unread {
        background-color: #e7f3ff;
    }
    
    .violation-item:hover {
        background-color: #f8f9fa;
    }
    
    .severity-badge {
        font-weight: 600;
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
    }
</style>
@endpush

@section('content')

<div class="container-fluid py-4">
    
    {{-- Welcome Section --}}
    <div class="welcome-section">
        <h2 class="mb-1">
            <i class="bi bi-person-circle me-2"></i>Welcome, {{ $user->first_name }}!
        </h2>
        <p class="mb-0">
            Student Number: <strong>{{ $user->student_number }}</strong>
        </p>
    </div>

    {{-- Statistics Cards --}}
    <div class="row mb-4 g-3">
        
        <div class="col-lg-3 col-md-6">
            <div class="card student-stat-card shadow-sm primary-light">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="stat-label text-muted mb-1">Unread Notifications</p>
                            <h2 class="stat-value text-primary mb-0">{{ $notificationCount }}</h2>
                        </div>
                        <div class="icon-badge primary">
                            <i class="bi bi-bell-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card student-stat-card shadow-sm danger-light">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="stat-label text-muted mb-1">Total Violations</p>
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
            <div class="card student-stat-card shadow-sm info-light">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="stat-label text-muted mb-1">Quick Links</p>
                            <h2 class="stat-value text-info mb-0">4</h2>
                        </div>
                        <div class="icon-badge info">
                            <i class="bi bi-link-45deg"></i>
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
                        <h5 class="fw-bold mb-0">Your Violation Records</h5>
                        <a href="{{ route('student.record') }}" class="btn btn-sm btn-outline-primary">
                            View All
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    @forelse($recentViolations as $violation)
                    <div class="violation-item border-bottom">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="mb-2">
                                    <h6 class="fw-bold mb-1">
                                        {{ $violation->violationType->violation_type ?? 'Unknown Violation' }}
                                    </h6>
                                    <small class="text-muted">
                                        <i class="bi bi-calendar"></i>
                                        {{ \Carbon\Carbon::parse($violation->violation_date)->format('F d, Y \a\t h:i A') }}
                                    </small>
                                </div>
                                <p class="text-muted mb-0 small">
                                    {{ \Illuminate\Support\Str::limit($violation->description, 100) }}
                                </p>
                            </div>
                            <div class="col-auto">
                                <div class="mb-2">
                                    <span class="severity-badge bg-warning text-dark">
                                        {{ $violation->offense_level ?? 'Unknown' }}
                                    </span>
                                </div>
                                <span class="badge bg-secondary">
                                    {{ optional(optional($violation->violationType)->violationCategory)->category_name ?? 'N/A' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-5">
                        <i class="bi bi-check-circle display-4 text-success"></i>
                        <p class="text-muted mt-3">Good news! No violations on record.</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Sidebar: Notifications --}}
        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-header card-header-custom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0">Latest Notifications</h5>
                        <a href="{{ route('student.notifications') }}" class="btn btn-sm btn-link">
                            All
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    @forelse($notifications as $notification)
                    <div class="notification-item {{ !$notification->is_read ? 'unread' : '' }}">
                        <div class="fw-500 mb-1">
                            {{ $notification->message }}
                        </div>
                        <small class="text-muted d-block">
                            <i class="bi bi-clock"></i>
                            {{ $notification->created_at->diffForHumans() }}
                        </small>
                    </div>
                    @empty
                    <div class="p-3 text-center text-muted">
                        <i class="bi bi-inbox display-4"></i>
                        <p class="mt-2">No notifications yet</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

    </div>

    {{-- Quick Actions --}}
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header card-header-custom">
                    <h5 class="fw-bold mb-0">Quick Access</h5>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <a href="{{ route('student.record') }}" class="btn btn-outline-primary w-100">
                                <i class="bi bi-file-text me-2"></i>My Records
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="{{ route('student.announcements') }}" class="btn btn-outline-primary w-100">
                                <i class="bi bi-megaphone me-2"></i>Announcements
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="{{ route('student.profile') }}" class="btn btn-outline-primary w-100">
                                <i class="bi bi-person me-2"></i>Profile
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="{{ route('student.settings') }}" class="btn btn-outline-primary w-100">
                                <i class="bi bi-gear me-2"></i>Settings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Student Handbook --}}
    <div class="card shadow-sm border-0">
        <div class="card-header card-header-custom">
            <h5 class="fw-bold mb-0">
                <i class="bi bi-book me-2"></i>Violation Code & Sanctions Guide
            </h5>
        </div>
        <div class="card-body">
            <div class="accordion" id="handbookAccordion">
                @forelse($categories as $category)
                <div class="accordion-item border-0 mb-2">
                    <h2 class="accordion-header">
                        <button
                            class="accordion-button collapsed"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#category{{ $category->violation_category_id }}"
                            aria-expanded="false">
                            <span class="badge bg-primary me-2">{{ $category->violationTypes->count() }}</span>
                            {{ $category->category_name }}
                        </button>
                    </h2>
                    <div
                        id="category{{ $category->violation_category_id }}"
                        class="accordion-collapse collapse"
                        data-bs-parent="#handbookAccordion">
                        <div class="accordion-body pt-0">
                            @forelse($category->violationTypes as $type)
                            <div class="mb-4 pb-3 border-bottom">
                                <h6 class="fw-bold text-primary mb-2">
                                    {{ $type->violation_type }}
                                </h6>
                                <p class="text-muted small mb-3">
                                    {{ $type->violation_description }}
                                </p>
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width: 40%;">Offense Level</th>
                                            <th>Sanction</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($type->disciplinarySanctions as $sanction)
                                        <tr>
                                            <td class="text-center">
                                                <span class="badge 
                                                    @if(strtolower($sanction->offense_level) == 'major') bg-danger 
                                                    @elseif(strtolower($sanction->offense_level) == 'minor') bg-warning 
                                                    @else bg-info @endif">
                                                    {{ $sanction->offense_level }}
                                                </span>
                                            </td>
                                            <td>{{ $sanction->disciplinary_sanction }}</td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="2" class="text-center text-muted small">No sanctions defined</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @empty
                            <p class="text-muted">No violation types in this category.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
                @empty
                <div class="alert alert-info">
                    No violation categories available.
                </div>
                @endforelse
            </div>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script src="{{ asset('js/student.js') }}"></script>
@endpush