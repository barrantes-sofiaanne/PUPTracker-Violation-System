@extends('layouts.app')

@section('title', 'Student Record')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/student.css') }}">
@endpush

@section('content')

<div class="dashboard-wrapper">

 

    <main class="main-content">


        <div class="container-fluid py-4">

            {{-- Student Information --}}
            <div class="card shadow-sm border-0 mb-4">

                <div class="card-body">

                    <h2 class="fw-bold mb-3">

                        Student Record

                    </h2>

                    <div class="row">

                        <div class="col-md-6">

                            <h4 class="mb-3">

                                {{ $user->first_name }}
                                {{ $user->middle_name }}
                                {{ $user->last_name }}

                            </h4>

                            <p>

                                <strong>Student Number:</strong>

                                {{ $user->student_number }}

                            </p>

                        </div>

                        <div class="col-md-6">

                            <p>

                                <strong>Course:</strong>

                                {{ optional(optional($user->studentInfo)->program)->program_name }}

                            </p>

                            <p>

                                <strong>Year:</strong>

                                {{ optional(optional($user->studentInfo)->year)->year }}

                            </p>

                            <p>

                                <strong>Section:</strong>

                                {{ optional(optional($user->studentInfo)->section)->section_name }}

                            </p>

                        </div>

                    </div>

                </div>

            </div>

            {{-- Tabs --}}
            <ul
                class="nav nav-tabs mb-4"
                id="recordTabs"
                role="tablist">

                <li class="nav-item">

                   <button
    class="nav-link active"
    id="violation-tab"
    data-bs-toggle="tab"
    data-bs-target="#violationRecord"
    type="button"
    role="tab"
    aria-controls="violationRecord"
    aria-selected="true">

    Violation Record

</button>
                </li>

                <li class="nav-item">

                    <button
    class="nav-link"
    id="sanction-tab"
    data-bs-toggle="tab"
    data-bs-target="#sanctionRecord"
    type="button"
    role="tab"
    aria-controls="sanctionRecord"
    aria-selected="false">

    Sanction Record

</button>
                </li>

            </ul>

            <div class="tab-content">

                {{-- =============================== --}}
                {{-- Violation Record --}}
                {{-- =============================== --}}

              <div
    class="tab-pane fade"
    id="violationRecord"
    role="tabpanel">

                    <div class="card shadow-sm border-0 mb-4">

                        <div class="card-header bg-white">

                            <h5 class="mb-0">

                                Summary by Violation

                            </h5>

                        </div>

                        <div class="card-body p-0">

                            <table
                                class="table table-hover align-middle mb-0">

                                <thead>

                                    <tr>

                                        <th>

                                            Category

                                        </th>

                                        <th>

                                            Violation Type

                                        </th>

                                        <th>

                                            Offense Level

                                        </th>

                                        <th>

                                            Status

                                        </th>

                                        <th>

                                            Remarks

                                        </th>

                                        <th>

                                            Disciplinary Sanction

                                        </th>

                                        <th>

                                            Action

                                        </th>

                                    </tr>

                                </thead>

                                <tbody>
                                    @forelse($violationSummary as $summary)

<tr>

    <td>

        {{ $summary['category'] }}

    </td>

    <td>

        {{ $summary['type'] }}

    </td>

    <td>

        {{ $summary['offense_level'] }}

    </td>

    <td>

        @if($summary['violation_status'] === 'Sanction')

            <span class="badge bg-danger">

                Sanction

            </span>

        @else

            <span class="badge bg-warning text-dark">

                Warning

            </span>

        @endif

    </td>

    <td>

        {{ $summary['remarks'] }}

    </td>

    <td style="max-width:300px; white-space:normal;">

        {{ $summary['disciplinary_sanction'] }}

    </td>

    <td>

        @if($summary['workflow_status'] === 'Requested')

            <button
                class="btn btn-secondary btn-sm"
                disabled>

                <i class="bi bi-clock-history"></i>

                Requested

            </button>

        @elseif($summary['workflow_status'] === 'Pending')

            <button
                class="btn btn-secondary btn-sm"
                disabled>

                <i class="bi bi-hourglass-split"></i>

                Pending

            </button>

        @else

            <button
                class="btn btn-primary btn-sm request-sanction-btn"
                data-violation-type-id="{{ $summary['violation_type_id'] }}"

                @if($summary['violation_status'] !== 'Sanction')
                    disabled
                @endif>

                <i class="bi bi-send"></i>

                Request Sanction

            </button>

        @endif

    </td>

