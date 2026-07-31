
<div class="accordion mb-3" id="violationAccordion">
@forelse($categories as $category)
 <div class="accordion-item border-0 shadow-sm mb-3">

<h2 class="accordion-header">

    <div class="d-flex align-items-center w-100">

        <button
            class="accordion-button collapsed flex-grow-1"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#category{{ $category->violation_category_id }}"
            aria-expanded="false"
            aria-controls="category{{ $category->violation_category_id }}">

            {{ strtoupper($category->category_name) }}

        </button>

        <div class="d-flex align-items-center gap-2 ms-2 pe-2 flex-nowrap">

            <button
                type="button"
                class="btn btn-warning btn-sm edit-category"
                data-id="{{ $category->violation_category_id }}"
                data-name="{{ $category->category_name }}"
                title="Edit Category">

                <i class="bi bi-pencil-square me-1"></i>

                Edit

            </button>

            <button
                type="button"
                class="btn btn-danger btn-sm delete-category"
                data-id="{{ $category->violation_category_id }}"
                data-name="{{ $category->category_name }}"
                title="Delete Category">

                <i class="bi bi-trash me-1"></i>

                Delete

            </button>

        </div>

    </div>

</h2>

    <div
        id="category{{ $category->violation_category_id }}"
        class="accordion-collapse collapse"
        data-bs-parent="#violationAccordion">

        <div class="accordion-body">

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h6 class="mb-0 fw-semibold">

            Violation Types

        </h6>

        <small class="text-muted">

            Manage the violation types under this category.

        </small>

    </div>

    <button
        class="btn btn-primary addTypeBtn"
        data-id="{{ $category->violation_category_id }}"
        data-name="{{ $category->category_name }}">

        <i class="bi bi-plus-circle me-2"></i>

        Add Type

    </button>

</div>

           <div class="table-responsive violation-types-table">

    <table class="table table-hover align-middle mb-0">

                    <thead class="secondary-light">

                    <tr>

                        <th width="180">
                            Resolution Order
                        </th>

                        <th width="250">
                            Type of Violation
                        </th>

                        <th>
                            Violation Description
                        </th>

                        <th width="170">
                            Date Published
                        </th>

                        <th width="220">
                            Actions
                        </th>

                    </tr>

                    </thead>

                    <tbody>

                    @forelse($category->violationTypes as $type)

                        <tr>

                            <td>
    <span class="badge bg-secondary">
        {{ $type->resolution_number }}
    </span>
</td>

                            <td>
                                {{ $type->violation_type }}
                            </td>

                            <td>
                                {{ $type->violation_description }}
                            </td>

                            <td>
                                {{ optional($type->created_at)->format('F d, Y') ?? '-' }}
                            </td>

                            <td>

                                <div class="d-flex align-items-center gap-2 flex-nowrap">

                                <button
                                    class="btn btn-warning btn-sm editTypeBtn"
                                    data-id="{{ $type->violation_type_id }}">

                                    <i class="bi bi-pencil-square me-1"></i>

                                    Edit

                                </button>

                                <button
                                    class="btn btn-danger btn-sm deleteTypeBtn"
                                    data-id="{{ $type->violation_type_id }}">

                                    <i class="bi bi-trash me-1"></i>

                                    Delete

                                </button>

                                </div>

                            </td>

                        </tr>

                    @empty

                        <tr>

<td colspan="5" class="text-center py-4">

<i class="bi bi-folder2-open fs-3 text-muted"></i>

<p class="text-muted mt-2 mb-0">

No violation types found.

</p>

</td>

</tr>

                    @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

@empty

<div class="alert alert-warning">

    No violation categories found.

</div>

@endforelse

</div>