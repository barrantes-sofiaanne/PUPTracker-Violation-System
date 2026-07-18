@extends('layouts.admin')

@section('title','Violation Management')

@section('content')

<div class="container-fluid">

<div class="card shadow-sm">

<div class="card-header">

<h4 class="mb-0">

Violation Management

</h4>

</div>

<div class="card-body">
    <form method="GET">

<div class="row g-3">

<div class="col-md-4">

<input
type="text"
name="search"
class="form-control"
placeholder="Student Number or Name"
value="{{ request('search') }}"
>

</div>

<div class="col-md-2">

<select
name="course"
class="form-select">

<option value="">Course</option>

@foreach($courses as $course)

<option
value="{{ $course->course_id }}"
@selected(request('course')==$course->course_id)
>

{{ $course->course_name }}

</option>

@endforeach

</select>

</div>
<div class="col-md-2">

<button
class="btn btn-primary w-100">

Search

</button>

</div>

<div class="col-md-2">

<a
href="{{ route('admin.violations.index') }}"
class="btn btn-secondary w-100">

Reset

</a>

</div>

</div>

</form>
<table class="table table-hover align-middle">

<thead>

<tr>

<th>Student No.</th>

<th>Name</th>

<th>Course</th>

<th>Total Violations</th>

<th></th>

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

{{ optional($student->course)->course_name }}

</td>

<td>

{{ $student->violations->count() }}

</td>

<td>

<a
href="{{ route(
'admin.violations.show',
$student->student_number
) }}"
class="btn btn-sm btn-primary">

View

</a>

</td>

</tr>

@empty

<tr>

<td colspan="5">

No records found.

</td>

</tr>

@endforelse

</tbody>

</table>
{{ $students->links() }}
</div>

</div>

</div>

@endsection
</div>