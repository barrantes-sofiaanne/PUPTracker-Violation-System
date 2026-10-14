@extends('layouts.admin')

@section('title', 'Violation Management')

@section('content')

<div class="container-fluid">

    <div class="page-header-modern mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h3 class="mb-1">Violation Management</h3>
                <p class="mb-0">Track student cases, configure rules, and review violation history.</p>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">

        {{-- Card Header.
             The redundant secondary "Violation Management" heading has been
             REMOVED — the main maroon page header above already names the page.
             The tabs are now the first visible module navigation. --}}
        <div class="card-header bg-white">

            {{-- Main Navigation Tabs.
                 "Violation Confirmations" is deliberately FIRST: it is the queue
                 that needs an administrator's attention. The server decides which
                 tab is active (see $activeViolationsTab) so a direct link,
                 bookmark or notification deep link lands on the right tab. --}}
            @php
                $activeTab = $activeViolationsTab ?? 'confirmations';
            @endphp

            <ul
                class="nav nav-tabs"
                id="violationTabs"
                role="tablist">

                <li class="nav-item" role="presentation">

                    <button
                        class="nav-link {{ $activeTab === 'confirmations' ? 'active' : '' }}"
                        id="confirmations-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#confirmations"
                        type="button"
                        role="tab">

                        <i class="bi bi-shield-exclamation me-1"></i>

                        Violation Confirmations

                        @if(isset($pendingCount) && $pendingCount > 0)
                            <span class="badge text-bg-warning ms-1">{{ $pendingCount }}</span>
                        @endif

                    </button>

                </li>

                <li class="nav-item" role="presentation">

                    <button
                        class="nav-link {{ $activeTab === 'management' ? 'active' : '' }}"
                        id="management-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#management"
                        type="button"
                        role="tab">

                        <i class="bi bi-people-fill me-1"></i>

                        Management

                    </button>

                </li>

                <li class="nav-item" role="presentation">

                    <button
                        class="nav-link"
                        id="configuration-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#configuration"
                        type="button"
                        role="tab">

                        <i class="bi bi-gear-fill me-1"></i>

                        Configuration

                    </button>

                </li>

                <li class="nav-item" role="presentation">

                    <button
                        class="nav-link"
                        id="history-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#history"
                        type="button"
                        role="tab">

                        <i class="bi bi-clock-history me-1"></i>

                        History

                    </button>

                </li>

            </ul>

        </div>

        {{-- Card Body --}}
        <div class="card-body">

            <div class="tab-content">

                {{-- ========================= --}}
                {{-- Violation Confirmations (FIRST) --}}
                {{-- ========================= --}}
                <div
                    class="tab-pane fade {{ $activeTab === 'confirmations' ? 'show active' : '' }}"
                    id="confirmations"
                    role="tabpanel">

                    @if(isset($violations))
                        @include('admin.violations.tabs.confirmations')
                    @endif

                </div>

                {{-- ========================= --}}
                {{-- Management --}}
                {{-- ========================= --}}
                <div
                    class="tab-pane fade {{ $activeTab === 'management' ? 'show active' : '' }}"
                    id="management"
                    role="tabpanel">

                    @include('admin.violations.tabs.management')

                </div>

                {{-- ========================= --}}
                {{-- Configuration --}}
                {{-- ========================= --}}
                <div
                    class="tab-pane fade {{ $activeTab === 'configuration' ? 'show active' : '' }}"
                    id="configuration"
                    role="tabpanel">

                    @include('admin.violations.tabs.configuration')

                </div>

                {{-- ========================= --}}
                {{-- History --}}
                {{-- ========================= --}}
                <div
                    class="tab-pane fade {{ $activeTab === 'history' ? 'show active' : '' }}"
                    id="history"
                    role="tabpanel">

                    @include('admin.violations.tabs.history')

                </div>

            </div>

        </div>

    </div>

</div>

{{-- ========================= --}}
{{-- Modals --}}
{{-- ========================= --}}
@include('admin.violations.partials.student-history-modal')

