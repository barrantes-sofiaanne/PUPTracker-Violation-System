<div class="table-responsive">

    <table class="table table-hover align-middle mb-0">

        <thead class="table-light">

            <tr>

                <th>#</th>
                <th>Violation Type</th>
                <th>Description</th>
                <th>Resolution No.</th>
                <th>Severity</th>
                <th width="140">Actions</th>

            </tr>

        </thead>

        <tbody>

            @forelse($types as $type)

                <tr>

                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $type->violation_type }}</td>
                    <td>{{ $type->violation_description ?: '-' }}</td>
                    <td>{{ $type->resolution_number ?: '-' }}</td>
                    <td>{{ $type->severity_level }}</td>
                    <td>
                        <button class="btn btn-sm btn-warning edit-type" data-id="{{ $type->violation_type_id }}">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <button class="btn btn-sm btn-danger delete-type" data-id="{{ $type->violation_type_id }}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>

                </tr>

            @empty

                <tr>

                    <td colspan="6" class="text-center py-5">

                        <i class="bi bi-folder2-open fs-3 text-muted"></i>

                        <p class="text-muted mt-2 mb-0">No violation types found.</p>

                    </td>

                </tr>

            @endforelse

        </tbody>

    </table>

</div>
