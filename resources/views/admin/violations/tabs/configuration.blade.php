<div class="card border-0 shadow-sm">

    <div class="card-header bg-white">

        <div class="d-flex justify-content-between align-items-center">

            <div>

                <h5 class="mb-1 admin-section-title">
                    Violation Configuration
                </h5>

                <small class="admin-section-subtitle">
                    Configure violation categories, violation types, and sanctions.
                </small>

            </div>

            <button
                class="admin-btn admin-btn-primary add-category"
                data-bs-toggle="modal"
                data-bs-target="#categoryModal">

                <i class="fas fa-plus me-2"></i>

                Add Violation Category

            </button>

        </div>

    </div>

    <div class="card-body">

        <div class="row gy-4">

            <div class="col-12">
                <div id="categoryAccordionContainer">
                    @include('admin.violations.configuration.category-accordion')
                </div>
            </div>

        </div>

    </div>

</div>