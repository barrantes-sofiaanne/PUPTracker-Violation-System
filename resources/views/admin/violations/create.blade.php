@extends('layouts.admin')

@section('content')

<div class="container-fluid">

<div class="card">

<div class="card-header">

<h3>Record Student Violation</h3>

</div>

<div class="card-body">

<form
method="POST"
action="{{ route('admin.violations.store') }}">

@csrf

<div class="mb-3">

<label>

Student

</label>

<select
name="student_number"
class="form-select">

@foreach($students as $student)

<option
value="{{ $student->student_number }}">

{{ $student->student_number }}

-

{{ $student->last_name }},

{{ $student->first_name }}

</option>

@endforeach

</select>

</div>

<div class="mb-3">

<label>

Violation

</label>

<select
name="violation_type"
class="form-select">

@foreach($violationTypes as $type)

<option
value="{{ $type->violation_type }}">

{{ $type->violation_type }}

</option>

@endforeach

</select>

</div>

<div class="mb-3">

<label>

Description

</label>

<textarea
name="description"
class="form-control"></textarea>

</div>

<button
class="btn btn-danger">

Save Violation

</button>

</form>

</div>

</div>

</div>

@endsection