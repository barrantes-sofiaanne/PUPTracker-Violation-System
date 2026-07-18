@extends('layouts.admin')

@section('title', 'Student Violations')

@section('content')

<div class="container-fluid"><div class="card shadow-sm mb-4">

<div class="card-body">

<div class="row align-items-center">

<div class="col-md-2 text-center">

@if($student->profile_photo)

<img
src="{{ asset('storage/'.$student->profile_photo) }}"
class="rounded-circle img-fluid"
style="width:140px;height:140px;object-fit:cover;">

@else

<div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center"
style="width:140px;height:140px;margin:auto;">

<i class="bi bi-person-fill fs-1"></i>

</div>

@endif

</div>

<div class="col-md-10">

<h3>

{{ $student->last_name }},
{{ $student->first_name }}

</h3>

<p class="mb-1">

<strong>Student Number:</strong>

{{ $student->student_number }}

</p>

<p class="mb-1">

<strong>Course:</strong>

{{ optional($student->course)->course_name }}

</p>

<p class="mb-1">

<strong>Year:</strong>

{{ optional($student->year)->year }}

</p>

<p>

<strong>Section:</strong>

{{ optional($student->section)->section_name }}

</p>

</div>

</div>

</div>

</div><div class="row mb-4">

<div class="col-md-3">

<div class="card border-start border-primary border-4">

<div class="card-body">

<h6>Total Violations</h6>

<h2>{{ $statistics['total'] }}</h2>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card border-start border-success border-4">

<div class="card-body">

<h6>Minor</h6>

<h2>{{ $statistics['minor'] }}</h2>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card border-start border-danger border-4">

<div class="card-body">

<h6>Major</h6>

<h2>{{ $statistics['major'] }}</h2>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card border-start border-warning border-4">

<div class="card-body">

<h6>Latest</h6>

<p>

{{ optional($statistics['latest'])->violation_date }}

</p>

</div>

</div>

</div>

</div><div class="card mb-4">

<div class="card-header">

<h5 class="mb-0">

Violation Summary

</h5>

</div>

<div class="card-body">

<table class="table table-bordered">

<thead>

<tr>

<th>Violation</th>

<th>Category</th>

<th>Occurrences</th>

<th>Offense</th>

<th>Sanction</th>

</tr>

</thead>

<tbody>

@foreach($summary as $item)

<tr>

<td>

{{ $item['violation_type']->violation_type }}

</td>

<td>

{{ optional($item['category'])->category_name }}

</td>

<td>

{{ $item['count'] }}

</td>

<td>

{{ $item['offense_level'] }}

</td>

<td>

{{ $item['sanction'] ?? 'No sanction found' }}

</td>

</tr>

@endforeach

</tbody>

</table>

</div>

</div><div class="card">

<div class="card-header d-flex justify-content-between">

<h5 class="mb-0">

Violation History

</h5>

<a
href="{{ route('admin.violations.create', $student->student_number) }}"
class="btn btn-primary">

Add Violation

</a>

</div>

<div class="card-body">

<table class="table table-hover">

<thead>

<tr>

<th>Date</th>

<th>Violation</th>

<th>Category</th>

<th>Description</th>

<th>Recorded By</th>

</tr>

</thead>

<tbody>

@foreach($violations as $violation)

<tr>

<td>

{{ \Carbon\Carbon::parse($violation->violation_date)->format('M d, Y') }}

</td>

<td>

{{ $violation->violation_type }}

</td>

<td>

{{ optional($violation->violationType->category)->category_name }}

</td>

<td>

{{ $violation->description }}

</td>

<td>

{{ optional($violation->recorder)->full_name ?? 'System' }}

</td>

</tr>

@endforeach

</tbody>

</table>

</div>

</div></div>

@endsection</div>