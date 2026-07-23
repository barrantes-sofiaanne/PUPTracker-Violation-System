<div
    class="modal fade"
    id="addCategoryModal"
    tabindex="-1">

    <div class="modal-dialog">

        <form id="addCategoryForm">

            @csrf

            <div class="modal-content">

                <div class="modal-header">

                    <h5 class="modal-title">

                        Add Violation Category

                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <div class="mb-3">

                        <label class="form-label">

                            Category Name

                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="category_name"
                            required>

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
                        class="btn btn-primary">

                        Save Category

                    </button>

                </div>

            </div>

        </form>

    </div>

</div>