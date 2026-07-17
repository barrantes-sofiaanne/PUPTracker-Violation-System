@extends('layouts.admin')

@section('content')

<div class="container-fluid">

    <a href="{{ route('admin.violations') }}"
       class="btn btn-secondary mb-3">

        ← Back to Violation List

    </a>

    <div class="card mb-3">

        <div class="card-body">

            <h2>

                {{ $student->first_name }}
                {{ $student->middle_name }}
                {{ $student->last_name }}

            </h2>

            <p>

                <strong>Student Number:</strong>

                {{ $student->student_number }}

            </p>

            <span class="badge bg-secondary">

                {{ $student->course?->course_name }}

            </span>

            <span class="badge bg-secondary">

                Year {{ $student->year?->year }}

            </span>

            <span class="badge bg-secondary">

                Section {{ $student->section?->section_name }}

            </span>

        </div>

    </div>
    <div class="card mb-3">

    <div class="card-body">

        <h5>

            Total Violations:

            <span class="text-danger">

                {{ $student->violations->count() }}

            </span>

        </h5>

    </div>

</div>
<div class="card mb-4">

<div class="card-header">

Summary by Violation

</div>

<div class="card-body">

<table class="table table-bordered">

<thead>

<tr>

<th>Category</th>

<th>Violation</th>

<th>Total</th>

</tr>

</thead>

<tbody>

@foreach(

$student->violations

->groupBy('violation_type')

as $type => $records

)

<tr>

<td>

{{ $records->first()->violationType?->category?->category_name }}

</td>

<td>

{{ $type }}

</td>

<td>

{{ $records->count() }}

</td>

</tr>

@endforeach

</tbody>

</table>

</div>

</div>
<div class="card">

<div class="card-header">

Individual Violations

</div>

<div class="card-body">

<table class="table table-striped">

<thead>

<tr>

<th>Violation</th>

<th>Date</th>

<th>Description</th>

<th>Recorded By</th>

</tr>

</thead>

<tbody>

@foreach($student->violations as $violation)

<tr>

<td>

{{ $violation->violation_type }}

</td>

<td>

{{ $violation->violation_date?->format('M d, Y h:i A') }}

</td>

<td>

{{ $violation->description }}

</td>

<td>

{{ $violation->recorder?->first_name }}

{{ $violation->recorder?->last_name }}

</td>

</tr>

@endforeach

</tbody>

</table>

</div>

</div>

</div>

@endsection