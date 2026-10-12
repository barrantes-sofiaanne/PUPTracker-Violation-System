@extends('layouts.student')

@section('title', 'Student Record')

@php
    $violationCategories = $violationTypes
        ->pluck('violationCategory')
        ->filter()
        ->unique('violation_category_id')
        ->sortBy('category_name')
        ->values();
@endphp

@push('styles')

{{-- Student Record-specific styles --}}
<link rel="stylesheet" href="{{ asset('css/student.css') }}">

{{-- Re-apply the shared Student Portal UI after student.css --}}
<link rel="stylesheet" href="{{ asset('css/portal.css') }}">
<link rel="stylesheet" href="{{ asset('css/user-menu.css') }}">

<style>
/* =========================================================
   Individual Violations Log
   Consolidated responsive styling
   ========================================================= */

.individual-log-card {
    width: 100%;
    height: auto !important;
    min-height: 0 !important;
    margin-bottom: 1.5rem !important;
    padding-bottom: 0 !important;
    overflow: hidden;
    background: #fff;
    border: 1px solid rgba(112, 0, 0, 0.10) !important;
    border-radius: 1.1rem !important;
    box-shadow: 0 12px 34px rgba(43, 24, 24, 0.07) !important;
}

.individual-log-card > .card-body {
    height: auto !important;
    min-height: 0 !important;
    flex: initial !important;
}

.individual-log-card > .card-body.p-0 {
    padding: 0 !important;
}

/* Header */

.individual-log-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.25rem 1.5rem !important;
    color: #fff !important;
    background: linear-gradient(
        115deg,
        #650000 0%,
        #8b0808 58%,
        #a21b1b 100%
    ) !important;
    border: 0 !important;
    opacity: 1 !important;
}

.individual-log-heading {
    display: flex;
    align-items: center;
    gap: 0.9rem;
    min-width: 0;
}

.individual-log-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.8rem;
    height: 2.8rem;
    flex: 0 0 2.8rem;
    border: 1px solid rgba(255, 255, 255, 0.24);
    border-radius: 0.9rem;
    background: rgba(255, 255, 255, 0.12);
    font-size: 1.25rem;
}

.individual-log-title,
.individual-log-header .individual-log-title {
    margin: 0;
    color: #fff !important;
    font-size: 1.2rem;
    font-weight: 750;
    letter-spacing: -0.02em;
}

.individual-log-subtitle,
.individual-log-header .individual-log-subtitle {
    margin: 0.22rem 0 0;
    color: rgba(255, 255, 255, 0.85) !important;
    font-size: 0.84rem;
    line-height: 1.4;
}

.individual-log-count {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    flex: 0 0 auto;
    padding: 0.45rem 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
    font-size: 0.78rem;
    font-weight: 700;
}

/* Filters */

.individual-log-filters-wrap {
    padding: 1.1rem 1.25rem !important;
    border-bottom: 1px solid #eee2d5;
    background: #fffdf9;
}

.individual-log-filters {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.85rem;
    align-items: end;
}

.individual-filter-field {
    min-width: 0;
}

.individual-filter-field label,
.individual-status-filter-row label {
    display: block;
    margin-bottom: 0.4rem;
    color: #5d1515;
    font-size: 0.78rem;
    font-weight: 750;
}

.individual-filter-field .form-select,
.individual-filter-field .form-control,
.individual-status-filter-row .form-select {
    width: 100%;
    min-width: 0;
    min-height: 42px;
    border-color: #e5d7c8;
    border-radius: 0.65rem;
    font-size: 0.88rem;
}

.individual-filter-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    grid-column: 1 / -1;
}

.individual-filter-apply {
    color: #fff;
    background: #800909;
    border: 1px solid #800909;
    border-radius: 0.65rem;
    font-weight: 700;
}

.individual-filter-apply:hover {
    color: #fff;
    background: #650606;
}

.individual-filter-clear {
    border: 1px solid #d9c8b8;
    color: #5d1515;
    border-radius: 0.65rem;
    font-weight: 650;
}

.individual-status-filter-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-top: 1rem;
}

.individual-status-filter-row label {
    margin: 0;
    white-space: nowrap;
}

.individual-status-filter-row .form-select {
    max-width: 260px;
}

/* Table scrolling container */

.individual-log-card .individual-table-responsive,
.individual-table-responsive {
    display: block;
    width: 100%;
    max-width: 100%;
    height: auto !important;
    min-height: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
    overflow-x: auto !important;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior-x: contain;
}

/* Table layout.
   The seven columns are deliberately given enough space.
   Horizontal scrolling is preferable to breaking every word. */

.individual-log-card .individual-violations-table,
.individual-violations-table {
    width: 1500px !important;
    min-width: 1500px !important;
    max-width: none !important;
    margin: 0 !important;
    table-layout: fixed !important;
    border-collapse: separate;
    border-spacing: 0;
}

/* Column widths:
   1 = violation type
   2 = date
   3 = category
   4 = status
   5 = remarks
   6 = attachments
   7 = action
*/

.individual-violations-table .violation-type-col,
.individual-violations-table th:nth-child(1),
.individual-violations-table td:nth-child(1) {
    width: 260px;
}

.individual-violations-table .violation-date-col,
.individual-violations-table th:nth-child(2),
.individual-violations-table td:nth-child(2) {
    width: 180px;
}

.individual-violations-table .category-col,
.individual-violations-table th:nth-child(3),
.individual-violations-table td:nth-child(3) {
    width: 260px;
}

.individual-violations-table .status-col,
.individual-violations-table th:nth-child(4),
.individual-violations-table td:nth-child(4) {
    width: 175px;
}

.individual-violations-table .remarks-col,
.individual-violations-table th:nth-child(5),
.individual-violations-table td:nth-child(5) {
    width: 210px;
}

.individual-violations-table .attachments-col,
.individual-violations-table th:nth-child(6),
.individual-violations-table td:nth-child(6) {
    width: 205px;
    min-width: 0;
}

.individual-violations-table .individual-actions-col,
.individual-violations-table th:nth-child(7),
.individual-violations-table td:nth-child(7) {
    width: 210px;
    min-width: 0;
    text-align: center;
}

/* Header cells */

