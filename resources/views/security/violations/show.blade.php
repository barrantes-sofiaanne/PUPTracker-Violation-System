@extends('layouts.security')

@section('title', "Violations - {$student->student_number}")

@push('styles')
<style>
    .student-header {
        position: relative;
        overflow: hidden;
        background: linear-gradient(120deg, rgba(128, 0, 0, 0.96) 0%, rgba(95, 0, 0, 0.96) 70%, rgba(218, 165, 32, 0.94) 175%);
        color: white;
        padding: 2rem;
        border-radius: 1.15rem;
        margin-bottom: 2rem;
        box-shadow: 0 18px 34px rgba(95, 0, 0, 0.28);
        border: 1px solid rgba(255, 255, 255, 0.12);
    }

    .student-header::before {
        content: '';
        position: absolute;
        width: 320px;
        height: 320px;
        right: -90px;
        top: -170px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0) 72%);
        pointer-events: none;
    }

    .student-header::after {
        content: '';
        position: absolute;
        width: 260px;
        height: 260px;
        left: -100px;
        bottom: -180px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(255, 220, 150, 0.18) 0%, rgba(255, 220, 150, 0) 74%);
        pointer-events: none;
    }

    .student-header h2 {
        color: #fff !important;
    }

    .student-name-line {
        color: #fff;
    }

    .student-header-content {
        position: relative;
        z-index: 1;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: flex-end;
        gap: 1rem;
        margin-bottom: 1.25rem;
    }

    .student-header-label {
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        opacity: 0.9;
        margin-bottom: 0.35rem;
    }

    .student-name-line {
        margin: 0;
        line-height: 1.2;
        text-wrap: balance;
    }

    .student-number {
        font-weight: 800;
        letter-spacing: 0.02em;
        margin-bottom: 0.2rem;
    }

    .student-stats-grid {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: repeat(3, minmax(120px, 1fr));
        gap: 0.8rem;
        max-width: 460px;
    }
    
    .stat-mini {
        display: flex;
        align-items: center;
        padding: 1rem;
        background: linear-gradient(145deg, rgba(255, 255, 255, 0.14), rgba(255, 255, 255, 0.08));
        border: 1px solid rgba(255, 255, 255, 0.16);
        border-radius: 12px;
        min-height: 88px;
        backdrop-filter: blur(2px);
    }
    
    .stat-mini-value {
        font-size: 1.5rem;
        font-weight: 700;
        line-height: 1;
    }
    
    .stat-mini-label {
        font-size: 0.85rem;
        opacity: 0.92;
        margin-top: 0.35rem;
    }

    .profile-panel {
        border: 1px solid rgba(122, 19, 19, 0.14);
        border-radius: 1.1rem;
        box-shadow: 0 10px 24px rgba(122, 19, 19, 0.08);
        overflow: hidden;
    }

    .profile-panel .card-header {
        background: linear-gradient(180deg, #fffef8 0%, #fff8e8 100%);
        border-bottom: 1px solid rgba(122, 19, 19, 0.12);
        padding: 0.95rem 1.1rem;
    }

    .profile-panel .card-header h5 {
        color: #7f0000;
        letter-spacing: 0.01em;
    }

    .student-info-grid {
        display: grid;
        gap: 0.6rem;
    }

    .student-info-item {
        display: flex;
        align-items: baseline;
        gap: 0.4rem;
        font-size: 1.05rem;
    }

    .student-info-item .label {
        font-weight: 700;
        color: #2f2f2f;
    }

    .student-info-item .value {
        color: #1d2740;
        font-weight: 600;
    }

    .stat-category-row {
        margin-bottom: 1rem;
    }

    .stat-category-row:last-child {
        margin-bottom: 0;
    }

    .category-name {
        font-weight: 600;
        color: #202540;
        text-transform: uppercase;
        letter-spacing: 0.01em;
    }

    .category-count {
        border-radius: 999px;
        font-weight: 700;
        padding: 0.28rem 0.7rem;
    }

    .category-progress {
        height: 7px;
        border-radius: 999px;
        background: #e7ebf1;
        overflow: hidden;
    }

    .category-progress .progress-bar {
        border-radius: 999px;
        background: linear-gradient(90deg, #840707 0%, #a10d0d 100%);
    }
    
    .violation-timeline {
        position: relative;
        padding-left: 2rem;
    }
    
    .violation-item {
        position: relative;
        padding: 1.5rem;
        margin-bottom: 1rem;
        background: linear-gradient(180deg, #fffefa 0%, #fff8e8 100%);
        border-radius: 0.95rem;
        border-left: 4px solid #800000;
    }
    
    .violation-item::before {
        content: '';
        position: absolute;
        left: -14px;
        top: 30px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #800000;
        border: 3px solid white;
        box-shadow: 0 0 0 2px #800000;
    }
    
    .violation-title {
        font-weight: 700;
        color: #5f0000;
        margin-bottom: 0.5rem;
    }
    
    .violation-meta {
        font-size: 0.85rem;
        color: #67585c;
        margin-bottom: 0.75rem;
    }
    
    .violation-description {
        color: #3c3134;
        margin-bottom: 0.75rem;
    }
    
    .badge-severity {
        display: inline-block;
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    @media (max-width: 767.98px) {
        .student-header {
            padding: 1.25rem;
        }

        .student-name-line {
            font-size: 1.6rem;
        }

        .student-stats-grid {
            grid-template-columns: repeat(2, minmax(120px, 1fr));
            max-width: 100%;
        }
    }
</style>
@endpush

@section('content')

<div class="container-fluid py-4">

    {{-- Back Button --}}
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('security.violations.students') }}" class="btn portal-btn-outline">
            <i class="bi bi-arrow-left me-2"></i>Back to Students
        </a>
    </div>

    {{-- Student Header --}}
    <div class="student-header">
        <div class="student-header-content">
            <div class="student-identity">
                <div class="student-header-label">Student Record</div>
                <div class="student-number fs-3">{{ $student->student_number }}</div>
                <h2 class="fw-bold student-name-line mb-0">{{ $student->last_name }}, {{ $student->first_name }}</h2>
            </div>
        </div>

        <div class="student-stats-grid">
            <div class="stat-mini">
                <div>
                    <div class="stat-mini-value">{{ $violationStats['total'] }}</div>
                    <div class="stat-mini-label">Total Violations</div>
                </div>
            </div>
            <div class="stat-mini">
                <div>
                    <div class="stat-mini-value">{{ $violationStats['major'] }}</div>
                    <div class="stat-mini-label">Major</div>
                </div>
            </div>
            <div class="stat-mini">
                <div>
                    <div class="stat-mini-value">{{ $violationStats['minor'] }}</div>
                    <div class="stat-mini-label">Minor</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Student Info --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card profile-panel">
                <div class="card-header">
                    <h5 class="fw-bold mb-0">Student Information</h5>
                </div>
                <div class="card-body">
                    <div class="student-info-grid">
                        <div class="student-info-item">
                            <span class="label">Course:</span>
                            <span class="value">{{ optional(optional($student->studentInfo)->program)->program_name ?? 'N/A' }}</span>
                        </div>
                        <div class="student-info-item">
                            <span class="label">Year:</span>
                            <span class="value">{{ optional(optional($student->studentInfo)->year)->year ?? 'N/A' }}</span>
                        </div>
                        <div class="student-info-item">
                            <span class="label">Section:</span>
                            <span class="value">{{ optional(optional($student->studentInfo)->section)->section_name ?? 'N/A' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card profile-panel">
                <div class="card-header">
                    <h5 class="fw-bold mb-0">Violation Statistics</h5>
                </div>
                <div class="card-body">
                    @forelse($violationStats['by_category'] as $category_stat)
                    <div class="stat-category-row">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="category-name">{{ $category_stat->category_name ?? 'Unknown' }}</span>
                               <span class="portal-badge muted category-count">{{ $category_stat->total }}</span>
                        </div>
                        <div class="progress category-progress">
                               <div class="progress-bar"
                                 role="progressbar"
                                 aria-valuemin="0"
                                 aria-valuemax="100"
                                 aria-valuenow="0"
                                 data-progress-width="{{ $violationStats['total'] > 0 ? round(($category_stat->total / $violationStats['total']) * 100, 1) : 0 }}">
                            </div>
                        </div>
                    </div>
                    @empty
                    <p class="text-muted">No category data available</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Violations List --}}
    <div class="card portal-card">
        <div class="card-header">
            <h5 class="fw-bold mb-0">Violation Records</h5>
        </div>
        <div class="card-body">
            @if($violations->count() > 0)
            <div class="violation-timeline">
                @foreach($violations as $violation)
                <div class="violation-item">
                    <div class="violation-title">
                        {{ $violation->violation_type_display }}
                    </div>
                    <div class="violation-meta">
                        <i class="bi bi-calendar"></i>
                        {{ $violation->violation_date->format('F d, Y \a\t h:i A') }}
                    </div>
                    <div class="violation-description">
                        {{ $violation->description ?? 'No description provided' }}
                    </div>
                    <div>
                        <span class="badge-severity portal-badge danger">
                            {{ $violation->offense_level ?: '1st Offense' }}
                        </span>
                        <span class="portal-badge muted">
                            {{ $violation->violation_category_display }}
                        </span>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-5">
                <i class="bi bi-check-circle display-4" style="color: var(--portal-goldenrod);"></i>
                <p class="mt-3" style="color: #67585c;">No violation records found.</p>
            </div>
            @endif
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
