@extends('layouts.app')

@section('title', "Violations - {$student->student_number}")

@push('styles')
<style>
    .student-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem;
        border-radius: 15px;
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
        background: #f8f9fa;
        border-radius: 10px;
        border-left: 4px solid #dc3545;
    }
    
    .violation-item::before {
        content: '';
        position: absolute;
        left: -14px;
        top: 30px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #dc3545;
        border: 3px solid white;
        box-shadow: 0 0 0 2px #dc3545;
    }
    
    .violation-title {
        font-weight: 700;
        color: #212529;
        margin-bottom: 0.5rem;
    }
    
    .violation-meta {
        font-size: 0.85rem;
        color: #6c757d;
        margin-bottom: 0.75rem;
    }
    
    .violation-description {
        color: #495057;
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
    <a href="{{ route('security.violations.students') }}" class="btn btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left me-2"></i>Back to Students
    </a>

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
            <div class="card shadow-sm border-0">
                <div class="card-header bg-light">
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
            <div class="card shadow-sm border-0">
                <div class="card-header bg-light">
                    <h5 class="fw-bold mb-0">Violation Statistics</h5>
                </div>
                <div class="card-body">
                    @forelse($violationStats['by_category'] as $category_stat)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="fw-500">{{ $category_stat->category_name ?? 'Unknown' }}</span>
                            <span class="badge bg-secondary">{{ $category_stat->total }}</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-danger" 
                                 role="progressbar" 
                                 style="width: {{ ($category_stat->total / $violationStats['total'] * 100) }}%;">
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
    <div class="card shadow-sm border-0">
        <div class="card-header bg-light">
            <h5 class="fw-bold mb-0">Violation Records</h5>
        </div>
        <div class="card-body">
            @if($violations->count() > 0)
            <div class="violation-timeline">
                @foreach($violations as $violation)
                <div class="violation-item">
                    <div class="violation-title">
                        {{ $violation->violationType->violation_type ?? 'Unknown Violation' }}
                    </div>
                    <div class="violation-meta">
                        <i class="bi bi-calendar"></i>
                        {{ $violation->violation_date->format('F d, Y \a\t h:i A') }}
                    </div>
                    <div class="violation-description">
                        {{ $violation->description ?? 'No description provided' }}
                    </div>
                    <div>
                        <span class="badge-severity 
                            @if(strtolower($violation->offense_level) == 'major') bg-danger text-white
                            @elseif(strtolower($violation->offense_level) == 'minor') bg-warning text-dark
                            @else bg-info text-white @endif">
                            {{ $violation->offense_level }} Offense
                        </span>
                        <span class="badge bg-light text-dark">
                            {{ optional($violation->violationType->violationCategory)->category_name ?? 'N/A' }}
                        </span>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-5">
                <i class="bi bi-check-circle display-4 text-success"></i>
                <p class="text-muted mt-3">No violation records found.</p>
            </div>
            @endif
        </div>
    </div>

</div>

@endsection
