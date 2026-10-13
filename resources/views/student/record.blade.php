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
#studentRecordTabsContent > .tab-pane,

.tab-content > #violationRecord,

.tab-content > #sanctionRecord {

    height: auto !important;

    min-height: 0 !important;

    flex: initial !important;

    align-self: stretch;

}



/* Bootstrap should hide inactive tab panes. */

.tab-content > .tab-pane:not(.active) {

    display: none !important;

}



/* Let the active tab determine the content height. */

.tab-content {

    height: auto !important;

    min-height: 0 !important;

    align-items: stretch;

}



/* =========================================================*

*   Individual Violations Log*

*   Consolidated responsive styling*

*   ========================================================= */



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





/* Filters */



.individual-log-filters-wrap {

    padding: 1.1rem 1.25rem !important;

    border-bottom: 1px solid #eee2d5;

    background: #fffdf9;

}



.individual-log-filters {

    display: grid;

    grid-template-columns: repeat(4, minmax(0, 1fr));

    gap: 0.85rem 1rem;

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



.individual-status-filter-row {

    display: grid;

    grid-template-columns: max-content minmax(180px, 260px) minmax(0, 1fr);

    align-items: center;

    gap: 0.55rem 0.75rem;

    grid-column: 1 / 4;

    grid-row: 2;

    min-width: 0;

}



.individual-status-filter-row label {

    margin: 0;

    white-space: nowrap;

}



.individual-status-filter-row .form-select {

    max-width: 260px;

}



.individual-status-filter-row #individualFilterCount {

    grid-column: 3;

    justify-self: end;

    max-width: 100%;

    color: #716663;

    white-space: normal;

    overflow-wrap: anywhere;

    text-align: right;

    line-height: 1.35;

    font-size: 0.82rem;

}



.individual-filter-actions {

    display: flex;

    justify-content: flex-end;

    align-items: center;

    grid-column: 4 / 5;

    grid-row: 2;

}



.individual-filter-clear {

    min-width: 110px;

    border: 1px solid #d9c8b8;

    color: #5d1515;

    background: #fffdf9;

    border-radius: 0.65rem;

    font-weight: 650;

    padding: 0.65rem 1.25rem;

}



.individual-filter-clear:hover {

    border-color: #800909;

    color: #800909;

    background: #fff8f3;

}



@media (max-width: 991.98px) {

    .individual-log-filters {

        grid-template-columns: repeat(2, minmax(0, 1fr));

    }



    .individual-status-filter-row {

        grid-column: 1 / 2;

        grid-row: 3;

        grid-template-columns: minmax(0, 1fr);

        gap: 0.4rem;

    }



    .individual-status-filter-row label {

        margin: 0;

    }



    .individual-status-filter-row .form-select {

        max-width: none;

    }



    .individual-status-filter-row #individualFilterCount {

        grid-column: 1;

        justify-self: start;

        text-align: left;

    }



    .individual-filter-actions {

        grid-column: 2 / 3;

        grid-row: 3;

        align-self: end;

    }

}



@media (max-width: 575.98px) {

    .individual-log-filters-wrap {

        padding: 1rem !important;

    }



    .individual-log-filters {

        grid-template-columns: minmax(0, 1fr);

        gap: 0.8rem;

    }



    .individual-status-filter-row,

    .individual-filter-actions {

        grid-column: 1 / -1;

        grid-row: auto;

    }



    .individual-filter-actions {

        justify-content: flex-start;

    }



    .individual-filter-clear {

        width: 100%;

    }

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

*   The seven columns are deliberately given enough space.*

*   Horizontal scrolling is preferable to breaking every word. */



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

*   1 = violation type*

*   2 = date*

*   3 = category*

*   4 = status*

*   5 = remarks*

*   6 = attachments*

*   7 = action*

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





/* Fix excessive wrapping in the Individual Violations Log Evidence column */

.individual-log-card .table-responsive > table th:nth-child(6),

.individual-log-card .table-responsive > table td:nth-child(6),

.individual-violations-table th:nth-child(6),

.individual-violations-table td:nth-child(6) {

    width: 260px !important;

    min-width: 260px !important;

}



/* Keep attachment links compact and aligned */

.individual-log-card .table-responsive > table td:nth-child(6) a,

.individual-violations-table td:nth-child(6) a {

    display: inline-flex;

    align-items: flex-start;

    gap: 0.4rem;

    max-width: 100%;

    line-height: 1.4 !important;

    white-space: normal !important;

    word-break: normal !important;

    overflow-wrap: anywhere !important;

}



/* Keep attachment icons from consuming text width */

.individual-log-card .table-responsive > table td:nth-child(6) a i,

.individual-violations-table td:nth-child(6) a i {

    flex: 0 0 auto;

    margin-top: 0.15rem;

}



/* Keep the empty attachment message compact */

.individual-log-card .table-responsive > table td:nth-child(6) .text-muted,

.individual-violations-table td:nth-child(6) .text-muted {

    display: inline-block;

    line-height: 1.4 !important;

    overflow-wrap: normal;

}



/* Slightly compact row spacing without hiding content */

.individual-log-card .table-responsive > table tbody td,

.individual-violations-table tbody td {

    padding-top: 0.65rem !important;

    padding-bottom: 0.65rem !important;

    line-height: 1.35 !important;

}



/* Fix excess height in the Student Record tab pane */

#violationRecord.tab-pane,

#sanctionRecord.tab-pane {

    height: auto !important;

    min-height: 0 !important;

    flex: 0 0 auto !important;

    align-self: flex-start !important;

}



