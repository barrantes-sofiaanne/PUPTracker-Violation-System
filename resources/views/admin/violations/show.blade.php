@extends('layouts.admin')

@section('title', 'Student Violation Record')

@section('content')

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h3 class="mb-0">Student Violation Record</h3>
            <small class="text-muted">
                View student's violation history and summary
            </small>
        </div>

        <a href="{{ route('admin.violations.index') }}"
           class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i>
            Back
        </a>

    </div>

    {{-- Student Information --}}
    <div class="card shadow-sm mb-4">

        <div class="card-header">
            <h5 class="mb-0">Student Information</h5>
        </div>

        <div class="card-body">

            <div class="row">

                <div class="col-md-6">

                    <table class="table table-borderless">

                        <tr>
                            <th width="180">Student Number</th>
                            <td>{{ $student->student_number }}</td>
                        </tr>

                        <tr>
                            <th>Name</th>
                            <td>
                                {{ $student->last_name }},
                                {{ $student->first_name }}
                                {{ $student->middle_name }}
                            </td>
                        </tr>

                        <tr>
                            <th>Program</th>
                            <td>{{ $student->studentInfo?->program?->program_name ?? '-' }}</td>
                        </tr>

                        <tr>
                            <th>Year</th>
                            <td>{{ $student->studentInfo?->year?->year ?? '-' }}</td>
                        </tr>

                        <tr>
                            <th>Section</th>
                            <td>{{ $student->studentInfo?->section?->section_name ?? '-' }}</td>
                        </tr>

                        <tr>
                            <th>Status</th>
                            <td>{{ $student->studentInfo?->studentStatus?->status_name ?? '-' }}</td>
                        </tr>

                    </table>

                </div>

                <div class="col-md-6 text-center">

                    <h1 class="display-3 text-danger">
                        {{ $violations->count() }}
                    </h1>

                    <h5>Total Violations</h5>

                </div>

            </div>

        </div>

    </div>

<div class="card shadow-sm mb-4">

    <div class="card-header">
        <h5 class="mb-0">Violation Summary</h5>
    </div>

    <div class="card-body">

        <table class="table table-bordered align-middle">

            <thead class="table-light">

                <tr>
                    <th>Category</th>
                    <th>Violation Type</th>
                    <th>Times Committed</th>
                    <th>Current Offense</th>
                    <th>Current Sanction</th>
                </tr>

            </thead>

            <tbody>

            @forelse($summary as $item)

                <tr>

                    <td>{{ $item['category'] ?? '-' }}</td>

                    <td>{{ $item['violation_type'] }}</td>

                    <td>
                        <span class="badge bg-primary">
                            {{ $item['total'] }}
                        </span>
                    </td>

                    <td>
                        <span class="badge bg-warning text-dark">
                            {{ $item['offense_level'] }}
                        </span>
                    </td>

                    <td>{{ $item['sanction'] }}</td>

                </tr>

            @empty

                <tr>
                    <td colspan="5" class="text-center">
                        No violations found.
                    </td>
                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

</div>
<div class="card shadow-sm">

    <div class="card-header">
        <h5 class="mb-0">Violation History</h5>
    </div>

    <div class="card-body">

        <table class="table table-hover">

            <thead>

                <tr>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Violation</th>
                    <th>Description</th>
                    <th>Recorded By</th>
                </tr>

            </thead>

            <tbody>

            @foreach($violations as $violation)

                <tr>

                    <td>
                        {{ $violation->violation_date->format('M d, Y') }}
                    </td>

                    <td>
                        {{ optional($violation->violationType?->violationCategory)->category_name ?? '-' }}
                    </td>

                    <td>{{ $violation->violation_type }}</td>

                    <td>{{ $violation->description }}</td>

                    <td>
                        {{ trim(
    ($violation->recorder?->adminInfo?->firstname ?? '') . ' ' .
    ($violation->recorder?->adminInfo?->middlename ?? '') . ' ' .
    ($violation->recorder?->adminInfo?->lastname ?? '')
) ?: '-' }}
                    </td>

                </tr>

            @endforeach

            </tbody>

        </table>

    </div>

</div>
@endsection