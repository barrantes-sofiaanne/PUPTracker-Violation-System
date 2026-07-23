<div
    class="modal fade"
    id="violationTypeModal"
    tabindex="-1"
    aria-labelledby="violationTypeModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <form id="violationTypeForm">

                @csrf

                <input
                    type="hidden"
                    id="violation_type_id"
                    name="violation_type_id">

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="violationTypeModalLabel">

                        Add Violation Type

                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"></button>

                </div>

<div class="modal-body">

    <!-- Category -->
    <div class="mb-3">
        <label class="form-label">Category</label>

        <input
            type="text"
            id="category_name_display"
            class="form-control"
            readonly>

        <input
            type="hidden"
            id="violation_category_id"
            name="violation_category_id">
    </div>

    <!-- Violation Type -->
    <div class="mb-3">
        <label
            for="violation_type"
            class="form-label">

            Violation Type

        </label>

        <input
            type="text"
            class="form-control"
            id="violation_type"
            name="violation_type"
            placeholder="Enter violation type"
            required>

        <div
            class="invalid-feedback"
            id="violation_name_error">
        </div>
    </div>

    <!-- Description -->
    <div class="mb-3">
        <label
            for="violation_description"
            class="form-label">

            Description

        </label>

        <textarea
            class="form-control"
            id="violation_description"
            name="violation_description"
            rows="4"
            placeholder="Enter description (optional)"></textarea>

        <div
            class="invalid-feedback"
            id="description_error">
        </div>
    </div>

    <!-- Resolution + Severity -->
    <div class="row g-3">

        <div class="col-md-8">

            <label
                for="resolution_number"
                class="form-label">

                Resolution Number

            </label>

            <input
                type="text"
                class="form-control"
                id="resolution_number"
                name="resolution_number"
                placeholder="Example: Board Resolution No. 2024-001">

        </div>

        <div class="col-md-4">

            <label
                for="severity_level"
                class="form-label">

                Severity Level

            </label>

            <select
                class="form-select"
                id="severity_level"
                name="severity_level"
                required>

                <option value="1">Level 1</option>
                <option value="2">Level 2</option>
                <option value="3">Level 3</option>

            </select>

        </div>

    </div>

</div>

<div class="modal-footer">

    <button
        type="button"
        class="btn btn-secondary"
        data-bs-dismiss="modal">

        Cancel

    </button>

    <button
        type="submit"
        class="btn btn-danger"
        id="saveViolationTypeBtn">

        <i class="bi bi-save me-1"></i>

        Save Violation Type

    </button>

</div>

                </div>

            </form>

        </div>

    </div>
    

</div>