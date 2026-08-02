@extends('layouts.student')

@section('title', 'Student Record')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/student.css') }}"> 
@endpush

@section('content')

<div class="container-fluid py-1">

            {{-- Student Information --}}
            <div class="portal-hero mb-4">
                <h2 class="fw-bold mb-2">Student Record</h2>
                <p class="mb-0">Review violation and sanction history with clear offense tracking.</p>
            </div>
            <div class="card portal-card mb-4">

                <div class="card-body">

                    <h2 class="fw-bold mb-3">

                        Student Record

                    </h2>

                    <div class="row align-items-center">
                        <div class="col-md-7">
                            <h4 class="mb-3 fw-bold">
                                {{ $user->first_name }}
                                {{ $user->middle_name }}
                                {{ $user->last_name }}
                            </h4>
                            <p class="mb-2">
                                <strong>Student Number:</strong>
                                {{ $user->student_number }}
                            </p>
                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <span class="portal-badge maroon px-3 py-2">
                                    Program: {{ optional(optional($user->studentInfo)->program)->program_name ?? 'N/A' }}
                                </span>
                                <span class="portal-badge goldenrod px-3 py-2">
                                    Year: {{ optional(optional($user->studentInfo)->year)->year ?? 'N/A' }}
                                </span>
                                <span class="portal-badge muted px-3 py-2">
                                    Section: {{ optional(optional($user->studentInfo)->section)->section_name ?? 'N/A' }}
                                </span>
                            </div>
                        </div>

                        <div class="col-md-5">
                            <div class="portal-muted-box">
                                <p class="mb-2">
                                    <strong>Violation Summary</strong>
                                </p>
                                <p class="mb-0 portal-subtitle">
                                    Track your disciplinary history and request sanction review when needed.
                                </p>
                            </div>
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
    class="tab-pane fade show active"
    id="violationRecord"
    role="tabpanel"
    aria-labelledby="violation-tab">

                    <div class="card shadow-sm border-0 mb-4">

                        <div class="card-header bg-white">

                            <h5 class="mb-0">

                                Summary by Violation

                            </h5>

                        </div>

                        <div class="card-body p-0">

                                <table class="table table-hover align-middle mb-0 portal-table">

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

            <span class="portal-badge warning">

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

        @elseif($summary['workflow_status'] === 'Approved')

            <button
                class="btn btn-success btn-sm"
                disabled>

                <i class="bi bi-check-circle"></i>

                Approved

            </button>

        @else

            @if($summary['violation_status'] !== 'Sanction')

                <span class="badge bg-secondary-subtle text-secondary-emphasis border">
                    No Request Needed
                </span>

            @else

                <button
                    class="btn portal-btn btn-sm request-sanction-btn"
                    data-violation-type-id="{{ $summary['violation_type_id'] }}">

                    <i class="bi bi-send"></i>

                    Request Sanction

                </button>

            @endif

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

        <table class="table table-hover align-middle mb-0 portal-table">

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

                            {{ $violation->violation_type_display }}

                        </td>

                        <td>

                            {{ \Carbon\Carbon::parse($violation->violation_date)->format('M d, Y h:i A') }}

                        </td>

                        <td>

                            {{ $violation->violation_category_display }}

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
    class="tab-pane fade"
    id="sanctionRecord"
    role="tabpanel"
    aria-labelledby="sanction-tab">

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

                                        <span class="portal-badge warning">

                                            Pending

                                        </span>

                                        @break

                                    @case('completed')

                                        <span class="portal-badge success">

                                            Completed

                                        </span>

                                        @break

                                    @default

                                        <span class="portal-badge muted">

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

<div class="modal fade" id="requestSanctionConfirmModal" tabindex="-1" aria-labelledby="requestSanctionConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requestSanctionConfirmModalLabel">Confirm Sanction Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to request this disciplinary sanction?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn portal-btn" id="confirmRequestSanctionBtn">
                    <i class="bi bi-send"></i>
                    Yes, Request Sanction
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')

<script src="{{ asset('js/student-record.js') }}"></script>

@endpush