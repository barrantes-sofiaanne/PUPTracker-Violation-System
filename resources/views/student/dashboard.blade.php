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
    <div class="portal-hero dashboard-hero">
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

        {{-- Sidebar: Approaching sanction deadlines --}}
        <div class="col-lg-4">
            <div class="card portal-card h-100">
                <div class="card-header card-header-custom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0">Approaching Deadlines</h5>
                        <a href="{{ route('student.record') }}#sanctionRecord" class="btn btn-sm btn-link">
                            All
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    @forelse($approachingDeadlines as $deadline)
                        @php
                            $isOverdue = $deadline->deadline_date
                                && $deadline->deadline_date->isPast();
                        @endphp
                        <div class="portal-list-item">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="min-w-0">
                                    <h6 class="fw-bold mb-1 announcement-card-heading">
                                        {{ $deadline->sanction_text }}
                                    </h6>

                                    <div class="small text-muted">
                                        <div>
                                            <i class="bi bi-building" aria-hidden="true"></i>
                                            {{ $deadline->designated_office ?: 'Office of Student Services' }}
                                        </div>
                                        <div class="{{ $isOverdue ? 'text-danger fw-semibold' : '' }}">
                                            <i class="bi bi-calendar-event" aria-hidden="true"></i>
                                            Due {{ $deadline->deadline_date->format('F d, Y') }}
                                            @if($isOverdue)
                                                <span class="portal-badge maroon ms-1">Overdue</span>
                                            @else
                                                <span class="text-muted">
                                                    &bull; {{ $deadline->deadline_date->diffForHumans() }}
                                                </span>
                                            @endif
                                        </div>
                                        @if($deadline->required_hours)
                                            <div>
                                                <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                                                {{ $deadline->required_hours }} hrs required
                                            </div>
                                        @endif
                                        <div class="mt-1">
                                            <span class="portal-badge {{ $isOverdue ? 'maroon' : 'goldenrod' }}">
                                                {{ $deadline->status }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                {{-- View: opens the Sanction Record tab and highlights
                                     this specific sanction. --}}
                                <a href="{{ route('student.record') }}?sanction={{ $deadline->record_id }}#sanctionRecord"
                                   class="btn btn-sm portal-btn-outline flex-shrink-0"
                                   aria-label="View sanction: {{ $deadline->sanction_text }}">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                    View
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-calendar-check display-4" aria-hidden="true"></i>
                            <p class="mt-2 mb-0">No approaching deadlines.</p>
                            <small>You have no pending sanction deadlines.</small>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

    </div>

    {{-- Violation Code & Sanctions Guide — shared partial, also rendered by the
         Security Dashboard so both modules show the same guide content. --}}
    @include('partials.violation-sanction-guide', [
        'guideId' => 'handbook',
        'guideTitle' => 'Violation Code & Sanctions Guide',
    ])

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