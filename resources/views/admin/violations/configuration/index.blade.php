@extends('layouts.admin')

@section('title', 'Student Violations')

@section('content')

<div class="container-fluid py-4">

    {{-- Flash Messages --}}

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">

            {{ session('success') }}

            <button
                class="btn-close"
                data-bs-dismiss="alert">
            </button>

        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">

            {{ session('error') }}

            <button
                class="btn-close"
                data-bs-dismiss="alert">
            </button>

        </div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold">

                Student Violations

            </h2>

            <small class="text-muted">

                View and manage student violations.

            </small>

        </div>

        <button

            class="btn btn-primary"

            data-bs-toggle="modal"

            data-bs-target="#addViolationModal">

            <i class="fas fa-plus me-1"></i>

            Add Violation

        </button>

    </div>
    <div class="card shadow-sm mb-4">

    <div class="card-body">

        <form
            method="GET">

            <div class="row">

                <div class="col-md-4">

                    <label>

                        Search Student

                    </label>

                    <input
                        type="text"
                        class="form-control"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Student Number / Name">

                </div>

                <div class="col-md-2">

                    <label>

                        Course

                    </label>

                    <select
                        class="form-select"
                        name="course">

                        <option value="">

                            All

                        </option>

                        @foreach($courses as $course)

                            <option

                                value="{{ $course->course_id }}"

                                @selected(request('course')==$course->course_id)>

                                {{ $course->course }}

                            </option>

                        @endforeach

                    </select>

                </div>

                <div class="col-md-2">

                    <label>

                        Year

                    </label>

                    <select
                        class="form-select"
                        name="year">

                        <option value="">

                            All

                        </option>

                        @foreach($years as $year)

                            <option

                                value="{{ $year->year_id }}"

                                @selected(request('year')==$year->year_id)>

                                {{ $year->year }}

                            </option>

                        @endforeach

                    </select>

                </div>

                <div class="col-md-3">

                    <label>

                        Violation Category

                    </label>

                    <select
                        class="form-select"
                        name="category">

                        <option value="">

                            All

                        </option>

                        @foreach($categories as $category)

                            <option

                                value="{{ $category->violation_category_id }}"

                                @selected(request('category')==$category->violation_category_id)>

                                {{ $category->category_name }}

                            </option>

                        @endforeach

                    </select>

                </div>

                <div class="col-md-1 d-grid">

                    <label>&nbsp;</label>

                    <button
                        class="btn btn-primary">

                        Search

                    </button>

                </div>

            </div>

        </form>

    </div>

</div>
<div class="card shadow-sm">

    <div class="table-responsive">

        <table class="table table-hover mb-0">

            <thead class="table-light">

            <tr>

                <th width="5%"></th>

                <th>

                    Student Number

                </th>

                <th>

                    Student Name

                </th>

                <th>

                    Course

                </th>

                <th>

                    Year

                </th>

                <th>

                    Total Violations

                </th>

                <th>

                    Action

                </th>

            </tr>

            </thead>

            <tbody>
                @foreach($students as $student)

<tr>

    <td>

        <button

            class="btn btn-sm btn-outline-secondary"

            data-bs-toggle="collapse"

            data-bs-target="#student{{ $student->student_id }}">

            <i class="fas fa-chevron-down"></i>

        </button>

    </td>

    <td>

        {{ $student->student_number }}

    </td>

    <td>

        {{ $student->last_name }},
        {{ $student->first_name }}

    </td>

    <td>

        {{ $student->course->course }}

    </td>

    <td>

        {{ $student->year->year }}

    </td>

    <td>

        {{ $student->violations->count() }}

    </td>

    <td>

        <a

            href="{{ route('admin.violation.show',$student->student_id) }}"

            class="btn btn-primary btn-sm">

            More Details

        </a>

    </td>

</tr>
            </tbody>
            @foreach($students as $student)

<tr>

    <td>

        <button

            class="btn btn-sm btn-outline-secondary"

            data-bs-toggle="collapse"

            data-bs-target="#student{{ $student->student_id }}">

            <i class="fas fa-chevron-down"></i>

        </button>

    </td>

    <td>

        {{ $student->student_number }}

    </td>

    <td>

        {{ $student->last_name }},
        {{ $student->first_name }}

    </td>

    <td>

        {{ $student->course->course }}

    </td>

    <td>

        {{ $student->year->year }}

    </td>

    <td>

        {{ $student->violations->count() }}

    </td>

    <td>

        <a

            href="{{ route('admin.violations.show',$student->student_id) }}"

            class="btn btn-primary btn-sm">

            More Details

        </a>

    </td>

</tr>
</tbody>

</table>

</div>

</div>

