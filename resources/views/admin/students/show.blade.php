@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1">Student Profile</h3>
            <p class="text-muted mb-0">Review the student’s personal and academic details.</p>
        </div>
        <a href="{{ route('admin.students') }}" class="btn btn-outline-secondary">Back to Students</a>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <strong>Personal Information</strong>
                </div>
                <div class="card-body">
                    <p class="mb-3">
                        <strong>Student Number</strong><br>
                        {{ $student->student_number }}
                    </p>
                    <p class="mb-3">
                        <strong>Name</strong><br>
                        {{ $student->last_name }}, {{ $student->first_name }} {{ $student->middle_name }}
                    </p>
                    <p class="mb-3">
                        <strong>Email</strong><br>
                        {{ $student->email }}
                    </p>
                    <p class="mb-0">
                        <strong>Gender</strong><br>
                        {{ $student->gender?->gender_name ?? '—' }}
                    </p>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <strong>Academic Information</strong>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <p class="mb-0">
                                <strong>Program</strong><br>
                                {{ $student->program?->program_name ?? '—' }}
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-0">
                                <strong>Year</strong><br>
                                {{ $student->year?->year ?? '—' }}
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-0">
                                <strong>Section</strong><br>
                                {{ $student->section?->section_name ?? '—' }}
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-0">
                                <strong>Status</strong><br>
                                {{ $student->status?->status_name ?? '—' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mt-4">
        <div class="card-header bg-white">
            <strong>Violation History</strong>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Violation</th>
                            <th>Category</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($student->violations as $violation)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($violation->violation_date)->format('M d, Y') }}</td>
                                <td>{{ $violation->violationType?->violation_type ?? '—' }}</td>
                                <td>{{ $violation->violationType?->violationCategory?->category_name ?? '—' }}</td>
                                <td>{{ $violation->description }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No violation records.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection