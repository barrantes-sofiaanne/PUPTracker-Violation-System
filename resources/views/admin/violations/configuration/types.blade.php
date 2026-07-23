<div class="card border-0">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h5 class="mb-1">

                Violation Types

            </h5>

            <small class="text-muted">

                Manage all violation types under each category.

            </small>

        </div>

        <button
            type="button"
            class="btn btn-danger"
            id="btnAddViolationType"
            data-bs-toggle="modal"
            data-bs-target="#violationTypeModal">

            <i class="bi bi-plus-circle me-1"></i>

            Add Violation Type

        </button>

    </div>

    {{-- Table --}}
    <div class="table-responsive">

        <table class="table table-hover align-middle">

           <thead class="table-light">

<tr>

    <th width="60">#</th>

    <th>Category</th>

    <th>Violation Type</th>

    <th>Description</th>

    <th>Resolution No.</th>

    <th>Severity</th>

    <th width="140">Actions</th>

</tr>

</thead>

            <tbody id="violationTypeTableBody">

                @forelse($violationTypes as $type)

                    <tr>

                        <td>

                            {{ $loop->iteration }}

                        </td>

                      <td>
    <span class="badge bg-primary">
        {{ $type->violationCategory->category_name ?? '-' }}
    </span>
</td>

<td>
    <strong>
        {{ $type->violation_type }}
    </strong>
</td>

<td>
    {{ $type->violation_description ?: '-' }}
</td>

<td>
    {{ $type->resolution_number ?: '-' }}
</td>

<td>
    <span class="badge bg-secondary">
        {{ $type->severity_level }}
    </span>
</td>

<td>

    <button
        class="btn btn-warning btn-sm edit-type"
        data-id="{{ $type->violation_type_id }}">

        <i class="bi bi-pencil-square"></i>

    </button>

    <button
        class="btn btn-danger btn-sm delete-type"
        data-id="{{ $type->violation_type_id }}">

        <i class="bi bi-trash"></i>

    </button>

</td>

                    </tr>

                @empty

                    <tr>

                        <td colspan="6" class="text-center py-5">

                            <i class="bi bi-list-ul display-5 text-muted"></i>

                            <p class="mt-3 mb-0">

                                No violation types found.

                            </p>

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>