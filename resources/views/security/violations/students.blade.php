@extends('layouts.app')

@section('title', 'Students with Violations')

@push('styles')
<style>
    .student-card {
        border: none;
        border-radius: 12px;
        transition: all 0.3s ease;
        overflow: hidden;
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.5rem;
        margin-bottom: 1rem;
        background: #f8f9fa;
    }
    
    .student-card:hover {
        background: #e9ecef;
        transform: translateX(5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .violation-badge {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        color: white;
        font-weight: 700;
        font-size: 1.3rem;
    }
    
    .student-info {
        flex: 1;
    }
    
    .student-name {
        font-weight: 700;
        color: #212529;
        margin-bottom: 0.25rem;
    }
    
    .student-meta {
        font-size: 0.85rem;
        color: #6c757d;
    }
    
    .page-header {
        margin-bottom: 2rem;
    }
    
    .search-container {
        margin-bottom: 2rem;
    }
</style>
@endpush

@section('content')

<div class="container-fluid py-4">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h1 class="fw-bold mb-1">Students with Violations</h1>
            <p class="text-muted mb-0">View detailed violation records for students.</p>
        </div>
    </div>

    {{-- Search Bar --}}
    <div class="search-container">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="input-group">
                    <span class="input-group-text bg-light border-0">
                        <i class="bi bi-search"></i>
                    </span>
                    <input 
                        type="text" 
                        class="form-control border-0 bg-light" 
                        id="studentSearch"
                        placeholder="Search by student number or name...">
                </div>
            </div>
        </div>
    </div>

    {{-- Students List --}}
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            @forelse($students as $student)
            <a href="{{ route('security.violations.show', $student->student_number) }}" class="text-decoration-none text-dark">
                <div class="student-card">
                    <div class="violation-badge">
                        {{ $student->violations_count }}
                    </div>
                    <div class="student-info">
                        <div class="student-name">
                            {{ $student->student_number }} - {{ $student->last_name }}, {{ $student->first_name }}
                        </div>
                        <div class="student-meta">
                            <i class="bi bi-building"></i> {{ optional($student->course)->course_name ?? 'N/A' }}
                            @if($student->year)
                            | <i class="bi bi-calendar"></i> Year {{ $student->year->year }}
                            @endif
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-danger">
                            {{ $student->violations_count }} violation{{ $student->violations_count !== 1 ? 's' : '' }}
                        </span>
                        <div style="font-size: 0.8rem; color: #6c757d; margin-top: 0.5rem;">
                            Latest: {{ $student->violations->first()?->violation_date?->format('M d, Y') ?? 'N/A' }}
                        </div>
                    </div>
                </div>
            </a>
            @empty
            <div class="text-center py-5">
                <i class="bi bi-inbox display-4 text-muted"></i>
                <p class="text-muted mt-3">No students with violations found.</p>
            </div>
            @endforelse
        </div>
    </div>

    {{-- Pagination --}}
    @if($students->hasPages())
    <div class="mt-4">
        {{ $students->links() }}
    </div>
    @endif

</div>

@push('scripts')
<script>
    document.getElementById('studentSearch').addEventListener('input', function(e) {
        const query = e.target.value;
        if (query.length < 2) return;
        
        // Could implement AJAX search here
    });
</script>
@endpush

@endsection