.individual-violations-table thead th {
    box-sizing: border-box;
    padding: 0.95rem 1rem !important;
    color: #6d1515;
    background: #fff7e2;
    border-top: 1px solid #f4e4bd !important;
    border-bottom: 1px solid #f0e3c8 !important;
    font-size: 0.78rem !important;
    font-weight: 800;
    line-height: 1.35 !important;
    text-align: left;
    text-transform: uppercase;
    letter-spacing: 0.045em;
    vertical-align: middle;
    white-space: normal !important;
    word-break: normal !important;
    overflow-wrap: break-word !important;
    hyphens: none !important;
}

/* Body cells */

.individual-violations-table tbody td {
    box-sizing: border-box;
    padding: 1rem !important;
    color: #34313a;
    background: #fff;
    border-bottom: 1px solid #f0eeee;
    font-size: 0.92rem;
    line-height: 1.5;
    text-align: left;
    vertical-align: middle;
    white-space: normal !important;
    word-break: normal !important;
    overflow-wrap: break-word !important;
    hyphens: none !important;
}

.individual-violations-table tbody tr {
    transition: background-color 0.18s ease;
}

.individual-violations-table tbody tr:hover td {
    background: #fffdf8;
}

.individual-violations-table tbody tr:last-child td {
    border-bottom: 0;
}

/* Dates */

.individual-violations-table .violation-date-col {
    white-space: normal !important;
    font-variant-numeric: tabular-nums;
}

/* Status badges: do not split Confirmed into separate fragments */

.individual-violations-table .status-col {
    text-align: center !important;
}

.individual-violations-table .status-col .portal-badge,
.individual-violations-table .status-col .badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    width: auto;
    max-width: 100%;
    padding: 0.55rem 0.8rem;
    font-size: 0.82rem !important;
    font-weight: 700;
    line-height: 1.25 !important;
    text-align: center;
    white-space: nowrap !important;
    word-break: keep-all !important;
    overflow-wrap: normal !important;
    hyphens: none !important;
}

.individual-violations-table .status-col .portal-badge i,
.individual-violations-table .status-col .badge i {
    flex: 0 0 auto;
}

/* Remarks */

.individual-violations-table .remarks-col {
    overflow-wrap: break-word !important;
    word-break: normal !important;
}

/* Attachments */

.individual-violations-table .attachments-col {
    overflow-wrap: normal !important;
    word-break: normal !important;
}

.individual-violations-table .attachments-col a {
    display: inline-flex;
    align-items: flex-start;
    gap: 0.35rem;
    max-width: 100%;
    white-space: normal !important;
    overflow-wrap: anywhere !important;
    word-break: normal !important;
    line-height: 1.4;
}

.individual-violations-table .attachments-col a i {
    flex: 0 0 auto;
    margin-top: 0.15rem;
}

.individual-violations-table .attachments-col .text-muted {
    display: inline-block;
    line-height: 1.45;
}

/* Sanction action buttons */

.individual-violations-table .individual-actions-col {
    text-align: center;
    vertical-align: middle;
}

.individual-violations-table .individual-actions-col > div {
    width: 100%;
    max-width: 190px;
    margin: 0 auto;
}

.individual-violations-table .individual-actions-col .btn,
.individual-violations-table .individual-actions-col .sanction-state {
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    width: 100%;
    max-width: 190px;
    min-width: 0;
    min-height: 44px;
    margin: 0;
    padding: 0.7rem 0.8rem !important;
    border-radius: 0.75rem;
    font-size: 0.85rem !important;
    font-weight: 700;
    line-height: 1.3 !important;
    text-align: center;
    white-space: normal !important;
    word-break: normal !important;
    overflow-wrap: break-word !important;
    hyphens: none !important;
    transition:
        transform 0.18s ease,
        box-shadow 0.18s ease,
        background 0.18s ease;
}

.individual-violations-table .individual-actions-col .btn i,
.individual-violations-table .individual-actions-col .sanction-state i {
    flex: 0 0 auto;
}

.individual-violations-table .individual-actions-col .request-sanction-btn {
    color: #fff;
    background: linear-gradient(120deg, #710000, #960d0d);
    border: 1px solid #780000;
    box-shadow: 0 5px 13px rgba(120, 0, 0, 0.13);
}

.individual-violations-table .individual-actions-col .request-sanction-btn:hover {
    color: #fff;
    background: #5f0000;
    transform: translateY(-1px);
    box-shadow: 0 8px 18px rgba(120, 0, 0, 0.2);
}

/* Sanction states */

.individual-violations-table .sanction-state {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    border: 1px solid transparent;
    font-size: 0.82rem !important;
}

.sanction-state-pending {
    color: #815300;
    background: #fff4d5;
    border-color: #f4df9d;
}

.sanction-state-approved {
    color: #116448;
    background: #e2f5ec;
    border-color: #bce7d2;
}

.sanction-state-declined {
    color: #9b2727;
    background: #fff0f0;
    border-color: #f2cdcd;
}

.sanction-state-assigned {
    color: #4d5664;
    background: #f0f2f5;
    border-color: #dfe3e8;
}

/* Informational notices in the action column */

.individual-violations-table .individual-actions-col .alert {
    width: 100%;
    max-width: 190px;
    margin: 0 auto;
    line-height: 1.45;
    text-align: left;
    word-break: normal;
    overflow-wrap: break-word;
}

/* Empty state */

.individual-log-empty {
    padding: 3.25rem 1rem !important;
    color: #77717a !important;
    text-align: center;
}

.individual-log-empty-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 3rem;
    height: 3rem;
    margin-bottom: 0.75rem;
    border-radius: 1rem;
    color: #850909;
    background: #fff3df;
    font-size: 1.35rem;
}

/* Pagination: preserve Laravel pagination and eliminate excess space */

.individual-log-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    gap: 0.75rem;
    width: 100%;
    height: auto !important;
    min-height: 0 !important;
    margin: 0 !important;
    padding: 1rem !important;
    border-top: 1px solid #f0eeee;
    background: #fff;
}

.individual-log-pagination nav {
    max-width: 100%;
    margin: 0;
}

.individual-log-pagination .pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    gap: 0.2rem;
    margin: 0 !important;
}

.individual-log-pagination svg {
    width: 1rem;
    height: 1rem;
}

/* Natural card height: prevent a large blank area below the log */

.individual-log-card .table-responsive,
.individual-log-card .individual-table-responsive {
    height: auto !important;
    min-height: 0 !important;
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}

.individual-log-card .individual-violations-table {
    margin-bottom: 0 !important;
}

.individual-log-card nav[aria-label],
.individual-log-card .pagination {
    margin-bottom: 0 !important;
}