</div>
<!-- Add Violation Modal -->
<div class="modal fade"
     id="addViolationModal"
     tabindex="-1">

    <div class="modal-dialog">

        <form
            method="POST"
            action="{{ route('admin.violations.store') }}">

            @csrf

            <div class="modal-content">

                <div class="modal-header">

                    <h5 class="modal-title">

                        Add Violation

                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    {{-- Student --}}

                    <div class="mb-3">

                        <label class="form-label">

                            Student

                        </label>

                       <div class="mb-3">

    <label class="form-label">

        Search Student

    </label>

    <input
        type="text"
        id="studentSearch"
        class="form-control"
        placeholder="Student Number or Name">

</div>

<input
    type="hidden"
    id="studentNumber"
    name="student_number">

<div
    id="studentResults"
    class="list-group mt-2">
</div>

                    </div>

                    {{-- Category --}}

                    <div class="mb-3">

                        <label class="form-label">

                            Violation Category

                        </label>

                        <select
                            class="form-select"
                            id="categorySelect"
                            required>

                            <option value="">

                                Select Category

                            </option>

                            @foreach($categories as $category)

                                <option
                                    value="{{ $category->violation_category_id }}">

                                    {{ $category->category_name }}

                                </option>

                            @endforeach

                        </select>

                    </div>

                    {{-- Violation Type --}}

                    <div class="mb-3">

                        <label class="form-label">

                            Violation Type

                        </label>

                        <select
                            class="form-select"
                            id="violationTypeSelect"
                            name="violation_type"
                            required>

                            <option value="">

                                Select Violation Type

                            </option>

                        </select>

                    </div>
                    <div class="alert alert-info d-none" id="previewBox">

    <strong>Offense Level:</strong>

    <span id="offenseLevel"></span>

    <br>

    <strong>Sanction:</strong>

    <span id="sanctionText"></span>

</div>

                    {{-- Description --}}

                    <div class="mb-3">

                        <label class="form-label">

                            Remarks

                        </label>

                        <textarea
                            class="form-control"
                            rows="4"
                            name="description"></textarea>

                    </div>

                </div>

                <div class="modal-footer">

                    <button
                        class="btn btn-secondary"
                        type="button"
                        data-bs-dismiss="modal">

                        Cancel

                    </button>

                    <button
                        class="btn btn-primary"
                        type="submit">

                        Save

                    </button>

                </div>

            </div>

        </form>

    </div>

</div>
<script>

document.getElementById('categorySelect').addEventListener('change', function () {

    let categoryId = this.value;

    let typeSelect = document.getElementById('violationTypeSelect');

    typeSelect.innerHTML =
        '<option value="">Loading...</option>';

    fetch('/admin/violations/category/' + categoryId)

        .then(response => response.json())

        .then(data => {

            typeSelect.innerHTML =
                '<option value="">Select Violation Type</option>';

            data.forEach(function(type){

                typeSelect.innerHTML +=
                    `<option value="${type.violation_type}">
                        ${type.violation_type}
                    </option>`;

            });

        });

});

function loadPreview() {

    let student =
        document.querySelector('[name="student_number"]').value;

    let violation =
        document.querySelector('[name="violation_type"]').value;

    if(student === '' || violation === '')
        return;

    fetch('/admin/violations/preview', {

        method: 'POST',

        headers: {

            'Content-Type': 'application/json',

            'X-CSRF-TOKEN':
                document.querySelector('meta[name="csrf-token"]').content

        },

        body: JSON.stringify({

            student_number: student,

            violation_type: violation

        })

    })

    .then(r => r.json())

    .then(data => {

        document
            .getElementById('previewBox')
            .classList
            .remove('d-none');

        document
            .getElementById('offenseLevel')
            .innerHTML =
            data.offense_level;

        document
            .getElementById('sanctionText')
            .innerHTML =
            data.sanction ?? 'No sanction found';

    });

}

document
.querySelector('[name="student_number"]')
.addEventListener('change', loadPreview);

document
.querySelector('[name="violation_type"]')
.addEventListener('change', loadPreview);s

const searchInput =
    document.getElementById('studentSearch');

const resultBox =
    document.getElementById('studentResults');

searchInput.addEventListener('keyup', function () {

    let value = this.value;

    if(value.length < 2){

        resultBox.innerHTML='';

        return;

    }

    fetch('/admin/violations/search-student?search=' + value)

    .then(response=>response.json())

    .then(data=>{

        resultBox.innerHTML='';

        data.forEach(function(student){

            resultBox.innerHTML +=

            `<button
                type="button"
                class="list-group-item list-group-item-action"

                data-number="${student.student_number}"

                data-name="${student.last_name}, ${student.first_name}">

                ${student.student_number}

                <br>

                <small>

                    ${student.last_name}, ${student.first_name}

                </small>

            </button>`;

        });

    });

});

resultBox.addEventListener('click', function(e){

    let button = e.target.closest('.list-group-item');

    if(!button)
        return;

    document.getElementById('studentNumber').value =
        button.dataset.number;

    document.getElementById('studentSearch').value =
        button.dataset.name;

    resultBox.innerHTML='';

    loadPreview();

});
</script>
@endsection