/* Prevent the tab content from stretching the record tabs */

#violationRecord.tab-pane.show.active,

#sanctionRecord.tab-pane.show.active {

    height: auto !important;

    min-height: 0 !important;

}



/* Keep the tab-content wrapper content-sized */

.tab-content:has(> #violationRecord),

.tab-content:has(> #sanctionRecord) {

    height: auto !important;

    min-height: 0 !important;

    flex: 0 0 auto !important;

    align-self: flex-start !important;

}



/* Keep the log card and pagination at their natural height */

#violationRecord .individual-log-card {

    height: auto !important;

    min-height: 0 !important;

    margin-bottom: 1rem !important;

}



#violationRecord .individual-log-pagination {

    height: auto !important;

    min-height: 0 !important;

    margin-bottom: 0 !important;

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

        grid-template-columns: minmax(0, 1fr);

        gap: 0.4rem;

    }



    .individual-status-filter-row #individualFilterCount {

        grid-column: auto;

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







/* Warning-level consequence card: informational only, not an assigned record. */

.warning-consequence-card {

    border: 1px solid #f0dca3 !important;

    border-left: 4px solid #c58a08 !important;

    border-radius: 1rem !important;

    background: #fffaf0;

    box-shadow: 0 8px 24px rgba(83, 55, 0, 0.06) !important;

    overflow: hidden;

}

.warning-consequence-card .card-header {

    display: flex;

    align-items: flex-start;

    gap: 0.8rem;

    padding: 1rem 1.25rem;

    background: #fff4d6 !important;

    border-bottom: 1px solid #f0e0b8;

}

.warning-consequence-icon {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    flex: 0 0 2.5rem;

    width: 2.5rem;

    height: 2.5rem;

    border-radius: 0.8rem;

    color: #805600;

    background: #ffe7a3;

    font-size: 1.15rem;

}

.warning-consequence-card .card-body {

    padding: 1rem 1.25rem;

}

.warning-consequence-item {

    padding: 1rem 0;

    border-bottom: 1px solid #f0e6d0;

}

.warning-consequence-item:first-child { padding-top: 0; }

.warning-consequence-item:last-child {

    padding-bottom: 0;

    border-bottom: 0;

}

.warning-consequence-label {

    color: #79571a;

    font-size: 0.75rem;

    font-weight: 800;

    letter-spacing: 0.04em;

    text-transform: uppercase;

}

.warning-consequence-value {

    color: #332b1e;

    font-weight: 700;

    overflow-wrap: anywhere;

}

.warning-consequence-status {

    display: inline-flex;

    align-items: center;

    gap: 0.35rem;

    padding: 0.35rem 0.65rem;

    border: 1px solid #e9d28e;

    border-radius: 999px;

    color: #775400;

    background: #fff1c5;

    font-size: 0.78rem;

    font-weight: 750;

}

.warning-sanction-notice {

    border: 1px solid #f0dca3;

    border-left: 3px solid #c58a08;

    border-radius: 0.75rem;

    background: #fffaf0;

    color: #473919;

}

.warning-sanction-notice .btn {

    white-space: normal;

}



/* Give the consultation note the same deliberate callout treatment as the

*   other portal guidance panels instead of rendering it as plain table text. */

.consultation-notice {

    display: flex;

    align-items: flex-start;

    gap: 0.7rem;

    width: 100%;

    padding: 0.9rem 1rem;

    border: 1px solid rgba(128, 0, 0, 0.18);

    border-left: 4px solid #800909;

    border-radius: 0.85rem;

    background: #fff8ef;

    color: #342c2c;

    line-height: 1.5;

    white-space: normal;

}

.consultation-notice > i {

    flex: 0 0 auto;

    margin-top: 0.15rem;

    color: #800909;

    font-size: 1.1rem;

}

.consultation-notice-title {

    display: block;

    margin-bottom: 0.25rem;

    color: #720606;

    font-size: 0.88rem;

    font-weight: 800;

}

.consultation-notice-text {

    margin: 0;

    font-size: 0.84rem;

    overflow-wrap: anywhere;

}

@media (max-width: 576px) {

    .warning-consequence-card .card-header,

    .warning-consequence-card .card-body { padding: 0.9rem; }

    .consultation-notice { padding: 0.75rem; }

}



/* Match the Individual Violations Log title bar to Summary by Violation. */

.individual-log-titlebar {

    padding: 1rem 1.25rem !important;

    background: #fff !important;

    border-bottom: 1px solid #eee2d5 !important;

}

.individual-log-titlebar h5 {

    color: #800909 !important;

    font-size: 1.15rem;

    font-weight: 750;

}

.individual-log-record-count {

    color: #6c5555;

    font-size: 0.8rem;

    font-weight: 650;

}



/* Keep the filter panel aligned without changing its visual styling. */

.individual-log-filters-wrap { overflow: visible; }

.individual-log-filters { align-items: end; }

@media (max-width: 640px) {

    .individual-log-filters-wrap { padding: 1rem !important; }

    .individual-log-filters { grid-template-columns: minmax(0, 1fr); }

    .individual-filter-actions,

    .individual-status-filter-row { grid-column: 1 / -1; }

}