.individual-log-card .card-footer {
    height: auto !important;
    min-height: 0 !important;
}

/* Responsive filters */

@media (max-width: 767.98px) {
    .individual-log-filters {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .individual-filter-actions {
        grid-column: 1 / -1;
    }

    .individual-status-filter-row {
        align-items: flex-start;
        flex-direction: column;
        gap: 0.4rem;
    }

    .individual-status-filter-row .form-select {
        width: 100%;
        max-width: none;
    }
}

@media (max-width: 640px) {
    .individual-log-header {
        align-items: flex-start;
        padding: 1rem !important;
    }

    .individual-log-title {
        font-size: 1rem;
    }

    .individual-log-subtitle {
        font-size: 0.78rem;
    }

    .individual-log-icon {
        width: 2.35rem;
        height: 2.35rem;
        flex-basis: 2.35rem;
    }

    .individual-log-count {
        padding: 0.38rem 0.55rem;
        font-size: 0.7rem;
    }

    .individual-violations-table thead th {
        padding: 0.8rem 0.7rem !important;
        font-size: 0.76rem !important;
    }

    .individual-violations-table tbody td {
        padding: 0.85rem 0.7rem !important;
        font-size: 0.86rem;
    }

    .individual-log-pagination {
        padding: 0.85rem 0.5rem !important;
    }
}

@media (max-width: 420px) {
    .individual-log-filters {
        grid-template-columns: 1fr;
    }

    .individual-log-header {
        flex-wrap: wrap;
    }

    .individual-log-count {
        margin-left: 3.25rem;
    }
}

</style>
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
                                {{ $user->full_name ?: '—' }}
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

                            <div class="table-responsive summary-table-responsive">

                                <table class="table table-hover align-middle mb-0 portal-table summary-violations-table">

                                <thead>

                                    <tr>

                                        <th class="summary-category-col">
                                            Category
                                        </th>

                                        <th class="summary-violation-type-col">
                                            Violation Type
                                        </th>

                                        <th class="summary-offense-col">
                                            Offense Level
                                        </th>

                                        <th class="summary-status-col">
                                            Status
                                        </th>

                                        <th class="summary-remarks-col">
                                            Remarks
                                        </th>


                                        <th class="summary-sanction-col">
                                            Disciplinary Sanction
                                        </th>


                                    </tr>

                                </thead>

                                <tbody>
                                    @forelse($violationSummary as $summary)

<tr>

    <td class="summary-category-col">
        {{ $summary['category'] }}
    </td>

    <td class="summary-violation-type-col">
        {{ $summary['type'] }}
    </td>

    <td class="summary-offense-col">
        {{ $summary['offense_level'] }}
    </td>

    <td class="summary-status-col">
        @if(!empty($summary['has_pending']))

            <span class="portal-badge goldenrod">

                Pending Confirmation

            </span>

        @elseif($summary['violation_status'] === 'Sanction')

            <span class="badge bg-danger">

                Sanction

            </span>

        @else

            <span class="portal-badge warning">

                Warning

            </span>

        @endif

    </td>

    <td class="summary-remarks-col">
        {{ $summary['remarks'] }}
    </td>

    <td class="summary-sanction-col">
        {{ $summary['disciplinary_sanction'] }}
    </td>



</tr>

@empty

<tr>

    <td
        colspan="6"
        class="text-center py-5 text-muted">

        No violation summary records found.

    </td>

</tr>

@endforelse

</tbody>

</table>

                            </div>

</div>

</div>

{{-- ================================================= --}}
{{-- Individual Violations Log --}}
{{-- ================================================= --}}

<div class="card individual-log-card mb-4">
    <div class="card-header individual-log-header">
        <div class="individual-log-heading">
            <span class="individual-log-icon"><i class="bi bi-journal-check"></i></span>
            <div>
                <h5 class="individual-log-title">Individual Violations Log</h5>
                <p class="individual-log-subtitle">Review your confirmed records and track sanction requests.</p>
            </div>
        </div>
        <span class="individual-log-count"><i class="bi bi-list-check"></i>{{ $violations->total() }} {{ $violations->total() === 1 ? 'record' : 'records' }}</span>
    </div>

    <div class="card-body individual-log-filters-wrap">
        <form method="GET" action="{{ route('student.record') }}" class="individual-log-filters" id="individualLogFilters">
            <div class="individual-filter-field">
                <label for="filterCategory">Category</label>
                <select class="form-select" id="filterCategory" name="category_id">
                    <option value="">All Categories</option>
                    @foreach($recordedViolationCategories as $category)
                        <option value="{{ $category->violation_category_id }}" @selected((string)($filters['category_id'] ?? '') === (string)$category->violation_category_id)>{{ $category->category_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="individual-filter-field">
                <label for="filterViolationType">Violation Type</label>
                <select class="form-select" id="filterViolationType" name="violation_type_id" data-selected="{{ $filters['violation_type_id'] ?? '' }}">
                    <option value="">All Violation Types</option>
                    @foreach($recordedViolationTypes as $type)
                        <option value="{{ $type->violation_type_id }}" data-category-id="{{ $type->violation_category_id }}" @selected((string)($filters['violation_type_id'] ?? '') === (string)$type->violation_type_id)>{{ $type->violation_type }}</option>
                    @endforeach
                </select>
            </div>
            <div class="individual-filter-field">
                <label for="filterDateFrom">From Date</label>
                <input class="form-control" type="date" id="filterDateFrom" name="date_from" value="{{ $filters['date_from'] ?? '' }}" max="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div class="individual-filter-field">
                <label for="filterDateTo">To Date</label>
                <input class="form-control" type="date" id="filterDateTo" name="date_to" value="{{ $filters['date_to'] ?? '' }}" min="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div class="individual-filter-actions">
                <button class="btn individual-filter-apply" type="submit"><i class="bi bi-funnel me-1"></i>Apply Filters</button>
                <a class="btn individual-filter-clear" href="{{ route('student.record') }}">Clear</a>
            </div>
        </form>
        <div class="individual-status-filter-row">
            <label for="filterSanctionStatus">Sanction Status</label>
            <select class="form-select" id="filterSanctionStatus">
                <option value="all">All Statuses</option>
                <option value="needs">Needs Request</option>
                <option value="pending">Request Pending</option>
                <option value="approved">Approved</option>
                <option value="declined">Declined</option>
                <option value="assigned">Sanction Assigned</option>
            </select>
            <span class="small text-muted" id="individualFilterCount" aria-live="polite"></span>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive individual-table-responsive">
            <table class="table align-middle individual-violations-table">
                <thead>
                    <tr>
                        <th>Violation Type</th>
                        <th>Date Recorded</th>
                        <th>Category</th>
                        <th>Record Status</th>
                        <th>Remarks</th>
                        <th>Evidence</th>
                        <th>Sanction Request</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($violations as $violation)
                        @php
                            $isPendingLog = strtolower((string) $violation->status) === 'pending';
                            $resolvedType = $violation->resolvedViolationType();
                            $typeId = (string) ($resolvedType?->violation_type_id ?? $violation->violation_type_id ?? '');
                            $categoryId = (string) ($resolvedType?->violation_category_id ?? '');
                            $sanctionRequest = $sanctionRequestsByType[$typeId] ?? null;
                            $requestStatus = strtolower((string) ($sanctionRequest->status ?? ''));
                            $isActiveRequest = (bool) ($sanctionRequest->is_active ?? false);
                            $hasAssignedSanction = isset($assignedSanctionViolationIds[$violation->violation_id]);
                            $rowSanctionStatus = $hasAssignedSanction ? 'assigned' : (($requestStatus === 'pending' && $isActiveRequest) ? 'pending' : ($requestStatus === 'approved' ? 'approved' : ($requestStatus === 'declined' ? 'declined' : 'needs')));
                            $rowDate = \Carbon\Carbon::parse($violation->violation_date)->format('Y-m-d');
                            $isWarningLevelViolation = isset(
    $warningLevelViolationIds[(int) $violation->violation_id]
);
                        @endphp
                        <tr data-category-id="{{ $categoryId }}" data-violation-type-id="{{ $typeId }}" data-violation-date="{{ $rowDate }}" data-sanction-status="{{ $rowSanctionStatus }}">
                            <td class="violation-type-col">{{ $violation->violation_type_display }}</td>
                            <td class="violation-date-col">
                                <div>{{ \Carbon\Carbon::parse($violation->violation_date)->format('M d, Y') }}</div>
                                <small class="text-muted">{{ \Carbon\Carbon::parse($violation->violation_date)->format('h:i A') }}</small>
                            </td>
                            <td class="category-col">{{ $violation->violation_category_display }}</td>
                            <td class="status-col">
                                <span class="portal-badge success"><i class="bi bi-check-circle me-1"></i>Confirmed</span>
                            </td>
                            <td class="remarks-col">{{ $violation->description ?: 'No remarks provided' }}</td>
                            <td class="attachments-col">
                                @php
                                    $visibleEvidence = collect($violation->evidence ?? [])->filter(fn ($e) => !empty($e->disk_path));
                                @endphp
                                @if ($visibleEvidence->isNotEmpty())
                                    @foreach ($visibleEvidence as $item)
                                        <a href="{{ route('student.violations.evidence', ['evidence' => $item->access_key]) }}"
                                           target="_blank" rel="noopener"
                                           class="btn btn-sm btn-outline-secondary me-1 mb-1"
                                           title="Open {{ $item->original_name ?: 'evidence' }}">
                                            <i class="bi bi-paperclip"></i> {{ Str::limit($item->original_name ?: 'Evidence', 18) }}
                                        </a>
                                    @endforeach
                                @else
                                    <span class="text-muted small"><i class="bi bi-paperclip me-1"></i>No attachment</span>
                                @endif
                            </td>
                            <td class="individual-actions-col">
                                @if($isPendingLog)
                                    <span class="text-muted small">Available after confirmation</span>
                                @elseif($requestStatus === 'pending' && (bool) ($sanctionRequest->is_active ?? false))
                                    <span class="sanction-state sanction-state-pending"><i class="bi bi-hourglass-split"></i>Request Pending</span>
                                    <small class="d-block text-muted mt-2">Submitted for admin review</small>
                                @elseif($requestStatus === 'approved')
                                    @if($hasAssignedSanction)
                                        <span class="sanction-state sanction-state-assigned"><i class="bi bi-check2-circle"></i>Sanction Assigned</span>
                                    @else
                                        <span class="sanction-state sanction-state-approved"><i class="bi bi-check-circle-fill"></i>Request Approved</span>
                                    @endif
                                @elseif($hasAssignedSanction)
                                    <span class="sanction-state sanction-state-assigned"><i class="bi bi-shield-check"></i>Sanction Assigned</span>
                                @elseif($requestStatus === 'declined')
                                    <span class="sanction-state sanction-state-declined"><i class="bi bi-x-circle"></i>Request Declined</span>
                                    <button type="button" class="btn request-sanction-btn mt-2" data-violation-type-id="{{ $typeId }}">
                                        <i class="bi bi-arrow-repeat"></i> Request Again
                                    </button>
                                    @elseif($isWarningLevelViolation)
    <div class="alert alert-warning py-2 px-3 mb-0 small text-start"
         role="note">
        <strong class="d-block mb-1">
            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
            Warning-Level Violation
        </strong>

        A sanction request is not required for this violation.
        Please check the Sanction Record tab for the related guidance note.
    </div>

@else
    <button type="button" class="btn request-sanction-btn"
            data-violation-type-id="{{ $typeId }}">
        <i class="bi bi-send"></i> Request Sanction
    </button>
    <small class="d-block text-muted mt-2">
        Start a sanction request
    </small>
@endif 
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="individual-log-empty">
                                <span class="individual-log-empty-icon"><i class="bi bi-journal-x"></i></span>
                                <div class="fw-bold text-dark mb-1">No violation records yet</div>
                                <div class="small">Confirmed violation records will appear here when available.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>
        </div>

        {{-- Individual Violations Log pagination --}}
        @if ($violations->hasPages())
            <div class="individual-log-pagination px-3 py-3">
                {{ $violations->links() }}
            </div>
        @endif
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

            <div class="table-responsive">
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

                            Deadline

                        </th>

                        <th>

                            Sanction Details

                        </th>

                        <th>

                            Proof of Completion

                        </th>

                    </tr>

                </thead>

                <tbody>

                    @forelse($sanctionRecords as $record)

                        {{-- data-sanction-record-id lets the dashboard's
                             "Approaching Deadlines" View action deep-link
                             straight to this exact sanction row. --}}
                        <tr data-sanction-record-id="{{ $record->record_id }}">

                            <td>

                                {{ optional(optional($record->violation)->violationType)->violation_type ?? 'N/A' }}

                            </td>

                            <td>

                                @php
                                    $recStatus = strtolower(trim((string) $record->status));
                                    $recOpen = strcasecmp((string) $record->status, 'Completed') !== 0;
                                    $recOverdue = $recOpen && $record->deadline_date && $record->deadline_date->isPast();
                                @endphp

                                @if($recStatus === 'pending')
                                    @if($record->has_pending_proof)
                                        <span class="portal-badge maroon">
                                            For Review
                                        </span>
                                    @else
                                        <span class="portal-badge warning">
                                            Pending
                                        </span>
                                    @endif
                                @elseif($recStatus === 'in progress')
                                    <span class="portal-badge goldenrod">
                                        In Progress
                                    </span>
                                @elseif($recStatus === 'overdue' || $recOverdue)
                                    <span class="portal-badge maroon">
                                        Overdue
                                    </span>
                                @elseif($recStatus === 'completed')
                                    <span class="portal-badge success">
                                        Completed
                                    </span>
                                @else
                                    <span class="portal-badge muted">
                                        {{ $record->status }}
                                    </span>
                                @endif

                            </td>

                            <td>

                                {{ optional($record->date_assigned)->format('M d, Y h:i A') }}

                            </td>

                            <td>

                                @if($record->deadline_date)
                                    <span class="fw-semibold {{ ($recOverdue || $recStatus === 'overdue') ? 'text-danger' : '' }}">
                                        {{ $record->deadline_date->format('M d, Y') }}
                                    </span>
                                    @if($recOverdue || $recStatus === 'overdue')
                                        <span class="portal-badge maroon d-block mt-1">Overdue</span>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif

                            </td>

                            <td style="max-width:320px; white-space:normal;">

                                {{ optional($record->assignedSanction)->disciplinary_sanction ?? 'N/A' }}

                                @if($record->is_service_sanction)
                                    {{-- Community/Campus Service assignment — server-side
                                         values, read-only to the student. --}}
                                    <div class="service-assignment-details mt-2">
                                        @if($record->required_hours)
                                            <div class="service-assignment-row">
                                                <span class="service-assignment-label"><i class="bi bi-hourglass-split me-1"></i>Required Hours:</span>
                                                <span class="fw-semibold">{{ $record->required_hours }} hrs</span>
                                            </div>
                                        @endif
                                        <div class="service-assignment-row">
                                            <span class="service-assignment-label"><i class="bi bi-building me-1"></i>Designated Office:</span>
                                            <span class="fw-semibold">{{ $record->designated_office ?: '—' }}</span>
                                        </div>
                                        <div class="service-assignment-row">
                                            <span class="service-assignment-label"><i class="bi bi-calendar-event me-1"></i>Starting Date:</span>
                                            <span class="fw-semibold">{{ optional($record->starting_date)->format('M d, Y') ?? '—' }}</span>
                                        </div>
                                    </div>
                                @endif

                            </td>

                            <td style="min-width:220px;">

                                @if(strcasecmp($record->status, 'Completed') === 0)

                                    <span class="portal-badge success mb-1 d-inline-block">Completed</span>

                                    @if($record->proof_path)
                                        <div class="small text-success mb-1">
                                            <i class="bi bi-patch-check-fill me-1"></i>Proof accepted
                                        </div>
                                        @if($record->proof_access_key)
                                            <a href="{{ route('student.proof.download', ['studentSanctionRecord' => $record->proof_access_key]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-paperclip me-1"></i>View Proof
                                            </a>
                                        @else
                                            <span class="small text-muted">Proof file link unavailable. Please contact the Office of Student Services.</span>
                                        @endif
                                    @else
                                        <span class="small text-muted">Completed by administration</span>
                                    @endif

                                @elseif($record->has_pending_proof)

                                    <span class="portal-badge goldenrod mb-1 d-inline-block">Under Review</span>

                                    <div class="small text-muted mb-1">
                                        <i class="bi bi-hourglass-split me-1"></i>Proof submitted — awaiting admin review.
                                    </div>

                                    @if($record->proof_path && $record->proof_access_key)
                                        <a href="{{ route('student.proof.download', ['studentSanctionRecord' => $record->proof_access_key]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary mb-1">
                                            <i class="bi bi-paperclip me-1"></i>View Proof
                                        </a>
                                    @elseif($record->proof_path)
                                        <span class="small text-muted">Proof file link unavailable. Please contact the Office of Student Services.</span>
                                    @endif

                                    @if($record->completion_remarks)
                                        <div class="small text-danger mt-1">
                                            <i class="bi bi-x-circle me-1"></i>Last review: {{ $record->completion_remarks }}
                                        </div>
                                    @endif

                                @elseif($recOpen)

                                    <span class="portal-badge warning mb-1 d-inline-block">Assigned</span>

                                    @if($record->completion_remarks)
                                        <div class="small text-danger mb-2">
                                            <i class="bi bi-x-circle me-1"></i>Your previous proof was not accepted: {{ $record->completion_remarks }}
                                        </div>
                                    @endif

                                    @if($record->requires_proof_of_completion)
                                        {{-- Community/Campus Service: the student uploads a
                                             Proof of Completion for admin review. --}}
                                        <button
                                            type="button"
                                            class="btn portal-btn btn-sm submit-proof-btn"
                                            data-record-id="{{ $record->record_id }}"
                                            data-violation="{{ optional(optional($record->violation)->violationType)->violation_type ?? 'N/A' }}"
                                            data-sanction="{{ optional($record->assignedSanction)->disciplinary_sanction ?? 'N/A' }}"
                                            data-deadline="{{ optional($record->deadline_date)->format('M d, Y') ?? '' }}"
                                            data-record-ref="Record #{{ $record->record_id }}"
                                            data-office="{{ $record->designated_office ?? '' }}"
                                            data-starting-date="{{ optional($record->starting_date)->format('M d, Y') ?? '' }}"
                                            data-required-hours="{{ $record->required_hours ?? '' }}"
data-service-sanction="{{ $record->is_service_sanction ? '1' : '0' }}">
    <i class="bi bi-upload me-1"></i>Submit Proof
</button>
                                    @else
                                        {{-- Every other sanction is settled through a personal
                                             consultation at the Office of Student Services, so
                                             no proof of completion is required from the student. --}}
                                        <div class="consultation-notice small" role="note">
                                            <i class="bi bi-info-circle-fill me-1" aria-hidden="true"></i>{{ \App\Models\StudentSanctionRecord::CONSULTATION_NOTICE }}
                                        </div>
                                    @endif

                                @else

                                    <span class="text-muted small">—</span>

                                @endif

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="7"
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

        </div>


<div class="modal fade" id="requestSanctionConfirmModal" tabindex="-1" aria-labelledby="requestSanctionConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requestSanctionConfirmModalLabel">Confirm Sanction Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">Are you sure you want to request this disciplinary sanction?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn portal-btn" id="confirmRequestSanctionBtn"><i class="bi bi-send me-1"></i>Yes, Request Sanction</button>
            </div>
        </div>
    </div>
</div>

{{-- Submit Proof of Completion modal --}}
<div class="modal fade" id="submitProofModal" tabindex="-1" aria-labelledby="submitProofModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold" id="submitProofModalLabel">Submit Proof of Completion</h5>
                    <p class="modal-subtitle mb-0">Upload the official document that verifies completion of your assigned sanction.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close submit proof dialog"></button>
            </div>
            <form id="submitProofForm" enctype="multipart/form-data" novalidate>
                @csrf
                <input type="hidden" name="record_id" id="proofRecordId">
                <div class="modal-body">

                    {{-- Sanction details (read-only, from the server-side record) --}}
                    <section class="proof-section" aria-labelledby="sanctionDetailsHeading">
                        <h6 class="proof-section-title" id="sanctionDetailsHeading">
                            <i class="bi bi-clipboard-check me-1" aria-hidden="true"></i>Sanction Details
                        </h6>
                        <div class="proof-detail-grid">
                            <div class="proof-detail-item">
                                <span class="proof-detail-label" id="proofViolationLabel">Violation Type</span>
                                <span class="proof-detail-value" id="proofViolationDisplay" aria-labelledby="proofViolationLabel">—</span>
                            </div>
                            <div class="proof-detail-item">
                                <span class="proof-detail-label" id="proofSanctionLabel">Assigned Sanction</span>
                                <span class="proof-detail-value" id="proofSanctionDisplay" aria-labelledby="proofSanctionLabel">—</span>
                            </div>
                            <div class="proof-detail-item">
                                <span class="proof-detail-label" id="proofDeadlineLabel">Deadline</span>
                                <span class="proof-detail-value" id="proofDeadlineDisplay" aria-labelledby="proofDeadlineLabel">—</span>
                            </div>
                            <div class="proof-detail-item">
                                <span class="proof-detail-label" id="proofRefLabel">Reference</span>
                                <span class="proof-detail-value" id="proofRefDisplay" aria-labelledby="proofRefLabel">—</span>
                            </div>
                        </div>
                    </section>

                    {{-- 1. Prepare your proof --}}
                    <section class="proof-section" aria-labelledby="prepareHeading">
                        <h6 class="proof-section-title" id="prepareHeading">
                            <span class="proof-step-number" aria-hidden="true">1</span>Prepare Your Proof
                        </h6>
                        <p class="proof-body-text mb-2">Upload the official document or proof required for your assigned sanction.</p>
                        <p class="proof-body-text mb-0">Make sure the document is complete, clear, readable, and contains any required signatures or certifications.</p>
                        <div id="officialFormArea" class="official-form-area d-none">
                            <span class="official-form-label">Need the official form?</span>
                            <a href="https://docs.google.com/document/d/1STRQ6AwNaSgHIjqt7vbxNFF7h_50xj84/edit?usp=sharing&ouid=105963611531508867265&rtpof=true&sd=true" id="officialFormDownloadBtn" class="btn portal-btn" target="_blank" rel="noopener">
                                <i class="bi bi-download me-1" aria-hidden="true"></i>Download Official Form
                            </a>
                        </div>
                    </section>

                    {{-- 2. Upload proof --}}
                    <section class="proof-section" aria-labelledby="uploadHeading">
                        <h6 class="proof-section-title" id="uploadHeading">
                            <span class="proof-step-number" aria-hidden="true">2</span>Upload Proof
                        </h6>
                        <p class="proof-body-text">Upload the document that verifies your completion of the assigned sanction.</p>

                        <div id="proofDropZone" class="proof-upload-zone" role="button" tabindex="0" aria-labelledby="proofUploadPrompt" aria-describedby="proofUploadFormats">
                            <input type="file" class="proof-file-input" id="proofFile" name="proof_file" accept=".jpg,.jpeg,.png,.pdf" aria-label="Choose proof file (JPG, JPEG, PNG, or PDF, maximum 5 MB)">
                            <div id="proofUploadPrompt">
                                <i class="bi bi-cloud-arrow-up proof-upload-icon" aria-hidden="true"></i>
                                <p class="proof-upload-title mb-1">Upload your proof</p>
                                <p class="proof-upload-hint mb-1">Drag and drop your file here or</p>
                                <span class="btn portal-btn">Choose File</span>
                            </div>
                            <div id="proofFileSelected" class="proof-file-selected d-none">
                                <div class="proof-file-chip">
                                    <i class="bi bi-file-earmark-check-fill proof-file-icon" aria-hidden="true"></i>
                                    <div class="proof-file-meta">
                                        <span class="proof-file-name" id="proofFileName">—</span>
                                        <span class="proof-file-size" id="proofFileSize">—</span>
                                    </div>
                                </div>
                                <button type="button" class="btn portal-btn" id="proofFileChangeBtn">
                                    <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Change File
                                </button>
                            </div>
                        </div>
                        <p class="proof-formats" id="proofUploadFormats">JPG, JPEG, PNG, PDF • Maximum 5 MB</p>
                        <div id="proofFileError" class="invalid-proof-feedback d-none" role="alert"></div>
                    </section>

                    {{-- Submission guidelines --}}
                    <section class="proof-section" aria-labelledby="guidelinesHeading">
                        <h6 class="proof-section-title" id="guidelinesHeading">
                            <i class="bi bi-list-check me-1" aria-hidden="true"></i>Submission Guidelines
                        </h6>
                        <ul class="proof-guidelines">
                            <li>Submit the official proof required for your sanction.</li>
                            <li>Make sure all information is complete and readable.</li>
                            <li>Include required signatures/certifications when applicable.</li>
                            <li>Upload JPG, JPEG, PNG, or PDF files only.</li>
                            <li>Maximum file size is 5 MB.</li>
                        </ul>
                    </section>

                    {{-- Additional remarks --}}
                    <section class="proof-section" aria-labelledby="remarksHeading">
                        <h6 class="proof-section-title" id="remarksHeading">
                            <i class="bi bi-chat-left-text me-1" aria-hidden="true"></i>Additional Remarks
                        </h6>
                        <label for="proofNotes" class="form-label small text-muted">Additional Remarks (Optional)</label>
                        <textarea class="form-control" id="proofNotes" name="proof_notes" rows="3" maxlength="1000" placeholder="Add any information that may help the Discipline Office review your submission..."></textarea>
                        <div class="d-flex justify-content-end">
                            <small class="text-muted proof-char-counter" id="proofNotesCounter">0 / 1000</small>
                        </div>
                    </section>

                    {{-- Review required --}}
                    <div class="proof-review-alert" role="note">
                        <div class="d-flex gap-2">
                            <i class="bi bi-shield-check fs-5 flex-shrink-0" aria-hidden="true"></i>
                            <div>
                                <p class="fw-semibold mb-1">Review Required</p>
                                <p class="small mb-1">Your submission will be reviewed by an authorized Discipline Office administrator. You will be notified once your proof is approved or if further action is required.</p>
                                <p class="small mb-0"><strong>Note:</strong> Uploading a document does not automatically mark your sanction as completed.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Confirmation --}}
                    <div class="form-check proof-confirm mt-3">
                        <input class="form-check-input" type="checkbox" id="proofConfirmCheck" aria-describedby="proofConfirmHelp">
                        <label class="form-check-label" for="proofConfirmCheck">
                            I confirm that the submitted document is a true and complete proof of my sanction completion.
                        </label>
                        <div id="proofConfirmHelp" class="form-text">This confirms your submission only. Your proof will still be reviewed by the Discipline Office.</div>
                    </div>
                    <div id="proofConfirmError" class="invalid-proof-feedback d-none" role="alert"></div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn portal-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn portal-btn" id="submitProofBtn" disabled>
                        <i class="bi bi-send me-1"></i>Submit Proof
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Deep-link support: if the URL contains #sanctionRecord, open the
    // Sanction Record tab automatically (used by notification links).
    if (window.location.hash === '#sanctionRecord') {
        const sanctionTab = document.getElementById('sanction-tab');
        if (sanctionTab) {
            bootstrap.Tab.getOrCreateInstance(sanctionTab).show();
        }
    }

    // Deep-link support from the dashboard's "Approaching Deadlines" card:
    // ?sanction=<record_id>#sanctionRecord opens the Sanction Record tab and
    // HIGHLIGHTS the exact sanction that was clicked, so the student lands on
    // the right row rather than a generic tab.
    (function highlightSanctionFromQuery() {
        const params = new URLSearchParams(window.location.search);
        const recordId = params.get('sanction');

        if (!recordId) {
            return;
        }

        const row = document.querySelector('[data-sanction-record-id="' + recordId + '"]');

        if (!row) {
            return;
        }

        row.classList.add('sanction-row-highlight');
        row.setAttribute('tabindex', '-1');

        // Move focus/scroll to the highlighted row so it is immediately
        // visible, including after a reload of this URL.
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        row.focus({ preventScroll: true });
    })();

    // Proof of completion submission.
    const submitProofModalEl = document.getElementById('submitProofModal');
    const submitProofForm = document.getElementById('submitProofForm');

    if (submitProofModalEl && submitProofForm) {
        const submitProofModal = new bootstrap.Modal(submitProofModalEl);
        const proofFileInput = document.getElementById('proofFile');
        const proofDropZone = document.getElementById('proofDropZone');
        const proofUploadPrompt = document.getElementById('proofUploadPrompt');
        const proofFileSelected = document.getElementById('proofFileSelected');
        const proofFileNameEl = document.getElementById('proofFileName');
        const proofFileSizeEl = document.getElementById('proofFileSize');
        const proofFileError = document.getElementById('proofFileError');
        const proofFileChangeBtn = document.getElementById('proofFileChangeBtn');
        const proofNotes = document.getElementById('proofNotes');
        const proofNotesCounter = document.getElementById('proofNotesCounter');
        const proofConfirmCheck = document.getElementById('proofConfirmCheck');
        const proofConfirmError = document.getElementById('proofConfirmError');
        const submitBtn = document.getElementById('submitProofBtn');
        const MAX_PROOF_BYTES = 5 * 1024 * 1024;
        const ALLOWED_PROOF_TYPES = ['image/jpeg', 'image/png', 'application/pdf'];
        const ALLOWED_PROOF_EXT = ['jpg', 'jpeg', 'png', 'pdf'];

        const fileBaseName = function (name) {
            return name.split(/(\\|\/)/g).pop() || name;
        };

        const formatBytes = function (bytes) {
            if (bytes < 1024) { return bytes + ' B'; }
            if (bytes < 1024 * 1024) { return (bytes / 1024).toFixed(1) + ' KB'; }
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        };

        const setProofError = function (message) {
            if (!message) {
                proofFileError.classList.add('d-none');
                proofFileError.textContent = '';
                return;
            }
            proofFileError.textContent = message;
            proofFileError.classList.remove('d-none');
        };

        const isAcceptedProofFile = function (file) {
            if (!file) { return false; }
            const ext = (file.name.split('.').pop() || '').toLowerCase();
            const typeOk = ALLOWED_PROOF_TYPES.indexOf(file.type) !== -1;
            const extOk = ALLOWED_PROOF_EXT.indexOf(ext) !== -1;
            // A file must pass BOTH the extension and the MIME/content check to
            // satisfy the server's rule (mimes + mimetypes). If the browser
            // reports an empty MIME, fall back to extension only; the server
            // still re-validates the real content type.
            const typeUnknown = file.type === '';
            return file.size <= MAX_PROOF_BYTES && (extOk && (typeOk || typeUnknown));
        };

        const syncSubmitState = function () {
            const hasFile = proofFileInput && proofFileInput.files && proofFileInput.files.length > 0;
            const confirmed = proofConfirmCheck && proofConfirmCheck.checked;
            if (submitBtn) {
                submitBtn.disabled = !(hasFile && confirmed);
            }
        };

        const clearFileError = function () { setProofError(''); };

        const showSelectedFile = function () {
            const file = proofFileInput && proofFileInput.files ? proofFileInput.files[0] : null;
            if (!file) {
                if (proofUploadPrompt) { proofUploadPrompt.classList.remove('d-none'); }
                if (proofFileSelected) { proofFileSelected.classList.add('d-none'); }
                if (proofFileNameEl) { proofFileNameEl.textContent = '—'; }
                if (proofFileSizeEl) { proofFileSizeEl.textContent = '—'; }
                syncSubmitState();
                return;
            }
            if (proofFileNameEl) { proofFileNameEl.textContent = fileBaseName(file.name); }
            if (proofFileSizeEl) { proofFileSizeEl.textContent = formatBytes(file.size); }
            if (proofUploadPrompt) { proofUploadPrompt.classList.add('d-none'); }
            if (proofFileSelected) { proofFileSelected.classList.remove('d-none'); }
            syncSubmitState();
        };

        const resetProofUpload = function () {
            if (proofFileInput) { proofFileInput.value = ''; }
            clearFileError();
            showSelectedFile();
        };

        // File selection via the input.
        if (proofFileInput) {
            proofFileInput.addEventListener('change', function () {
                const file = proofFileInput.files ? proofFileInput.files[0] : null;
                if (!file) { showSelectedFile(); return; }
                if (!isAcceptedProofFile(file)) {
                    setProofError('Please choose a JPG, JPEG, PNG, or PDF file no larger than 5 MB.');
                    resetProofUpload();
                    return;
                }
                clearFileError();
                showSelectedFile();
            });
        }

        // Drag & drop (desktop convenience; file input remains the accessible
        // control on mobile).
        if (proofDropZone) {
            const preventDefaults = function (e) {
                e.preventDefault();
                e.stopPropagation();
            };
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function (evtName) {
                proofDropZone.addEventListener(evtName, preventDefaults, false);
            });
            ['dragenter', 'dragover'].forEach(function (evtName) {
                proofDropZone.addEventListener(evtName, function () {
                    proofDropZone.classList.add('proof-upload-zone-active');
                }, false);
            });
            ['dragleave', 'drop'].forEach(function (evtName) {
                proofDropZone.addEventListener(evtName, function () {
                    proofDropZone.classList.remove('proof-upload-zone-active');
                }, false);
            });
            proofDropZone.addEventListener('drop', function (e) {
                const dt = e.dataTransfer;
                const files = dt && dt.files ? dt.files : [];
                if (files.length) {
                    if (proofFileInput) { proofFileInput.files = files; }
                    proofFileInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
            // Clicking anywhere in the zone opens the file picker.
            proofDropZone.addEventListener('click', function (e) {
                if (e.target && e.target.closest && e.target.closest('#proofFileChangeBtn')) {
                    return; // handled by the change button
                }
                if (proofFileInput) { proofFileInput.click(); }
            });
            proofDropZone.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    if (proofFileInput) { proofFileInput.click(); }
                }
            });
        }

        if (proofFileChangeBtn) {
            proofFileChangeBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (proofFileInput) { proofFileInput.click(); }
            });
        }

        if (proofConfirmCheck) {
            proofConfirmCheck.addEventListener('change', function () {
                if (proofConfirmError) { proofConfirmError.classList.add('d-none'); }
                syncSubmitState();
            });
        }

        if (proofNotes && proofNotesCounter) {
            const updateCounter = function () {
                const len = proofNotes.value ? proofNotes.value.length : 0;
                proofNotesCounter.textContent = len + ' / 1000';
            };
            proofNotes.addEventListener('input', updateCounter);
        }

        document.querySelectorAll('.submit-proof-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                const recordId = button.dataset.recordId || '';
                document.getElementById('proofRecordId').value = recordId;
                document.getElementById('proofViolationDisplay').textContent = button.dataset.violation || '—';
                document.getElementById('proofSanctionDisplay').textContent = button.dataset.sanction || '—';
                document.getElementById('proofDeadlineDisplay').textContent = button.dataset.deadline || '—';
                document.getElementById('proofRefDisplay').textContent = button.dataset.recordRef || '—';

                // Reset per-open state.
                if (proofNotes) { proofNotes.value = ''; }
                if (proofNotesCounter) { proofNotesCounter.textContent = '0 / 1000'; }
                if (proofConfirmCheck) { proofConfirmCheck.checked = false; }
                if (proofConfirmError) { proofConfirmError.classList.add('d-none'); }
                resetProofUpload();

                // The official form is only relevant for Community Service /
                // Campus Service sanctions. Show the download link only when
                // the assigned sanction is one of those; hide it for others.
// The official form is relevant only to Community Service / Campus Service.
// Student-Assistance Service uses the existing OSS consultation notice and
// does not require a proof upload.
const officialArea = document.getElementById('officialFormArea');

if (officialArea) {
    const isServiceSanction =
        String(button.dataset.serviceSanction || '0') === '1';

    officialArea.classList.toggle('d-none', !isServiceSanction);
}

                submitProofModal.show();
            });
        });

        submitProofForm.addEventListener('submit', function (event) {
            event.preventDefault();

            const recordId = document.getElementById('proofRecordId').value;
            const file = proofFileInput && proofFileInput.files ? proofFileInput.files[0] : null;
            const confirmed = proofConfirmCheck && proofConfirmCheck.checked;

            if (!recordId) {
                Swal.fire({ icon: 'warning', title: 'Missing Information', text: 'Please select a sanction record to attach your proof to.' });
                return;
            }
            if (!file) {
                setProofError('Please choose a proof file to upload.');
                proofFileInput && proofFileInput.focus();
                return;
            }
            if (!isAcceptedProofFile(file)) {
                setProofError('Please choose a JPG, JPEG, PNG, or PDF file no larger than 5 MB.');
                return;
            }
            if (!confirmed) {
                if (proofConfirmError) {
                    proofConfirmError.textContent = 'Please confirm that the document is a true and complete proof of your sanction completion.';
                    proofConfirmError.classList.remove('d-none');
                }
                if (proofConfirmCheck) { proofConfirmCheck.focus(); }
                return;
            }

            if (submitBtn) { submitBtn.disabled = true; }

            const formData = new FormData(submitProofForm);

            fetch("{{ route('student.submit-proof') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                },
                body: formData,
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    Swal.fire({
                        icon: data.success ? 'success' : 'error',
                        title: data.success ? 'Success' : 'Error',
                        text: data.message,
                    });
                    if (data.success) {
                        submitProofModal.hide();
                        location.reload();
                    }
                })
                .catch(function () {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An unexpected error occurred while submitting your proof.',
                    });
                })
                .finally(function () {
                    if (submitBtn) { submitBtn.disabled = false; }
                    syncSubmitState();
                });
        });
    }
});

</script>

<script src="{{ asset('js/student-record.js') }}"></script>

@endpush