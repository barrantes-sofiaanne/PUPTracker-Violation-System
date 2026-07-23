@extends('layouts.admin')

@section('title', 'Record Violation')

@section('content')

<div class="container-fluid">

<div class="card shadow-sm">

<div class="card-header">

<h4 class="mb-0">

Record New Violation

</h4>

</div>

<div class="card-body"><div class="card mb-4">

<div class="card-header">

Student Information

</div>

<div class="card-body">

<div class="row">

<div class="col-md-6">

<label class="fw-bold">

Student Number

</label>

<p>

{{ $student->student_number }}

</p>

</div>

<div class="col-md-6">

<label class="fw-bold">

Student Name

</label>

<p>

{{ $student->last_name }},
{{ $student->first_name }}

</p>

</div>

<div class="col-md-4">

<label class="fw-bold">

Course

</label>

<p>

{{ optional($student->course)->course_name }}

</p>

</div>

<div class="col-md-4">

<label class="fw-bold">

Year

</label>

<p>

{{ optional($student->year)->year }}

</p>

</div>

<div class="col-md-4">

<label class="fw-bold">

Section

</label>

<p>

{{ optional($student->section)->section_name }}

</p>

</div>

</div>

</div>

</div><form
method="POST"
action="{{ route('admin.violations.store') }}"
id="violationForm">

@csrf

<input
type="hidden"
name="student_number"
value="{{ $student->student_number }}"><div class="mb-3">

<label class="form-label">

Violation Type

</label>

<select
name="violation_type"
id="violation_type"
class="form-select"
required>

<option value="">

Select Violation

</option>

@foreach($violationTypes as $type)

<option value="{{ $type->violation_type }}">

{{ $type->violation_type }}

</option>

@endforeach

</select>

</div><div class="mb-3">

<label class="form-label">

Violation Date

</label>

<input
type="date"
name="violation_date"
class="form-control"
value="{{ now()->format('Y-m-d') }}"
required>

</div><div class="mb-4">

<label class="form-label">

Description

</label>

<textarea
name="description"
rows="4"
class="form-control"></textarea>

</div><div class="card mb-4">

<div class="card-header">

Violation Preview

</div>

<div class="card-body">

<div class="row">

<div class="col-md-4">

<label class="fw-bold">

Category

</label>

<p id="previewCategory">

-

</p>

</div>

<div class="col-md-4">

<label class="fw-bold">

Offense Level

</label>

<p id="previewOffense">

-

</p>

</div>

<div class="col-md-4">

<label class="fw-bold">

Sanction

</label>

<p id="previewSanction">

-

</p>

</div>

</div>

</div>

</div><div class="d-flex justify-content-end gap-2">

<a
href="{{ route('admin.violations.show',$student->student_number) }}"
class="btn btn-secondary">

Cancel

</a>

<button
type="submit"
class="btn btn-primary">

Save Violation

</button>

</div>

</form></div>

</div>

</div>

@endsection

@push('scripts')

<script src="{{ asset('js/admin/violations.js') }}"></script>

@endpush