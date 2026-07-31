<div class="row mb-4">

    <div class="col-md-12">

        <h5 class="border-bottom pb-2">
            Student Information
        </h5>

        <table class="table table-borderless table-sm">

            <tr>
                <th width="180">Student Number</th>
                <td>{{ $student->student_number }}</td>

                <th width="180">Status</th>
                <td>{{ $student->studentInfo?->studentStatus?->status_name ?? '-' }}</td>
            </tr>

            <tr>
                <th>Name</th>
                <td>
                    {{ $student->last_name }},
                    {{ $student->first_name }}
                    {{ $student->middle_name }}
                </td>

                <th>Program</th>
                <td>{{ $student->studentInfo?->program?->program_name ?? '-' }}</td>
            </tr>

            <tr>
                <th>Year</th>
                <td>{{ $student->studentInfo?->year?->year ?? '-' }}</td>

                <th>Section</th>
                <td>{{ $student->studentInfo?->section?->section_name ?? '-' }}</td>
            </tr>

        </table>

    </div>

</div>
<h5 class="border-bottom pb-2">
    Summary by Violation
</h5>

<div class="table-responsive">

<table class="table table-bordered table-hover align-middle">

    <thead class="table-danger">

        <tr>

            <th>Category</th>

            <th>Violation Type</th>

            <th>Offense Level</th>

            <th>Status</th>

            <th>Remarks</th>

            <th>Disciplinary Sanction</th>

        </tr>

    </thead>

    <tbody>

    @forelse($summary as $item)

        <tr>

            <td>{{ $item['category'] }}</td>

            <td>{{ $item['violation_type'] }}</td>

            <td>
                <span class="badge bg-primary">
                    {{ $item['offense_level'] }}
                </span>
            </td>

            <td>

                @php
                    $status = strtolower($item['status']);
                @endphp

                @if($status == 'approved')

                    <span class="badge bg-success">
                        Approved
                    </span>

                @elseif($status == 'pending')

                    <span class="badge bg-warning text-dark">
                        Pending
                    </span>

                @elseif($status == 'rejected')

                    <span class="badge bg-danger">
                        Rejected
                    </span>

                @elseif($status == 'sanction')

                    <span class="badge bg-info">
                        Sanction
                    </span>

                @else

                    <span class="badge bg-secondary">
                        {{ $item['status'] }}
                    </span>

                @endif

            </td>

            <td>{{ $item['remarks'] }}</td>

            <td>{{ $item['sanction'] }}</td>

        </tr>

    @empty

        <tr>

            <td colspan="6" class="text-center">

                No violations found.

            </td>

        </tr>

    @endforelse

    </tbody>

</table>

</div>
<h5 class="border-bottom pb-2 mt-4">
    Individual Violations Log
</h5>

<div class="table-responsive">

<table class="table table-striped table-hover align-middle">

    <thead class="table-light">

        <tr>

            <th>Date</th>

            <th>Category</th>

            <th>Violation</th>

            <th>Remarks</th>

            <th>Recorded By</th>

            <th>Position</th>

        </tr>

    </thead>

    <tbody>

    @forelse($individualViolations as $violation)

        <tr>

            <td>
                {{ \Carbon\Carbon::parse($violation->violation_date)->format('M d, Y h:i A') }}
            </td>

            <td>
                {{ optional($violation->violationType?->violationCategory)->category_name }}
            </td>

            <td>
                {{ $violation->violation_type }}
            </td>

            <td>
                {{ $violation->description }}
            </td>

            <td>
                {{ $violation->recorded_by_display }}
            </td>

            <td>
                {{ optional($violation->recorder?->adminInfo)->position ?? '-' }}
            </td>

        </tr>

    @empty

        <tr>

            <td colspan="6" class="text-center">

                No violation records found.

            </td>

        </tr>

    @endforelse

    </tbody>

</table>

</div>

<div class="d-flex justify-content-center mt-3">

    {{ $individualViolations->links() }}

</div>