{{-- Violation History → multi-file evidence list. Outside the tab pane so a
     hidden `.tab-pane` cannot hide it. --}}
@include('admin.violations.partials.violation-evidence-modal')

{{-- Confirmation review/deny/evidence modals. Kept outside the tab pane so a
     hidden `.tab-pane` cannot hide them. Only rendered for the Confirmations
     tab, which is the only tab that has the data they need. --}}
@include('admin.violations.partials.confirmation-modals')

@include('admin.violations.partials.record-violation')
@include('admin.violations.partials.category-modal')

@include('admin.violations.partials.violation-type-modal')

@endsection

@push('scripts')

<script>

window.ViolationRoutes = {

    typeIndex: "{{ route('admin.violation-types.index') }}",

    typeStore: "{{ route('admin.violation-types.store') }}",

    typeShow: "{{ route('admin.violation-types.show', ':id') }}",

    typeUpdate: "{{ route('admin.violation-types.update', ':id') }}",

    typeDelete: "{{ route('admin.violation-types.destroy', ':id') }}",

    studentHistory: "{{ url('/admin/violations/student') }}/",

    searchStudent: "{{ route('admin.violations.searchStudent') }}",

    previewViolation: "{{ route('admin.violations.previewViolation') }}",

    violationTypes: "{{ route('admin.violations.types') }}",

    store: "{{ route('admin.violations.store') }}",

    securityEvent: "{{ route('admin.violations.evidence-security-event') }}",

    categoryIndex: "{{ route('admin.violation-categories.index') }}",

    categoryStore: "{{ route('admin.violation-categories.store') }}",

    categoryShow: "{{ route('admin.violation-categories.show', ':id') }}",

    categoryUpdate: "{{ route('admin.violation-categories.update', ':id') }}",

    categoryDelete: "{{ route('admin.violation-categories.destroy', ':id') }}",

    sanctionIndex: "{{ route('admin.disciplinary-sanctions.index') }}",
sanctionStore: "{{ route('admin.disciplinary-sanctions.store') }}",
sanctionShow: "{{ route('admin.disciplinary-sanctions.show', ':id') }}",
sanctionUpdate: "{{ route('admin.disciplinary-sanctions.update', ':id') }}",
sanctionDelete: "{{ route('admin.disciplinary-sanctions.destroy', ':id') }}",
};

// Consumed by public/js/admin/violation-confirmations.js.
window.ViolationConfirmationRoutes = {

    confirm: "{{ route('admin.violations.confirm', ['violation' => '__ID__']) }}",

    deny: "{{ route('admin.violations.deny', ['violation' => '__ID__']) }}",
};

document.addEventListener('DOMContentLoaded', function () {

    // The SERVER decides the active tab when the URL names one (direct link,
    // bookmark, notification deep link, or a filter submit): it renders the
    // `active` class into the matching tab. localStorage is therefore only a
    // fallback for an in-page reload with no explicit tab, so it can never
    // override an explicit ?tab=... request.
    const urlHasTab = new URLSearchParams(window.location.search).has('tab');

    if (!urlHasTab) {
        const savedTab = localStorage.getItem("violationActiveTab");

        if (savedTab) {
            const trigger = document.querySelector(
                `[data-bs-target="${savedTab}"]`
            );

            if (trigger) {
                bootstrap.Tab.getOrCreateInstance(trigger).show();
            }
        }
    }

    document.querySelectorAll('#violationTabs button').forEach(function(tab){

        tab.addEventListener("shown.bs.tab", function(){

            localStorage.setItem(
                "violationActiveTab",
                tab.getAttribute("data-bs-target")
            );

        });

    });

});
</script>

<script src="{{ asset('js/admin/violations.js') }}"></script>

<script src="{{ asset('js/admin/violation-categories.js') }}"></script>
@vite('resources/js/camera/face-blur-entry.js')
<script src="{{ asset('js/admin/record-violation.js') }}"></script>
<script src="{{ asset('js/admin/violation-types.js') }}"></script>
<script src="{{ asset('js/admin/violation-confirmations.js') }}"></script>
@endpush