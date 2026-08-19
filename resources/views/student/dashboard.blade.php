@extends('layouts.student')

@section('title', 'Student Dashboard')

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
        font-size: 1.18rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.22);
        border-radius: 999px;
        padding: 0.45rem 0.85rem;
        white-space: nowrap;
    }

    @media (max-width: 767.98px) {
        .dashboard-time-wrap {
            text-align: left;
            min-width: unset;
        }

        .dashboard-time-value {
            font-size: 1rem;
        }
    }
</style>
@endpush

@section('content')

<div class="container-fluid py-4">
    
    {{-- Welcome Section --}}
    <div class="portal-hero">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h2 class="mb-1 fw-bold">
                    <i class="bi bi-person-circle me-2"></i>Welcome, {{ $user->first_name }}!
                </h2>
                <p class="mb-0">
                    Student Number: <strong>{{ $user->student_number }}</strong>
                </p>
            </div>

            <div class="dashboard-time-wrap">
                <div class="dashboard-time-label">Philippine Standard Time</div>
                <div id="phTimeDisplay" class="dashboard-time-value">
                    <i class="bi bi-clock-history"></i>
                    <span>Loading...</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Statistics Cards --}}
    <div class="row mb-4 g-3">
        
        <div class="col-lg-3 col-md-6">
            <div class="card portal-stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="portal-stat-label mb-1">Unread Notifications</p>
                            <h2 class="portal-stat-value mb-0">{{ $notificationCount }}</h2>
                        </div>
                        <div class="portal-icon-badge">
                            <i class="bi bi-bell-fill"></i>
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
                            <p class="portal-stat-label mb-1">Total Violations</p>
                            <h2 class="portal-stat-value mb-0">{{ $totalViolations }}</h2>
                        </div>
                        <div class="portal-icon-badge">
                            <i class="bi bi-exclamation-octagon-fill"></i>
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
            <div class="card portal-card">
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
                    <div class="portal-list-item">
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
                                    <span class="portal-badge gold">
                                        {{ $violation->offense_level ?? 'Unknown' }}
                                    </span>
                                </div>
                                <span class="portal-badge muted">
                                    {{ optional(optional($violation->violationType)->violationCategory)->category_name ?? 'N/A' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-5">
                        <i class="bi bi-check-circle display-4" style="color: var(--portal-goldenrod);"></i>
                        <p class="text-muted mt-3">Good news! No violations on record.</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Sidebar: Notifications --}}
        <div class="col-lg-4">
            <div class="card portal-card">
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
                    <div class="portal-list-item {{ !$notification->is_read ? 'bg-light' : '' }}">
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

    {{-- Student Handbook --}}
    <div class="card portal-card">
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
                            <span class="portal-badge maroon me-2">{{ $category->violationTypes->count() }}</span>
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
                                <h6 class="fw-bold portal-section-title mb-2">
                                    {{ $type->violation_type }}
                                </h6>
                                <p class="text-muted small mb-3">
                                    {{ $type->violation_description }}
                                </p>
                                <div class="portal-table-wrap">
                                <table class="table table-sm table-bordered portal-table">
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
                                                <span class="portal-badge gold">
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
<script>
document.addEventListener('DOMContentLoaded', function () {
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
});
</script>
<script src="{{ asset('js/student.js') }}"></script>
@endpush