/* Student Record: responsive sanction request buttons */

.individual-violations-table .individual-actions-col .request-sanction-btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 0.5rem;

    width: 100%;

    max-width: 190px;

    min-height: 44px;

    padding: 0.7rem 0.85rem;

    border-radius: 0.75rem;

    font-size: 0.875rem;

    font-weight: 700;

    line-height: 1.35;

    white-space: normal;

    overflow-wrap: break-word;

    text-align: center;

}



.individual-violations-table .individual-actions-col

.request-sanction-btn i {

    flex: 0 0 auto;

}



/* Confirmation modal */

#requestSanctionConfirmModal .modal-dialog {

    width: calc(100% - 2rem);

    max-width: 480px;

    margin: 1rem auto;

}



#requestSanctionConfirmModal .modal-content {

    border: 0;

    border-radius: 1rem;

    overflow: hidden;

}



#requestSanctionConfirmModal .modal-header {

    flex-wrap: nowrap;

    gap: 0.75rem;

}



#requestSanctionConfirmModal .modal-title {

    min-width: 0;

    overflow-wrap: anywhere;

}



#requestSanctionConfirmModal .modal-body {

    overflow-wrap: anywhere;

}



#requestSanctionConfirmModal .modal-footer {

    display: flex;

    flex-wrap: wrap;

    gap: 0.5rem;

}



#requestSanctionConfirmModal .modal-footer .btn {

    min-height: 44px;

}



/* Mobile phones */

@media (max-width: 575.98px) {

    .individual-violations-table .individual-actions-col {

        padding: 0.75rem !important;

    }



    .individual-violations-table .individual-actions-col

    .request-sanction-btn {

        max-width: 100%;

        min-height: 46px;

        padding: 0.7rem 0.6rem !important;

        font-size: 0.85rem !important;

    }



    #requestSanctionConfirmModal .modal-dialog {

        width: auto;

        max-width: none;

        margin: 0.5rem;

    }



    #requestSanctionConfirmModal .modal-content {

        border-radius: 0.85rem;

    }



    #requestSanctionConfirmModal .modal-header {

        padding: 0.85rem 1rem;

    }



    #requestSanctionConfirmModal .modal-title {

        font-size: 1.05rem;

        line-height: 1.35;

    }



    #requestSanctionConfirmModal .modal-body {

        padding: 1rem;

    }



    #requestSanctionConfirmModal .modal-footer {

        padding: 0.75rem 1rem;

    }



    #requestSanctionConfirmModal .modal-footer .btn {

        flex: 1 1 100%;

        width: 100%;

    }

}



/* Extra-small phones */

@media (max-width: 380px) {

    #requestSanctionConfirmModal .modal-dialog {

        margin: 0.25rem;

    }



    #requestSanctionConfirmModal .modal-header,

    #requestSanctionConfirmModal .modal-body {

        padding: 0.75rem;

    }



    #requestSanctionConfirmModal .modal-footer {

        padding: 0.75rem;

    }

}

/* =====================================================
   SUBMIT PROOF MODAL — RESPONSIVE LAYOUT AND SCROLLING
   ===================================================== */
#submitProofModal .modal-dialog {
    width: calc(100% - 2rem);
    max-width: 830px;
    margin: 1rem auto;
}

#submitProofModal .modal-content {
    display: flex;
    flex-direction: column;
    width: 100%;
    min-width: 0;
    max-height: calc(100vh - 2rem);
    max-height: calc(100dvh - 2rem);
    min-height: 0;
    overflow: hidden;
    border: 0;
    border-radius: 1rem;
    box-shadow: 0 1rem 3rem rgba(25, 15, 15, .22);
}

/* The form must also be a flex container; otherwise its body grows with
   all of its contents and the modal has nothing that can scroll. */
#submitProofModal #submitProofForm {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-width: 0;
    min-height: 0;
    overflow: hidden;
}

#submitProofModal #submitProofForm .modal-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
}

#submitProofModal .modal-header {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    flex: 0 0 auto;
    min-width: 0;
    padding: 1.25rem 1.25rem 1rem;
    border-bottom: 1px solid #eee5e5;
}

#submitProofModal .modal-header > div {
    flex: 1 1 auto;
    min-width: 0;
}

#submitProofModal .modal-title {
    margin: 0 0 .35rem;
    color: #800909;
    font-size: clamp(1.15rem, 3.5vw, 1.45rem);
    line-height: 1.3;
    overflow-wrap: anywhere;
}

#submitProofModal .modal-subtitle {
    color: #52525b;
    font-size: .96rem;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

#submitProofModal .btn-close {
    flex: 0 0 auto;
    margin: .15rem 0 0 auto;
    padding: .55rem;
}

#submitProofModal .modal-body {
    flex: 1 1 auto;
    min-width: 0;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
    overscroll-behavior-y: contain;
    -webkit-overflow-scrolling: touch;
    padding: 1.15rem 1.25rem;
}

#submitProofModal .proof-section {
    min-width: 0;
    margin: 0 0 1.25rem;
    padding: 0 0 1.1rem;
    border-bottom: 1px solid #eee5e5;
}

#submitProofModal .proof-section:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: 0;
}

