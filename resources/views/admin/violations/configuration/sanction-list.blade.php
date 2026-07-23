<div class="card shadow-sm">

    <div class="card-header d-flex justify-content-between align-items-center">

        <div>

            <h5 class="mb-0">
                Disciplinary Sanctions
            </h5>

            <small class="text-muted">
                Manage disciplinary sanctions for every violation type.
            </small>

        </div>

        <button
            class="btn btn-primary"
            id="addSanctionBtn">

            <i class="fas fa-plus me-2"></i>

            Add Sanction

        </button>

    </div>

    <div class="table-responsive">

        <table class="table table-hover align-middle mb-0">

            <thead class="secondary-light">

                <tr>

                    <th width="250">
                        Violation Type
                    </th>

                    <th width="170">
                        Offense Level
                    </th>

                    <th>
                        Disciplinary Sanction
                    </th>

                    <th width="130">
                        Actions
                    </th>

                </tr>

            </thead>

            <tbody>

            @forelse($sanctions as $sanction)

                <tr>

                    <td>
                        {{ $sanction->violationType->violation_type ?? '-' }}
                    </td>

                    <td>

                        <span class="badge bg-primary">

                            {{ $sanction->offense_level }}

                        </span>

                    </td>

                    <td>

                        {{ $sanction->disciplinary_sanction }}

                    </td>

                    <td>

                        <button
                            class="btn btn-warning btn-sm editSanctionBtn"
                            data-id="{{ $sanction->disciplinary_sanction_id }}">

                            <i class="fas fa-edit"></i>

                        </button>

                        <button
                            class="btn btn-danger btn-sm deleteSanctionBtn"
                            data-id="{{ $sanction->disciplinary_sanction_id }}">

                            <i class="fas fa-trash"></i>

                        </button>

                    </td>

                </tr>

            @empty

                <tr>

                    <td colspan="4" class="text-center py-5">

                        <i class="bi bi-folder2-open fs-3 text-muted"></i>

                        <p class="text-muted mt-2 mb-0">

                            No disciplinary sanctions found.

                        </p>

                    </td>

                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

</div>