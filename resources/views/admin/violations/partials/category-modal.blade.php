<div
    class="modal fade"
    id="categoryModal"
    tabindex="-1"
    aria-labelledby="categoryModalTitle"
    aria-hidden="true">

    <div class="modal-dialog">

        <div class="modal-content">

            <form id="categoryForm">

                @csrf

                <input
                    type="hidden"
                    id="category_id"
                    name="category_id">

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="categoryModalTitle">

                        Add Violation Category

                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"></button>

                </div>

                <div class="modal-body">

                    <div class="mb-3">

                        <label
                            for="category_name"
                            class="form-label">

                            Category Name
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="category_name"
                            name="category_name"
                            placeholder="Enter category name"
                            required>

                        <div
                            class="invalid-feedback"
                            id="category_name_error">

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
                        id="saveCategoryBtn">

                        <i class="bi bi-save me-1"></i>

                        Save Category

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>