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

        {{-- Card Header --}}
        <div class="card-header bg-white">

            <div class="d-flex justify-content-between align-items-center">

                <h4 class="mb-0">

                    <i class="bi bi-exclamation-octagon-fill text-danger me-2"></i>

                    Violation Management

                </h4>

            </div>

            {{-- Main Navigation Tabs --}}
            <ul
                class="nav nav-tabs mt-3"
                id="violationTabs"
                role="tablist">

                <li class="nav-item" role="presentation">

                    <button
                        class="nav-link active"
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
                {{-- Management --}}
                {{-- ========================= --}}
                <div
                    class="tab-pane fade show active"
                    id="management"
                    role="tabpanel">

                    @include('admin.violations.tabs.management')

                </div>

                {{-- ========================= --}}
                {{-- Configuration --}}
                {{-- ========================= --}}
                <div
                    class="tab-pane fade"
                    id="configuration"
                    role="tabpanel">

                    @include('admin.violations.tabs.configuration')

                </div>

                {{-- ========================= --}}
                {{-- History --}}
                {{-- ========================= --}}
                <div
                    class="tab-pane fade"
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

document.addEventListener("DOMContentLoaded", function () {

    const savedTab = localStorage.getItem("violationActiveTab");

    if (savedTab) {
        const trigger = document.querySelector(
            `[data-bs-target="${savedTab}"]`
        );

        if (trigger) {
            bootstrap.Tab.getOrCreateInstance(trigger).show();
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

<script src="{{ asset('js/admin/record-violation.js') }}"></script>
<script src="{{ asset('js/admin/violation-types.js') }}"></script>
@endpush