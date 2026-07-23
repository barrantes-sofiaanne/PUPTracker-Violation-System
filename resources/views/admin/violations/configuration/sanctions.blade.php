<div class="card border-0">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h5 class="mb-1">

                Disciplinary Sanctions

            </h5>

            <small class="text-muted">

                Configure sanctions for every violation type and offense level.

            </small>

        </div>

        <button
            type="button"
            class="btn btn-danger"
            data-bs-toggle="modal"
            data-bs-target="#sanctionModal">

            <i class="bi bi-plus-circle me-1"></i>

            Add Sanction

        </button>

    </div>

    <div class="table-responsive">

        <table class="table table-hover align-middle">

            <thead class="table-light">

                <tr>

                    <th width="60">#</th>

                    <th>Violation Type</th>

                    <th>Offense Level</th>

                    <th>Disciplinary Sanction</th>

                    <th width="140">Actions</th>

                </tr>

            </thead>

            <tbody>

                @forelse($sanctions as $sanction)

                    <tr>

                        <td>

                            {{ $loop->iteration }}

                        </td>

                        <td>

                            {{ $sanction->violationType->violation_type ?? '-' }}

                        </td>

                        <td>

                            <span class="badge bg-warning text-dark">

                                {{ $sanction->offense_level }}

                            </span>

                        </td>

                        <td>

                            {{ $sanction->disciplinary_sanction }}

                        </td>

                        <td>

                            <button
                                class="btn btn-warning btn-sm edit-sanction"
                                data-id="{{ $sanction->disciplinary_sanction_id }}">

                                <i class="bi bi-pencil-square"></i>

                            </button>

                            <button
                                class="btn btn-danger btn-sm delete-sanction"
                                data-id="{{ $sanction->disciplinary_sanction_id }}">

                                <i class="bi bi-trash"></i>

                            </button>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="5"
                            class="text-center py-5">

                            <i class="bi bi-shield-x display-5 text-muted"></i>

                            <p class="mt-3 mb-0">

                                No disciplinary sanctions found.

                            </p>

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>