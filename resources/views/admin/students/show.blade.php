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

    {{-- IDP Account Linking (administrator-controlled) --}}
    <div class="card shadow-sm border-0 mt-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong><i class="bi bi-person-badge me-1"></i> PUP ID Account Linking</strong>
            @if($student->idp_subject)
                <span class="badge bg-success">Linked</span>
            @else
                <span class="badge bg-secondary">Not Linked</span>
            @endif
        </div>
        <div class="card-body">
            @if($student->idp_subject)
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <small class="text-muted d-block">IDP Account ID</small>
                        <strong class="text-break">{{ $student->idp_subject }}</strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">IDP Email</small>
                        <strong class="text-break">{{ $student->idp_email ?: '—' }}</strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Linked At</small>
                        <strong>{{ optional($student->idp_connected_at)->format('M d, Y h:i A') ?: '—' }}</strong>
                    </div>
                </div>
                <form action="{{ route('admin.students.unlink-idp', $student->student_number) }}" method="POST"
                      onsubmit="return confirm('Unlink this student from their PUP ID? This will require re-linking before IDP login works again.');">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-link-45deg"></i> Unlink PUP ID
                    </button>
                </form>
            @else
                <p class="text-muted mb-3">
                    Link this student to a verified PUP Identity Provider (IDP) Account ID so they can sign in
                    with <strong>Continue with PUP ID</strong>. The IDP Account ID must be unique — it cannot be
                    linked to more than one student.
                </p>
                <form action="{{ route('admin.students.link-idp', $student->student_number) }}" method="POST" class="row g-3">
                    @csrf
                    <div class="col-md-5">
                        <label class="form-label">IDP Account ID <span class="text-danger">*</span></label>
                        <input type="text" name="idp_subject" class="form-control @error('idp_subject') is-invalid @enderror"
                               placeholder="e.g. ABC123" value="{{ old('idp_subject') }}" required>
                        @error('idp_subject')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">IDP Email (optional)</label>
                        <input type="email" name="idp_email" class="form-control"
                               placeholder="e.g. student@student.pup.edu.ph" value="{{ old('idp_email', $student->email) }}">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-link-45deg"></i> Link
                        </button>
                    </div>
                </form>
            @endif
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