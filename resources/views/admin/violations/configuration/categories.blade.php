<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">Violation Categories</h5>
            <small class="text-muted">Manage violation categories used across the system.</small>
        </div>
        <button class="btn btn-primary add-category" data-bs-toggle="modal" data-bs-target="#categoryModal">
            <i class="bi bi-plus-circle me-2"></i>
            Add Category
        </button>
    </div>
    <div class="card-body">
        @include('admin.violations.configuration.category-accordion')
    </div>
</div>
