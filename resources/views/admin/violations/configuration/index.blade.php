<div class="card shadow-sm border-0">

    <div class="card-header bg-white d-flex justify-content-between align-items-center">

        <div>
            <h5 class="mb-0">
                Violation Categories
            </h5>

            <small class="text-muted">
                Manage violation categories used throughout the system.
            </small>
        </div>

        <button
            class="btn btn-primary add-category"
            data-bs-toggle="modal"
            data-bs-target="#addCategoryModal">

            <i class="bi bi-plus-circle me-2"></i>

            Add Category

        </button>

    </div>

    <div class="card-body">

<div class="card shadow-sm border-0">

    <div class="card-header bg-white">

        <h5 class="mb-1">
            Violation Configuration
        </h5>

        <small class="text-muted">
            Manage categories, violation types, and disciplinary sanctions.
        </small>

    </div>

    <div class="card-body">

        <ul class="nav nav-tabs mb-4" id="configurationTabs">

            <li class="nav-item">

                <button
                    class="nav-link active"
                    data-bs-toggle="tab"
                    data-bs-target="#categoriesTab">

                    Categories

                </button>

            </li>

            <li class="nav-item">

                <button
                    class="nav-link"
                    data-bs-toggle="tab"
                    data-bs-target="#typesTab">

                    Violation Types

                </button>

            </li>

            <li class="nav-item">

                <button
                    class="nav-link"
                    data-bs-toggle="tab"
                    data-bs-target="#sanctionsTab">

                    Sanctions

                </button>

            </li>

        </ul>

        <div class="tab-content">

            <div
                class="tab-pane fade show active"
                id="categoriesTab">

                <div id="categoryAccordionContainer">
                    @include('admin.violations.configuration.category-accordion')
                </div>

            </div>

            <div
                class="tab-pane fade"
                id="typesTab">

                @include('admin.violations.configuration.types')

            </div>

            <div
                class="tab-pane fade"
                id="sanctionsTab">

                <div id="sanctionContainer">

                    @include('admin.violations.configuration.sanctions')

                </div>

            </div>

        </div>

    </div>

</div>

@include('admin.violations.partials.category-modal')
@include('admin.violations.partials.violation-type-modal')
@include('admin.violations.partials.sanction-modal')

@push('scripts')
<script src="{{ asset('js/admin/violation-categories.js') }}"></script>
<script src="{{ asset('js/admin/violation-types.js') }}"></script>
<script src="{{ asset('js/admin/sanction.js') }}"></script>
@endpush
    </div>

</div>