</tr>

@empty

<tr>

    <td
        colspan="7"
        class="text-center py-5 text-muted">

        No outstanding violations found.

    </td>

</tr>

@endforelse

</tbody>

</table>

</div>

</div>

{{-- ================================================= --}}
{{-- Individual Violations Log --}}
{{-- ================================================= --}}

<div class="card shadow-sm border-0">

    <div class="card-header bg-white">

        <h5 class="mb-0">

            Individual Violations Log

        </h5>

    </div>

    <div class="card-body p-0">

        <table class="table table-hover align-middle mb-0">

            <thead>

                <tr>

                    <th>

                        Violation Type

                    </th>

                    <th>

                        Date

                    </th>

                    <th>

                        Category

                    </th>

                    <th>

                        Remarks

                    </th>

                </tr>

            </thead>

            <tbody>

                @forelse($violations as $violation)

                    <tr>

                        <td>

                            {{ $violation->violationType->violation_type ?? '-' }}

                        </td>

                        <td>

                            {{ \Carbon\Carbon::parse($violation->violation_date)->format('M d, Y h:i A') }}

                        </td>

                        <td>

                            {{ optional($violation->violationType->violationCategory)->category_name }}

                        </td>

                        <td>

                            {{ $violation->description ?: 'No remarks' }}

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="4"
                            class="text-center py-5">

                            No individual violations found.

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

    <div class="card-footer">

        {{ $violations->links() }}

    </div>

</div>

</div>
{{-- ================================================= --}}
{{-- Sanction Record --}}
{{-- ================================================= --}}

<div
    class="tab-pane fade show active"
    id="sanctionRecord"
    role="tabpanel">

    <div class="card shadow-sm border-0">

        <div class="card-header bg-white">

            <h5 class="mb-0">

                Your Sanction Records

            </h5>

        </div>

        <div class="card-body p-0">

            <table class="table table-hover align-middle mb-0">

                <thead>

                    <tr>

                        <th>

                            Violation Type

                        </th>

                        <th>

                            Status

                        </th>

                        <th>

                            Date Assigned

                        </th>

                        <th>

                            Sanction Details

                        </th>

                        <th>

                            Violation Remarks

                        </th>

                    </tr>

                </thead>

                <tbody>

                    @forelse($sanctionRecords as $record)

                        <tr>

                            <td>

                                {{ optional(optional($record->violation)->violationType)->violation_type ?? 'N/A' }}

                            </td>

                            <td>

                                @switch(strtolower($record->status))

                                    @case('pending')

                                        <span class="badge bg-warning text-dark">

                                            Pending

                                        </span>

                                        @break

                                    @case('completed')

                                        <span class="badge bg-success">

                                            Completed

                                        </span>

                                        @break

                                    @default

                                        <span class="badge bg-secondary">

                                            {{ $record->status }}

                                        </span>

                                @endswitch

                            </td>

                            <td>

                                {{ optional($record->date_assigned)->format('M d, Y h:i A') }}

                            </td>

                            <td style="max-width:320px; white-space:normal;">

                                {{ optional($record->assignedSanction)->disciplinary_sanction ?? 'N/A' }}

                            </td>

                            <td>

                                {{ optional($record->violation)->description ?? 'No remarks' }}

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="5"
                                class="text-center py-5 text-muted">

                                No sanction records found.

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>

</div>

</div>

</main>

</div>
@endsection

@push('scripts')

<script src="{{ asset('js/student-record.js') }}"></script>

@endpush