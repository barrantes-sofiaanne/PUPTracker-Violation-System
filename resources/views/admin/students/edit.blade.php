@extends('layouts.admin')

@section('title', 'Edit Student')

@section('content')

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3>Edit Student</h3>
            <p class="text-muted">Update the student profile and enrollment details.</p>
        </div>
        <a href="{{ route('admin.students') }}" class="btn btn-outline-secondary">Back to Students</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.students.update', $student->student_number) }}">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Student Number</label>
                        <input type="text" class="form-control" value="{{ $student->student_number }}" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">First Name</label>
                        <input type="text" name="first_name" class="form-control" value="{{ old('first_name', $student->first_name) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Middle Name</label>
                        <input type="text" name="middle_name" class="form-control" value="{{ old('middle_name', $student->middle_name) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" class="form-control" value="{{ old('last_name', $student->last_name) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email', $student->email) }}" required>
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

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="{{ route('admin.students') }}" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
