@extends('layouts.admin')

@section('content')

<div class="container-fluid">

    <h2 class="mb-4">
        Student Profile
    </h2>

    <div class="row">

        <div class="col-lg-4">

            <div class="card shadow-sm">

                <div class="card-header">

                    Personal Information

                </div>

                <div class="card-body">

                    <p>
                        <strong>Student Number</strong><br>
                        {{ $student->student_number }}
                    </p>

                    <p>
                        <strong>Name</strong><br>

                        {{ $student->last_name }},
                        {{ $student->first_name }}
                        {{ $student->middle_name }}

                    </p>

                    <p>
                        <strong>Email</strong><br>

                        {{ $student->email }}

                    </p>

                    <p>
                        <strong>Gender</strong><br>

                        {{ $student->gender?->gender_name }}

                    </p>

                </div>

            </div>

        </div>

        <div class="col-lg-8">

            <div class="card shadow-sm">

                <div class="card-header">

                    Academic Information

                </div>

                <div class="card-body">

                    <div class="row">

                        <div class="col-md-6">

                            <p>

                                <strong>Course</strong><br>

                                {{ $student->course?->course_name }}

                            </p>

                        </div>

                        <div class="col-md-6">

                            <p>

                                <strong>Year</strong><br>

                                {{ $student->year?->year }}

                            </p>

                        </div>

                        <div class="col-md-6">

                            <p>

                                <strong>Section</strong><br>

                                {{ $student->section?->section_name }}

                            </p>

                        </div>

                        <div class="col-md-6">

                            <p>

                                <strong>Status</strong><br>

                                {{ $student->status?->status_name }}

                            </p>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <br>

    <div class="card shadow-sm">

        <div class="card-header">

            Violation History

        </div>

        <div class="card-body">

            <table class="table table-hover">

                <thead>

                    <tr>

                        <th>Date</th>

                        <th>Violation</th>

                        <th>Category</th>

                        <th>Description</th>

                    </tr>

                </thead>

                <tbody>

                @forelse($student->violations as $violation)

                    <tr>

                        <td>

                            {{ \Carbon\Carbon::parse($violation->violation_date)->format('M d, Y') }}

                        </td>

                        <td>

                            {{ $violation->violationType?->violation_type }}

                        </td>

                        <td>

                            {{ $violation->violationType?->violationCategory?->category_name }}

                        </td>

                        <td>

                            {{ $violation->description }}

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td colspan="4" class="text-center">

                            No violation records.

                        </td>

                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>

@endsection