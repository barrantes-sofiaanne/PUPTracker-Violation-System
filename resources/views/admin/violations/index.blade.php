@extends('layouts.admin')

@section('content')

<div class="container-fluid">

    <div class="card">

        <div class="card-header">
            <h3>Student Violation Records</h3>
        </div>

        <div class="card-body">

            <form method="GET">

                <div class="row mb-3">

                    <div class="col-md-2">

                        <select
                            name="course"
                            class="form-select">

                            <option value="">Course</option>

                            @foreach($courses as $course)

                                <option
                                    value="{{ $course->course_id }}"
                                    {{ request('course') == $course->course_id ? 'selected' : '' }}>

                                    {{ $course->course_name }}

                                </option>

                            @endforeach

                        </select>

                    </div>

                    <div class="col-md-2">

                        <select
                            name="year"
                            class="form-select">

                            <option value="">Year</option>

                            @foreach($years as $year)

                                <option
                                    value="{{ $year->year_id }}"
                                    {{ request('year') == $year->year_id ? 'selected' : '' }}>

                                    {{ $year->year }}

                                </option>

                            @endforeach

                        </select>

                    </div>

                    <div class="col-md-3">

                        <select
                            name="violation_type"
                            class="form-select">

                            <option value="">Violation Type</option>

                            @foreach($violationTypes as $type)

                                <option
                                    value="{{ $type->violation_type }}"
                                    {{ request('violation_type') == $type->violation_type ? 'selected' : '' }}>

                                    {{ $type->violation_type }}

                                </option>

                            @endforeach

                        </select>

                    </div>
<div class="d-flex justify-content-between mb-3">

    <h3>Student Violation Records</h3>

   <button
class="btn btn-danger"
data-bs-toggle="modal"
data-bs-target="#addViolationModal">

+ Add Violation

</button>

</div>
                    <div class="col-md-3">

                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            placeholder="Student Number / Name"
                            value="{{ request('search') }}">

                    </div>

                    <div class="col-md-2">

                        <button class="btn btn-danger w-100">

                            Search

                        </button>

                    </div>

                </div>

            </form>

            <table class="table table-bordered table-hover">

                <thead class="table-danger">

                    <tr>

                        <th>Student Number</th>

                        <th>Name</th>

                        <th>Course</th>

                        <th>Year</th>

                        <th>Section</th>

                        <th>Total Violations</th>

                        <th width="180">Action</th>

                    </tr>

                </thead>

                <tbody>

                    @forelse($students as $student)

                        <tr>

                            <td>

                                {{ $student->student_number }}

                            </td>

                            <td>

                                {{ $student->last_name }},
                                {{ $student->first_name }}

                            </td>

                            <td>

                                {{ $student->course?->course_name }}

                            </td>

                            <td>

                                {{ $student->year?->year }}

                            </td>

                            <td>

                                {{ $student->section?->section_name }}

                            </td>

                            <td>

                                <span class="badge bg-danger">

                                    {{ $student->violations->count() }}

                                </span>

                            </td>

                            <td>

                                <a
                                    href="{{ route('admin.violations.show',$student->student_number) }}"
                                    class="btn btn-primary btn-sm">

                                    More Details

                                </a>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="7"
                                class="text-center">

                                No records found.

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

            {{ $students->withQueryString()->links() }}

        </div>

    </div>

</div>
@include('admin.violations.partials.add-modal')
<script>

document.addEventListener('DOMContentLoaded', function () {

    const student = document.getElementById('studentSelect');

    student.addEventListener('change', function () {

        const option = this.options[this.selectedIndex];

        document.getElementById('studentName').value =
            option.dataset.name || '';

        document.getElementById('studentCourse').value =
            option.dataset.course || '';

        document.getElementById('studentYear').value =
            option.dataset.year || '';

        document.getElementById('studentSection').value =
            option.dataset.section || '';

    });

});
const violationSelect =
document.querySelector(
'select[name="violation_type"]'
);

violationSelect.addEventListener('change', loadOffense);

student.addEventListener('change', loadOffense);

function loadOffense(){

    if(
        !student.value ||
        !violationSelect.value
    ){
        return;
    }

    fetch(
        "{{ route('admin.violations.offense') }}",
        {

            method:'POST',

            headers:{

                'Content-Type':'application/json',

                'X-CSRF-TOKEN':
                "{{ csrf_token() }}"

            },

            body:JSON.stringify({

                student_number:
                student.value,

                violation_type:
                violationSelect.value

            })

        }

    )

    .then(res=>res.json())

    .then(data=>{

        document.getElementById(
            'offenseLevel'
        ).value =
        data.offense + " Offense";

    });

}
</script>
@endsection