#submitProofModal .proof-section-title {
    display: flex;
    align-items: center;
    gap: .45rem;
    margin: 0 0 .7rem;
    color: #800909;
    font-size: 1.05rem;
    font-weight: 750;
    line-height: 1.4;
    overflow-wrap: anywhere;
}

#submitProofModal .proof-step-number {
    display: inline-flex;
    flex: 0 0 1.65rem;
    width: 1.65rem;
    height: 1.65rem;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #800909;
    color: #fff;
    font-size: .85rem;
    font-weight: 700;
}

#submitProofModal .proof-detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .75rem;
}

#submitProofModal .proof-detail-item {
    display: flex;
    flex-direction: column;
    gap: .2rem;
    min-width: 0;
    padding: .75rem;
    border: 1px solid #eee2e2;
    border-radius: .65rem;
    background: #fffafa;
}

#submitProofModal .proof-detail-label {
    color: #6b6262;
    font-size: .78rem;
    font-weight: 650;
}

#submitProofModal .proof-detail-value {
    color: #292525;
    font-size: .92rem;
    line-height: 1.45;
    overflow-wrap: anywhere;
    word-break: break-word;
}

#submitProofModal .proof-body-text {
    color: #454047;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

#submitProofModal .official-form-area {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: .5rem;
    margin-top: .85rem;
}

#submitProofModal .official-form-label {
    font-size: .9rem;
    font-weight: 650;
}

#submitProofModal .official-form-area .btn {
    max-width: 100%;
    white-space: normal;
    text-align: center;
}

#submitProofModal .proof-upload-zone {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 0;
    min-height: 180px;
    padding: 1.25rem;
    border: 2px dashed #d8bcbc;
    border-radius: .85rem;
    background: #fffafa;
    text-align: center;
    cursor: pointer;
    transition: border-color .15s ease, background-color .15s ease;
}

#submitProofModal .proof-upload-zone:focus-visible {
    outline: 3px solid rgba(128, 9, 9, .25);
    outline-offset: 3px;
}

#submitProofModal .proof-upload-zone-active {
    border-color: #800909;
    background: #fff1f1;
}

#submitProofModal .proof-file-input {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    clip-path: inset(50%);
    white-space: nowrap;
}

#submitProofModal .proof-upload-icon {
    display: block;
    margin-bottom: .5rem;
    color: #800909;
    font-size: 2rem;
}

#submitProofModal .proof-upload-title {
    color: #302929;
    font-weight: 750;
}

#submitProofModal .proof-upload-hint,
#submitProofModal .proof-formats {
    color: #706767;
    font-size: .84rem;
    overflow-wrap: anywhere;
}

#submitProofModal .proof-formats {
    margin: .5rem 0 0;
}

#submitProofModal .proof-file-selected {
    width: 100%;
    min-width: 0;
}

#submitProofModal .proof-file-chip {
    display: flex;
    align-items: center;
    gap: .7rem;
    min-width: 0;
    margin-bottom: .8rem;
    text-align: left;
}

#submitProofModal .proof-file-icon {
    flex: 0 0 auto;
    color: #198754;
    font-size: 1.6rem;
}

#submitProofModal .proof-file-meta {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

#submitProofModal .proof-file-name {
    overflow-wrap: anywhere;
    word-break: break-word;
    font-weight: 650;
}

#submitProofModal .proof-file-size {
    color: #706767;
    font-size: .82rem;
}

#submitProofModal .proof-guidelines {
    padding-left: 1.25rem;
    margin-bottom: 0;
    color: #454047;
}

#submitProofModal .proof-guidelines li {
    margin-bottom: .4rem;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

#submitProofModal .proof-review-alert {
    padding: .85rem;
    border: 1px solid #ead6a6;
    border-radius: .7rem;
    background: #fff9e9;
    color: #55451e;
}

#submitProofModal .proof-confirm {
    display: grid;
    grid-template-columns: 1.1rem minmax(0, 1fr);
    gap: .6rem;
    align-items: start;
}

#submitProofModal .proof-confirm .form-check-input {
    float: none;
    margin: .25rem 0 0;
}

#submitProofModal .proof-confirm .form-check-label {
    min-width: 0;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

#submitProofModal .proof-confirm .form-text {
    grid-column: 2;
}

#submitProofModal .modal-footer {
    display: flex;
    flex: 0 0 auto;
    flex-wrap: wrap;
    gap: .6rem;
    padding: 1rem 1.25rem;
    border-top: 1px solid #eee5e5;
    background: #fff;
}

#submitProofModal .modal-footer .btn {
    flex: 1 1 140px;
    min-width: 0;
    min-height: 44px;
    white-space: normal;
}

#submitProofModal .invalid-proof-feedback {
    margin-top: .5rem;
    color: #b02a37;
    font-size: .875rem;
    overflow-wrap: anywhere;
}

