@extends('layouts.admin')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin.css') }}">
@endpush

@section('title', 'Admin Dashboard')

@section('content')

<h2 class="mb-4">
    Admin Dashboard
</h2>

<div class="row g-4">

    <div class="col-lg-3">

        <div class="card shadow-sm">

            <div class="card-body text-center">

                <h6>Total Students</h6>

                <h2>{{ $totalStudents }}</h2>

            </div>

        </div>

    </div>

    <div class="col-lg-3">

        <div class="card shadow-sm">

            <div class="card-body text-center">

                <h6>Total Violations</h6>

                <h2>{{ $totalViolations }}</h2>

            </div>

        </div>

    </div>

    <div class="col-lg-3">

        <div class="card shadow-sm">

            <div class="card-body text-center">

                <h6>Pending Requests</h6>

                <h2>{{ $pendingRequests }}</h2>

            </div>

        </div>

    </div>

    <div class="col-lg-3">

        <div class="card shadow-sm">

            <div class="card-body text-center">

                <h6>Announcements</h6>

                <h2>{{ $announcementCount }}</h2>

            </div>

        </div>

    </div>

</div>

<div class="card mt-4 shadow-sm">

    <div class="card-header">

        <h5 class="mb-0">
            Recent Violations
        </h5>

    </div>

    <div class="card-body">

        <table class="table">

            <thead>

                <tr>

                    <th>Student</th>

                    <th>Violation</th>

                    <th>Date</th>

                </tr>

            </thead>

            <tbody>

                @forelse($recentViolations as $violation)

                <tr>

                    <td>{{ $violation->student_number }}</td>

                    <td>{{ $violation->violationType->violation_type ?? '-' }}</td>

                    <td>{{ $violation->violation_date }}</td>

                </tr>

                @empty

                <tr>

                    <td colspan="3" class="text-center">

                        No recent violations.

                    </td>

                </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>
@push('scripts')
<script src="{{ asset('assets/js/admin.js') }}"></script>
@endpush
@endsection