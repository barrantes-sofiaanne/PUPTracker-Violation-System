@extends('layouts.security')

@section('title', "Violations - {$student->student_number}")

@push('styles')
<style>
    .student-header {
        background: linear-gradient(120deg, rgba(128, 0, 0, 0.96) 0%, rgba(95, 0, 0, 0.96) 70%, rgba(218, 165, 32, 0.94) 175%);
        color: white;
        padding: 2rem;
        border-radius: 1rem;
        margin-bottom: 2rem;
    }
    
    .stat-mini {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        background: rgba(255,255,255,0.1);
        border-radius: 10px;
        margin-right: 1rem;
        margin-bottom: 1rem;
    }
    
    .stat-mini-value {
        font-size: 1.5rem;
        font-weight: 700;
    }
    
    .stat-mini-label {
        font-size: 0.85rem;
        opacity: 0.9;
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
</style>
@endpush

@section('content')

<div class="container-fluid py-4">

    {{-- Back Button --}}
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('security.violations.students') }}" class="btn portal-btn-outline">
            <i class="bi bi-arrow-left me-2"></i>Back to Students
        </a>
        <a href="{{ route('security.violations.report') }}" target="_blank" class="btn portal-btn">
            <i class="bi bi-file-earmark-pdf me-2"></i>Generate Report
        </a>
    </div>

    {{-- Student Header --}}
    <div class="student-header">
        <h2 class="fw-bold mb-3">
            {{ $student->student_number }} - {{ $student->last_name }}, {{ $student->first_name }}
        </h2>
        <div class="d-flex flex-wrap">
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
            <div class="card portal-card">
                <div class="card-header">
                    <h5 class="fw-bold mb-0">Student Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-12">
                            <strong>Email:</strong> {{ $student->email }}
                        </div>
                        <div class="col-12">
                            <strong>Course:</strong> {{ optional(optional($student->studentInfo)->program)->program_name ?? 'N/A' }}
                        </div>
                        <div class="col-12">
                            <strong>Year:</strong> {{ optional(optional($student->studentInfo)->year)->year ?? 'N/A' }}
                        </div>
                        <div class="col-12">
                            <strong>Section:</strong> {{ optional(optional($student->studentInfo)->section)->section_name ?? 'N/A' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card portal-card">
                <div class="card-header">
                    <h5 class="fw-bold mb-0">Violation Statistics</h5>
                </div>
                <div class="card-body">
                    @forelse($violationStats['by_category'] as $category_stat)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="fw-500">{{ $category_stat->category_name ?? 'Unknown' }}</span>
                               <span class="portal-badge muted">{{ $category_stat->total }}</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                               <div class="progress-bar"
                                   style="background-color: var(--portal-maroon);"
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
                        {{ $violation->violationType?->violation_type ?? 'Unknown Violation' }}
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
                            {{ $violation->offense_level }} Offense
                        </span>
                        <span class="portal-badge muted">
                            {{ optional($violation->violationType?->violationCategory)->category_name ?? 'N/A' }}
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