@media (max-width: 575.98px) {
    #submitProofModal .modal-dialog {
        width: calc(100% - 1rem);
        max-width: none;
        height: calc(100vh - 1rem);
        height: calc(100dvh - 1rem);
        margin: .5rem auto;
    }

    #submitProofModal .modal-content {
        height: 100%;
        max-height: 100%;
        min-height: 0;
        border-radius: .85rem;
    }

    #submitProofModal .modal-header {
        gap: .6rem;
        padding: 1rem;
    }

    #submitProofModal .modal-body {
        flex: 1 1 0%;
        min-height: 0;
        padding: 1rem;
        overflow-y: auto;
    }

    #submitProofModal .modal-footer {
        gap: .5rem;
        padding: .75rem 1rem;
    }

    #submitProofModal .modal-footer .btn {
        flex: 1 1 100%;
        width: 100%;
    }

    #submitProofModal .proof-detail-grid {
        grid-template-columns: minmax(0, 1fr);
        gap: .5rem;
    }

    #submitProofModal .proof-detail-item {
        padding: .65rem .75rem;
    }

    #submitProofModal .proof-upload-zone {
        min-height: 155px;
        padding: 1rem .75rem;
    }

    #submitProofModal .official-form-area .btn {
        width: 100%;
    }
}

@media (max-width: 360px) {
    #submitProofModal .modal-title {
        font-size: 1.08rem;
    }

    #submitProofModal .modal-subtitle {
        font-size: .88rem;
    }
}
/* Mobile filter layout: keep the Clear button in its own row. */
@media (max-width: 575.98px) {
    #individualLogFilters {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        align-items: stretch;
    }

    #individualLogFilters .individual-status-filter-row {
        grid-column: 1 / -1;
        grid-row: auto;
        order: 1;
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 0.4rem;
        min-width: 0;
    }

    #individualLogFilters .individual-status-filter-row #individualFilterCount {
        grid-column: 1;
        justify-self: start;
        text-align: left;
    }

    #individualLogFilters .individual-filter-actions {
        grid-column: 1 / -1;
        grid-row: auto;
        order: 2;
        justify-content: stretch;
        width: 100%;
        margin-top: 0.15rem;
    }

    #individualLogFilters .individual-filter-clear {
        display: block;
        width: 100%;
        min-height: 44px;
        margin: 0;
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
    <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2 individual-log-titlebar">
        <h5 class="mb-0">Individual Violations Log</h5>
        <span class="individual-log-record-count">{{ $violations->total() }} {{ $violations->total() === 1 ? 'record' : 'records' }}</span>
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
    <a class="btn individual-filter-clear"
       href="{{ route('student.record') }}">Clear</a>
</div>


<div class="individual-status-filter-row">
    <label for="filterSanctionStatus">Sanction Status</label>

    <select class="form-select"
            id="filterSanctionStatus"
            name="sanction_status">
        <option value="all" @selected(request('sanction_status', 'all') === 'all')>
            All Statuses
        </option>
        <option value="needs" @selected(request('sanction_status') === 'needs')>
            Needs Request
        </option>
        <option value="no-request" @selected(request('sanction_status') === 'no-request')>
            No Request Required
        </option>
        <option value="pending" @selected(request('sanction_status') === 'pending')>
            Request Pending
        </option>
        <option value="approved" @selected(request('sanction_status') === 'approved')>
            Approved
        </option>
        <option value="declined" @selected(request('sanction_status') === 'declined')>
            Declined
        </option>
        <option value="assigned" @selected(request('sanction_status') === 'assigned')>
            Sanction Assigned
        </option>
    </select>

    <span class="small text-muted"
          id="individualFilterCount"
          aria-live="polite"></span>
</div>

        </form>
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
<th>Available Actions</th>                    </tr>
                </thead>
                <tbody>
                    @forelse($violations as $violation)
                       
@php
    $isPendingLog = strtolower((string) $violation->status) === 'pending';

    $resolvedType = $violation->resolvedViolationType();

    $typeId = (string) (
        $resolvedType?->violation_type_id
        ?? $violation->violation_type_id
        ?? ''
    );

    $categoryId = (string) (
        $resolvedType?->violation_category_id ?? ''
    );

    // Resolve the request for this violation type.
    $sanctionRequest = $typeId !== ''
        ? (
            data_get($sanctionRequestsByType, $typeId)
            ?? data_get($sanctionRequestsByType, (int) $typeId)
        )
        : null;

    $requestStatus = strtolower(trim(
        (string) ($sanctionRequest->status ?? '')
    ));

    $isActiveRequest = (bool) (
        $sanctionRequest->is_active ?? false
    );

    $hasAssignedSanction = isset(
        $assignedSanctionViolationIds[$violation->violation_id]
    );

    // Preserve the existing warning-level detection.
    $isWarningLevelViolation = isset(
        $warningLevelViolationIds[(int) $violation->violation_id]
    );

    // Warning-level violations must not be treated as needing a request.
    $rowSanctionStatus = $isWarningLevelViolation
        ? 'no-request'
        : (
            $hasAssignedSanction
                ? 'assigned'
                : (
                    $requestStatus === 'pending' && $isActiveRequest
                        ? 'pending'
                        : (
                            $requestStatus === 'approved' && !$isActiveRequest
                                ? 'approved'
                                : (
                                    $requestStatus === 'declined' && !$isActiveRequest
                                        ? 'declined'
                                        : 'needs'
                                )
                        )
                )
        );

    $rowDate = \Carbon\Carbon::parse(
        $violation->violation_date
    )->format('Y-m-d');
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
    @elseif($isWarningLevelViolation)
        <div class="warning-sanction-notice p-3 mb-2 small text-start" role="note">
            <strong class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                Warning-Level Violation
            </strong>
            <p class="mb-0">
                A sanction request is not required for this warning-level
                violation. Review the disciplinary details above.
            </p>
        </div>

        <button type="button"
                class="btn btn-outline-danger btn-sm corrective-action-btn mt-2"
                data-violation-id="{{ $violation->violation_id }}">
            <i class="bi bi-pencil-square me-1"></i>
            Request Correction / Deletion
        </button>
    @else
        @if($isActiveRequest)
            <span class="sanction-state sanction-state-pending">
                <i class="bi bi-hourglass-split"></i>Request Pending
            </span>
            <small class="d-block text-muted mt-2">Submitted for admin review</small>
        @elseif($hasAssignedSanction)
            <span class="sanction-state sanction-state-assigned">
                <i class="bi bi-shield-check"></i>Sanction Assigned
            </span>
        @elseif($requestStatus === 'declined' && !$isActiveRequest)
            <span class="sanction-state sanction-state-declined">
                <i class="bi bi-x-circle"></i>Request Declined
            </span>
            <button type="button"
                    class="btn request-sanction-btn mt-2"
                    data-violation-type-id="{{ $typeId }}">
                <i class="bi bi-arrow-repeat"></i> Request Again
            </button>
        @else
            <button type="button"
                    class="btn request-sanction-btn"
                    data-violation-type-id="{{ $typeId }}">
                <i class="bi bi-send"></i> Request Sanction
            </button>
            <small class="d-block text-muted mt-2">Start a sanction request</small>
        @endif

        <button type="button"
                class="btn btn-outline-danger btn-sm corrective-action-btn mt-2"
                data-violation-id="{{ $violation->violation_id }}">
            <i class="bi bi-pencil-square me-1"></i>
            Request Correction / Deletion
        </button>
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

