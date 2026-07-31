@extends('layouts.admin')

@section('title', 'Violation History')

@section('content')

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3>Violation History</h3>
            <p class="text-muted">Review all student violations recorded in the system.</p>
        </div>
        <a href="{{ route('admin.violations.index') }}" class="btn btn-outline-secondary">Back to Violations</a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="student" class="form-control" placeholder="Search by student number or name" value="{{ request('student') }}">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Student</th>
                            <th>Violation</th>
                            <th>Recorded By</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($violations as $violation)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $violation->violation_date ? \Carbon\Carbon::parse($violation->violation_date)->format('M d, Y') : '-' }}</td>
                                <td>{{ $violation->student->last_name ?? $violation->student_number }}, {{ $violation->student->first_name ?? '' }}</td>
                                <td>{{ optional($violation->violationType)->violation_type ?? $violation->violation_type }}</td>
                                <td>{{ $violation->recorded_by_display }}</td>
                                <td>
                                    <a href="{{ route('admin.violations.show', $violation->student_number) }}" class="btn btn-sm btn-outline-primary">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    No violation history found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex justify-content-center">
        {{ $violations->appends(request()->query())->links() }}
    </div>
</div>

@endsection
