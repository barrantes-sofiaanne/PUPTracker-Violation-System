@extends('layouts.admin')

@section('title', 'Edit Student')

@section('content')

<div class="container-fluid">
    <x-admin.page-header title="Edit Student" description="Update the student profile and enrollment details.">
        <x-slot name="actions">
            <a href="{{ route('admin.students') }}" class="admin-btn admin-btn-neutral">
                <i class="bi bi-arrow-left"></i>
                Back to Students
            </a>
        </x-slot>
    </x-admin.page-header>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.students.update', $student->student_number) }}" data-submit-guard="Saving...">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Student Number</label>
                        <input type="text" class="form-control" value="{{ $student->student_number }}" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-control" value="{{ $student->first_name ?? '—' }}" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Middle Name</label>
                        <input type="text" class="form-control" value="{{ $student->middle_name ?? '—' }}" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control" value="{{ $student->last_name ?? '—' }}" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Name Suffix</label>
                        <input type="text" class="form-control" value="{{ $student->name_suffix ?: '—' }}" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" value="{{ $student->email ?? '—' }}" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Program</label>
                        <select name="program_id" class="form-select" required>
                            <option value="">Choose program</option>
                            @foreach($programs as $program)
                                <option value="{{ $program->program_id }}" {{ old('program_id', $student->program_id) == $program->program_id ? 'selected' : '' }}>
                                    {{ $program->program_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Year</label>
                        <select name="year_id" class="form-select" required>
                            <option value="">Choose year</option>
                            @foreach($years as $year)
                                <option value="{{ $year->year_id }}" {{ old('year_id', $student->year_id) == $year->year_id ? 'selected' : '' }}>
                                    {{ $year->year }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Section</label>
                        <select name="section_id" class="form-select" required>
                            <option value="">Choose section</option>
                            @foreach($sections as $section)
                                <option value="{{ $section->section_id }}" {{ old('section_id', $student->section_id) == $section->section_id ? 'selected' : '' }}>
                                    {{ $section->section_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Gender</label>
                        <select name="gender_id" class="form-select" required>
                            <option value="">Choose gender</option>
                            @foreach($genders as $gender)
                                <option value="{{ $gender->gender_id }}" {{ old('gender_id', $student->gender_id) == $gender->gender_id ? 'selected' : '' }}>
                                    {{ $gender->gender_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status_id" class="form-select" required>
                            <option value="">Choose status</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status->status_id }}" {{ old('status_id', $student->status_id) == $status->status_id ? 'selected' : '' }}>
                                    {{ $status->status_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-3">
                    <div class="alert alert-info small mb-0 py-2">
                        <i class="bi bi-shield-lock me-1"></i>
                        Name, suffix, and email are managed by the PUP IDP account and cannot be edited here. Passwords are also managed by the PUP Identity Provider (IDP).
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="admin-btn admin-btn-primary">
                        <i class="bi bi-check2" aria-hidden="true"></i> Save Changes
                    </button>
                    <a href="{{ route('admin.students') }}" class="admin-btn admin-btn-neutral ms-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