</div><!-- End Violation Record tab pane -->

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
                                        <div class="consultation-notice" role="note">
                                            <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                                            <div>
                                                <span class="consultation-notice-title">Personal consultation required</span>
                                                <p class="consultation-notice-text">{{ \App\Models\StudentSanctionRecord::CONSULTATION_NOTICE }}</p>
                                            </div>
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

{{-- Corrective Action Request Modal --}}
<div class="modal fade" id="correctiveActionModal" tabindex="-1"
     aria-labelledby="correctiveActionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold" id="correctiveActionModalLabel">
                        Request Record Correction or Deletion
                    </h5>
                    <p class="text-muted small mb-0">
                        Your request will be reviewed by an authorized administrator.
                    </p>
                </div>
                <button type="button" class="btn-close"
                        data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-info small">
                    Submitting a request does not immediately change or remove your
                    violation record. Changes require administrative approval.
                </div>

                <div id="correctiveActionError" class="alert alert-danger d-none"
                     role="alert" aria-live="polite"></div>

                <div class="mb-3">
                    <label for="correctiveActionType" class="form-label fw-semibold">
                        What would you like to request?
                    </label>
                    <select id="correctiveActionType" class="form-select" required>
                        <option value="">Choose an action</option>
                        <option value="correction">Correction of Information</option>
                        <option value="deletion">Request Deletion of Record</option>
                    </select>
                </div>

                <div id="correctionFields" class="d-none">
                    <hr>
                    <h6 class="fw-bold">Proposed Correct Information</h6>
                    <p class="small text-muted">
                        Select the correct violation type and provide the updated
                        information you want the administrator to review.
                    </p>

                    <div class="mb-3">
                        <label for="correctionCategory" class="form-label">
                            Violation Category
                        </label>
                        <select id="correctionCategory" class="form-select">
                            <option value="">Select category</option>
                            @foreach($violationCategories as $category)
                                <option value="{{ $category->violation_category_id }}">
                                    {{ $category->category_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="correctionViolationType" class="form-label">
                            Correct Violation Type
                        </label>
                        <select id="correctionViolationType" class="form-select">
                            <option value="">Select violation type</option>
                            @foreach($violationTypes as $type)
                                @if($type->violationCategory)
                                    <option
                                        value="{{ $type->violation_type_id }}"
                                        data-category-id="{{ $type->violation_category_id }}">
                                        {{ $type->violation_type }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="correctionViolationDate" class="form-label">
                            Correct Date and Time
                        </label>
                        <input type="datetime-local" id="correctionViolationDate"
                               class="form-control">
                    </div>

                    <div class="mb-3">
                        <label for="correctionDescription" class="form-label">
                            Correct Remarks / Description
                        </label>
                        <textarea id="correctionDescription" class="form-control"
                                  rows="3" maxlength="2000"
                                  placeholder="Enter the correct information"></textarea>
                    </div>
                </div>

                <div id="deletionFields" class="d-none">
                    <hr>
                    <h6 class="fw-bold">Deletion Request</h6>
                    <p class="small text-muted">
                        Explain why this record should be removed from your active
                        student record. The administrator will review your request
                        and any evidence provided.
                    </p>
                </div>

                <div class="mb-3">
                    <label for="correctiveActionReason" class="form-label fw-semibold">
                        Reason for Request <span class="text-danger">*</span>
                    </label>
                    <textarea id="correctiveActionReason" class="form-control"
                              rows="4" minlength="10" maxlength="2000"
                              placeholder="Explain your request in at least 10 characters."
                              required></textarea>
                    <div class="form-text">Minimum 10 characters.</div>
                </div>
<div id="deletionEvidenceFields" class="d-none">
                <div class="mb-3">
                    <label for="correctiveEvidenceFiles" class="form-label fw-semibold">
                        Supporting Evidence (Optional)
                    </label>
                    <input type="file" id="correctiveEvidenceFiles"
                           class="form-control" multiple
                           accept=".jpg,.jpeg,.png,.webp,.pdf">
                    <div class="form-text">
                        Up to 5 files, maximum 10 MB each. JPG, PNG, WEBP, or PDF.
                    </div>
                </div>

                <div class="border rounded p-3 mb-3">
                    <h6 class="fw-semibold">Optional Camera Evidence</h6>
                    <p class="small text-muted">
                        Only activate the camera if you want to capture a photo
                        as supporting evidence. Your camera will not start automatically.
                    </p>

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" id="startCorrectiveCamera"
                                class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-camera me-1"></i>Open Camera
                        </button>
                        <button type="button" id="captureCorrectivePhoto"
                                class="btn btn-outline-danger btn-sm" disabled>
                            Capture Photo
                        </button>
                        <button type="button" id="stopCorrectiveCamera"
                                class="btn btn-outline-secondary btn-sm" disabled>
                            Turn Camera Off
                        </button>
                        <button type="button" id="removeCorrectivePhoto"
                                class="btn btn-outline-secondary btn-sm d-none">
                            Remove Photo
                        </button>
                    </div>

                    <video id="correctiveCameraPreview"
                           class="w-100 rounded d-none"
                           autoplay muted playsinline
                           style="max-height:300px;background:#111"></video>

                    <canvas id="correctiveCameraCanvas" class="d-none"></canvas>

                    <img id="correctiveCameraPhoto" alt="Captured evidence preview"
                         class="img-fluid rounded d-none"
                         style="max-height:300px">

                    <p id="correctiveCameraStatus" class="small text-muted mb-0"
                       aria-live="polite">Camera is off.</p>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary"
                        data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="submitCorrectiveAction"
                        class="btn btn-danger">
                    <i class="bi bi-send me-1"></i>Submit Request
                </button>
            </div>
        </div>
    </div>
</div></div>

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


<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('correctiveActionModal');
    if (!modalElement) return;

    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const actionSelect = document.getElementById('correctiveActionType');
    const correctionFields = document.getElementById('correctionFields');
    const deletionFields = document.getElementById('deletionFields');
    const categorySelect = document.getElementById('correctionCategory');
    const violationTypeSelect = document.getElementById('correctionViolationType');
    const dateInput = document.getElementById('correctionViolationDate');
    const descriptionInput = document.getElementById('correctionDescription');
    const reasonInput = document.getElementById('correctiveActionReason');
    const evidenceInput = document.getElementById('correctiveEvidenceFiles');
    const submitButton = document.getElementById('submitCorrectiveAction');
    const errorBox = document.getElementById('correctiveActionError');

    const video = document.getElementById('correctiveCameraPreview');
    const canvas = document.getElementById('correctiveCameraCanvas');
    const photoPreview = document.getElementById('correctiveCameraPhoto');
    const cameraStatus = document.getElementById('correctiveCameraStatus');
    const startCameraButton = document.getElementById('startCorrectiveCamera');
    const captureButton = document.getElementById('captureCorrectivePhoto');
    const stopCameraButton = document.getElementById('stopCorrectiveCamera');
    const removePhotoButton = document.getElementById('removeCorrectivePhoto');
const deletionEvidenceFields =
    document.getElementById('deletionEvidenceFields');
    
    let selectedViolationId = null;
    let cameraStream = null;
    let cameraImageData = null;

    const correctionUrlTemplate = "{{ route('student.violations.correction-request', ['violation' => 987654321]) }}";
    const deletionUrlTemplate = "{{ route('student.violations.deletion-request', ['violation' => 987654321]) }}";

    function routeFor(template, id) {
        return template.replace('987654321', encodeURIComponent(String(id)));
    }

    function showError(message) {
        errorBox.textContent = message || 'Unable to submit the request.';
        errorBox.classList.remove('d-none');
    }

    function clearError() {
        errorBox.textContent = '';
        errorBox.classList.add('d-none');
    }

    function stopCamera() {
        if (cameraStream) {
            cameraStream.getTracks().forEach(track => track.stop());
            cameraStream = null;
        }

        video.srcObject = null;
        video.classList.add('d-none');
        captureButton.disabled = true;
        stopCameraButton.disabled = true;
        startCameraButton.disabled = false;
        cameraStatus.textContent = 'Camera is off.';
    }

    function removeCapturedPhoto() {
        cameraImageData = null;
        photoPreview.removeAttribute('src');
        photoPreview.classList.add('d-none');
        removePhotoButton.classList.add('d-none');
    }

    function resetForm() {
        actionSelect.value = '';
        correctionFields.classList.add('d-none');
        deletionFields.classList.add('d-none');

        categorySelect.value = '';
        violationTypeSelect.value = '';
        dateInput.value = '';
        descriptionInput.value = '';
        reasonInput.value = '';
        evidenceInput.value = '';

        clearError();
        removeCapturedPhoto();
        stopCamera();
    }

    document.querySelectorAll('.corrective-action-btn').forEach(button => {
        button.addEventListener('click', function () {
            selectedViolationId = this.dataset.violationId;
            resetForm();
            modal.show();
        });
    });

    actionSelect.addEventListener('change', function () {
        clearError();

        correctionFields.classList.toggle(
            'd-none',
            this.value !== 'correction'
        );
        deletionFields.classList.toggle(
            'd-none',
            this.value !== 'deletion'
        );
    });

    categorySelect.addEventListener('change', function () {
        const categoryId = this.value;

        Array.from(violationTypeSelect.options).forEach(option => {
            if (!option.value) {
                option.hidden = false;
                return;
            }

            option.hidden = !categoryId ||
                option.dataset.categoryId !== categoryId;

            if (option.hidden && option.selected) {
                violationTypeSelect.value = '';
            }
        });
    });

    startCameraButton.addEventListener('click', async function () {
        clearError();

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showError('Camera access is unavailable in this browser or page context. You can upload evidence instead.');
            return;
        }

        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: { facingMode: 'environment' }
            });

            video.srcObject = cameraStream;
            video.classList.remove('d-none');
            captureButton.disabled = false;
            stopCameraButton.disabled = false;
            startCameraButton.disabled = true;
            cameraStatus.textContent =
                'Camera is on. Capture a photo only when you are ready.';
        } catch (error) {
            stopCamera();
            showError('Camera access was not granted or is unavailable. You can still upload evidence.');
        }
    });

    captureButton.addEventListener('click', function () {
        if (!cameraStream || !video.videoWidth || !video.videoHeight) {
            showError('Wait for the camera preview to become ready, then try again.');
            return;
        }

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        const context = canvas.getContext('2d');
        context.drawImage(video, 0, 0, canvas.width, canvas.height);

        cameraImageData = canvas.toDataURL('image/jpeg', 0.85);
        photoPreview.src = cameraImageData;
        photoPreview.classList.remove('d-none');
        removePhotoButton.classList.remove('d-none');
        cameraStatus.textContent = 'Photo captured. It will only be sent if you submit this request.';
        clearError();
        stopCamera();
    });

    stopCameraButton.addEventListener('click', stopCamera);

    removePhotoButton.addEventListener('click', function () {
        removeCapturedPhoto();
        cameraStatus.textContent = 'Captured photo removed.';
    });

    modalElement.addEventListener('hidden.bs.modal', function () {
        stopCamera();
    });

    submitButton.addEventListener('click', async function () {
        clearError();

        if (!selectedViolationId) {
            showError('Please select a violation record first.');
            return;
        }

        const action = actionSelect.value;
        const reason = reasonInput.value.trim();

        if (!['correction', 'deletion'].includes(action)) {
            showError('Please choose correction or deletion.');
            return;
        }

        if (reason.length < 10) {
            showError('Please provide a reason containing at least 10 characters.');
            reasonInput.focus();
            return;
        }

        if (action === 'correction') {
            if (!categorySelect.value || !violationTypeSelect.value ||
                !dateInput.value || !descriptionInput.value.trim()) {
                showError('Please complete the category, violation type, date/time, and description for the proposed correction.');
                return;
            }
        }

        const files = Array.from(evidenceInput.files || []);
        const allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        const maxFileSize = 10 * 1024 * 1024;

        if (files.length > 5) {
            showError('You can attach up to 5 evidence files.');
            return;
        }

        for (const file of files) {
            const extension = (file.name.split('.').pop() || '').toLowerCase();

            if (!allowedExtensions.includes(extension) || file.size > maxFileSize) {
                showError('Each evidence file must be JPG, JPEG, PNG, WEBP, or PDF and no larger than 10 MB.');
                return;
            }
        }

        const formData = new FormData();
        formData.append('reason', reason);

        if (action === 'correction') {
            formData.append('violation_category_id', categorySelect.value);
            formData.append('violation_type_id', violationTypeSelect.value);
            formData.append('violation_date', dateInput.value.replace('T', ' ') + ':00');
            formData.append('description', descriptionInput.value.trim());
        } else {
            files.forEach(file => formData.append('evidence_files[]', file));

            if (cameraImageData) {
                formData.append('camera_image', cameraImageData);
            }
        }

        const url = action === 'correction'
            ? routeFor(correctionUrlTemplate, selectedViolationId)
            : routeFor(deletionUrlTemplate, selectedViolationId);

        submitButton.disabled = true;
        submitButton.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Submitting...';

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

            if (!csrfToken) {
                throw new Error('The security token is missing. Refresh the page and try again.');
            }

            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: formData
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                if (data.errors) {
                    const messages = Object.values(data.errors).flat();
                    throw new Error(messages.join(' '));
                }

                throw new Error(data.message || 'The request could not be submitted.');
            }

            stopCamera();
            modal.hide();

            if (typeof Swal !== 'undefined') {
                await Swal.fire({
                    icon: 'success',
                    title: 'Request Submitted',
                    text: data.message || 'Your request was submitted for administrative review.'
                });
            } else {
                alert(data.message || 'Your request was submitted for review.');
            }

            window.location.reload();
        } catch (error) {
            showError(error.message || 'An unexpected error occurred. Please try again.');
        } finally {
            submitButton.disabled = false;
            submitButton.innerHTML =
                '<i class="bi bi-send me-1"></i>Submit Request';
        }
    });
});
</script>

<script src="{{ asset('js/student-record.js') }}"></script>


@endpush