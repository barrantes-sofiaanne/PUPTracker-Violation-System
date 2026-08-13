@extends('layouts.admin')

@section('title', 'AI-Assisted Graduation Disciplinary Review')

@section('content')

<div class="container-fluid">

    <div class="page-header-modern d-flex justify-content-between align-items-center flex-wrap mb-4">

        <div>
            <h2 class="fw-bold mb-1">
                AI-Assisted Graduation Disciplinary Review
            </h2>
            <p class="mb-0">
                Screen graduating students' disciplinary records and generate AI-assisted summaries.
            </p>
        </div>

        <div>
            <form action="{{ route('admin.graduation-review.generate-batch') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-stars"></i>
                    Generate AI Review (Batch)
                </button>
            </form>
        </div>

    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('info'))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            {{ session('info') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->has('ai'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle"></i>
            {{ $errors->first('ai') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Filters --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.graduation-review.index') }}" class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Program</label>
                    <select name="program_id" class="form-select">
                        <option value="">All Programs</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->program_id }}" @selected((int) $programFilter === (int) $program->program_id)>
                                {{ $program->program_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Year</label>
                    <select name="year_id" class="form-select">
                        <option value="">All Years</option>
                        @foreach ($years as $year)
                            <option value="{{ $year->year_id }}" @selected((int) $yearFilter === (int) $year->year_id)>
                                {{ $year->year }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Batch report summary --}}
    <div class="row mb-4">

        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <small class="text-muted">Students Reviewed</small>
                    <h2 class="fw-bold mb-0">{{ $report['total'] }}</h2>
                </div>
            </div>
        </div>

        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <small class="text-muted">No Disciplinary History</small>
                    <h2 class="fw-bold text-success mb-0">{{ $report['no_history'] }}</h2>
                </div>
            </div>
        </div>

        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <small class="text-muted">All Sanctions Completed</small>
                    <h2 class="fw-bold text-primary mb-0">{{ $report['all_completed'] }}</h2>
                </div>
            </div>
        </div>

        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <small class="text-muted">Outstanding Sanctions</small>
                    <h2 class="fw-bold text-danger mb-0">{{ $report['outstanding'] }}</h2>
                </div>
            </div>
        </div>

        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <small class="text-muted">Manual Review Recommended</small>
                    <h2 class="fw-bold text-warning mb-0">{{ $report['manual_review'] }}</h2>
                </div>
            </div>
        </div>

        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center d-flex flex-column justify-content-center h-100">
                    <small class="text-muted d-block mb-1">AI Status</small>
                    <span class="badge {{ config('graduation_review.ai.enabled') ? 'bg-success' : 'bg-secondary' }}">
                        {{ config('graduation_review.ai.enabled') ? 'AI Configured' : 'AI Not Configured' }}
                    </span>
                </div>
            </div>
        </div>

    </div>

    {{-- Batch report table --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <strong>Graduating Students — Disciplinary Screening</strong>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Student</th>
                        <th>Program</th>
                        <th>Violations</th>
                        <th>Outstanding</th>
                        <th>Review Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($screened as $student)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $student['student_name'] }}</div>
                                <small class="text-muted">{{ $student['student_number'] }}</small>
                            </td>
                            <td>{{ $student['program'] }}</td>
                            <td>{{ $student['total_violations'] }}</td>
                            <td>
                                @if ((int) $student['outstanding_sanctions'] > 0)
                                    <span class="badge bg-danger">{{ $student['outstanding_sanctions'] }}</span>
                                @else
                                    <span class="text-muted">0</span>
                                @endif
                            </td>
                            <td>
                                @if ((int) $student['total_violations'] === 0)
                                    <span class="badge bg-success">No Immediate Concern</span>
                                @elseif ((int) $student['outstanding_sanctions'] > 0)
                                    <span class="badge bg-danger">Outstanding Sanction Review</span>
                                @elseif ($student['repeated_violations_exist'])
                                    <span class="badge bg-warning text-dark">Manual Review Recommended</span>
                                @else
                                    <span class="badge bg-success">No Immediate Concern</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.graduation-review.show', $student['student_number']) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> Review
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                No graduating students found for the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

@endsection
