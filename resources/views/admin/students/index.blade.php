@extends('layouts.admin')

@section('content')

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>Student Management</h3>
    </div>

    <form method="GET" class="mb-3">

        <div class="row">

            <div class="col-md-4">
                <input
                    type="text"
                    name="search"
                    class="form-control"
                    placeholder="Search student..."
                    value="{{ request('search') }}">
            </div>

            <div class="col-md-2">
                <button class="btn btn-primary">
                    Search
                </button>
            </div>

        </div>

    </form>

    <div class="card">

        <div class="card-body p-0">

            <table class="table table-bordered table-hover mb-0">

                <thead>

                    <tr>

                        <th>Student Number</th>
                        <th>Name</th>
                        <th>Program</th>
                        <th>Year</th>
                        <th>Section</th>
                        <th>Status</th>
                        <th width="120">Action</th>

                    </tr>

                </thead>

                <tbody>

                @forelse($students as $student)

                    <tr>

                        <td>{{ $student->student_number }}</td>

                        <td>
                            {{ $student->last_name }},
                            {{ $student->first_name }}
                            {{ $student->middle_name }}
                        </td>

                        <td>{{ $student->program?->program_name }}</td>

                        <td>{{ $student->year?->year }}</td>

                        <td>{{ $student->section?->section_name }}</td>

                        <td>
                            <button
                                class="btn btn-primary btn-sm editStudentBtn"
                                data-id="{{ $student->student_number }}"
                                data-first="{{ $student->first_name }}"
                                data-middle="{{ $student->middle_name }}"
                                data-last="{{ $student->last_name }}"
                                data-email="{{ $student->email }}"
                                data-program="{{ $student->program_id }}"
                                data-year="{{ $student->year_id }}"
                                data-section="{{ $student->section_id }}"
                                data-gender="{{ $student->gender_id }}"
                                data-status="{{ $student->status_id }}">
                                Edit
                            </button>
                            <button
                                class="btn btn-danger btn-sm deleteStudentBtn"
                                data-id="{{ $student->student_number }}"
                                data-name="{{ $student->first_name }} {{ $student->last_name }}">
                                Delete
                            </button>
                        </td>
                    </tr>

                @empty

                    <tr>

                        <td colspan="7" class="text-center">

                            No students found.

                        </td>

                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

    </div>

    <div class="mt-3">

        {{ $students->links() }}

    </div>

</div>
<!-- Edit Student Modal -->

<div
    class="modal fade"
    id="editStudentModal"
    tabindex="-1">

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <form
                id="editStudentForm"
                method="POST">

                @csrf
                @method('PUT')

                <div class="modal-header">

                    <h5>Edit Student</h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <div class="mb-3">

                        <label>Student Number</label>

                        <input
                            id="student_number"
                            class="form-control"
                            readonly>

                    </div>

                    <div class="mb-3">

                        <label>First Name</label>

                        <input
                            type="text"
                            name="first_name"
                            id="first_name"
                            class="form-control">

                    </div>

                    <div class="mb-3">

                        <label>Middle Name</label>

                        <input
                            type="text"
                            name="middle_name"
                            id="middle_name"
                            class="form-control">

                    </div>

                    <div class="mb-3">

                        <label>Last Name</label>

                        <input
                            type="text"
                            name="last_name"
                            id="last_name"
                            class="form-control">

                    </div>

                    <div class="mb-3">

                        <label>Email</label>

                        <input
                            type="email"
                            name="email"
                            id="email"
                            class="form-control">

                    </div>

                </div>
<div class="mb-3">
    <label>Program</label>

    <select
        name="program_id"
        id="program_id"
        class="form-select">

        @foreach($programs as $program)

            <option
                value="{{ $program->program_id }}">

                {{ $program->program_name }}

            </option>

        @endforeach

    </select>
</div>
<div class="mb-3">
    <label>Year</label>

    <select
        name="year_id"
        id="year_id"
        class="form-select">

        @foreach($years as $year)

            <option
                value="{{ $year->year_id }}">

                {{ $year->year }}

            </option>

        @endforeach

    </select>
</div>
<div class="mb-3">
    <label>Section</label>

    <select
        name="section_id"
        id="section_id"
        class="form-select">

        @foreach($sections as $section)

            <option
                value="{{ $section->section_id }}">

                {{ $section->section_name }}

            </option>

        @endforeach

    </select>
</div>
<div class="mb-3">
    <label>Gender</label>

    <select
        name="gender_id"
        id="gender_id"
        class="form-select">

        @foreach($genders as $gender)

            <option
                value="{{ $gender->gender_id }}">

                {{ $gender->gender_name }}

            </option>

        @endforeach

    </select>
</div>
<div class="mb-3">
    <label>Status</label>

    <select
        name="status_id"
        id="status_id"
        class="form-select">

        @foreach($statuses as $status)

            <option
                value="{{ $status->status_id }}">

                {{ $status->status_name }}

            </option>

        @endforeach

    </select>
</div>
                <div class="modal-footer">

                    <button
                        class="btn btn-danger">

                        Update Student

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>
<div class="modal fade"
     id="deleteStudentModal"
     tabindex="-1">

    <div class="modal-dialog">

        <div class="modal-content">

            <form
                method="POST"
                id="deleteStudentForm">

                @csrf
                @method('DELETE')

                <div class="modal-header">

                    <h5>Delete Student</h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <p>

                        Are you sure you want to delete

                        <strong id="studentDeleteName"></strong>?

                    </p>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">

                        Cancel

                    </button>

                    <button
                        class="btn btn-danger">

                        Delete

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>
<script>

document.querySelectorAll('.editStudentBtn')
.forEach(function(button){

    button.addEventListener('click',function(){

        document.getElementById('student_number').value =
            this.dataset.id;

        document.getElementById('first_name').value =
            this.dataset.first;

        document.getElementById('middle_name').value =
            this.dataset.middle;

        document.getElementById('last_name').value =
            this.dataset.last;

        document.getElementById('email').value =
            this.dataset.email;
            document.getElementById('program_id').value =
    this.dataset.program;

document.getElementById('year_id').value =
    this.dataset.year;

document.getElementById('section_id').value =
    this.dataset.section;

document.getElementById('gender_id').value =
    this.dataset.gender;

document.getElementById('status_id').value =
    this.dataset.status;

        document.getElementById('editStudentForm').action =
            "/admin/students/" + this.dataset.id;

        new bootstrap.Modal(
            document.getElementById('editStudentModal')
        ).show();

    });

});

document.querySelectorAll('.deleteStudentBtn')
.forEach(function(button){

    button.addEventListener('click', function(){

        document.getElementById('studentDeleteName').textContent =
            this.dataset.name;

        document.getElementById('deleteStudentForm').action =
            "/admin/students/" + this.dataset.id;

        new bootstrap.Modal(
            document.getElementById('deleteStudentModal')
        ).show();

    });

});
</script>

@endsection