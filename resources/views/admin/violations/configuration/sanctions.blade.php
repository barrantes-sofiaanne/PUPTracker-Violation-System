<div class="card border-0">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="input-group sanction-search-group">
            <input
                type="text"
                class="form-control"
                id="sanctionSearchInput"
                placeholder="Search violation type...">
            <span class="input-group-text bg-white">
                <i class="bi bi-search"></i>
            </span>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button
                type="button"
                class="admin-btn admin-btn-secondary addSanctionBtn"
                data-bs-toggle="modal"
                data-bs-target="#sanctionModal">
                <i class="bi bi-plus-lg me-1"></i>
                Add Sanction
            </button>
            <a href="{{ route('admin.audit-logs') }}" class="admin-btn admin-btn-neutral">
                <i class="bi bi-arrow-counterclockwise me-1"></i>
                View History
            </a>
        </div>
    </div>

    @php
        $sanctionsByType = $sanctions->groupBy('violation_type_id');
    @endphp

    @if($sanctionsByType->isEmpty())
        <x-admin.empty-state
            icon="bi-shield-x"
            title="No disciplinary sanctions found"
            message="No disciplinary sanctions have been configured yet."
            :clearUrl="null" />
    @else
        <div class="accordion" id="sanctionAccordionList">
            @foreach($sanctionsByType as $violationTypeId => $typeSanctions)
                @php
                    $first = $typeSanctions->first();
                    $accordionId = 'sanctionType' . $violationTypeId;
                    $isFirst = $loop->first;
                @endphp

                <div class="accordion-item mb-2 sanction-accordion-item" data-violation-type-name="{{ strtolower($first->violationType->violation_type ?? '') }}">
                    <h2 class="accordion-header" id="heading{{ $accordionId }}">
                        <button
                            class="accordion-button {{ $isFirst ? '' : 'collapsed' }} fw-semibold"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#collapse{{ $accordionId }}"
                            aria-expanded="{{ $isFirst ? 'true' : 'false' }}"
                            aria-controls="collapse{{ $accordionId }}">
                            {{ $first->violationType->violation_type ?? 'Uncategorized Violation Type' }}
                        </button>
                    </h2>
                    <div
                        id="collapse{{ $accordionId }}"
                        class="accordion-collapse collapse {{ $isFirst ? 'show' : '' }}"
                        aria-labelledby="heading{{ $accordionId }}"
                        data-bs-parent="#sanctionAccordionList">
                        <div class="accordion-body">
                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                <h5 class="mb-0">Disciplinary Sanctions</h5>
                                <button
                                    type="button"
                                    class="admin-btn admin-btn-secondary admin-btn-sm addSanctionBtn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#sanctionModal"
                                    data-violation-type-id="{{ $violationTypeId }}">
                                    <i class="bi bi-plus-lg me-1"></i>
                                    Add Sanction
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 220px;">Offense Level</th>
                                            <th>Disciplinary Sanction</th>
                                            <th class="text-end" style="width: 240px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($typeSanctions as $sanction)
                                            <tr>
                                                <td>{{ $sanction->offense_level }}</td>
                                                <td>{{ $sanction->disciplinary_sanction }}</td>
                                                <td>
                                                    <div class="d-flex align-items-center justify-content-end gap-2 flex-nowrap">
                                                        <button
                                                            class="admin-btn admin-btn-view admin-btn-sm edit-sanction-btn"
                                                            data-id="{{ $sanction->disciplinary_sanction_id }}">
                                                            <i class="bi bi-pencil-square me-1"></i>
                                                            Update
                                                        </button>
                                                        <button
                                                            class="admin-btn admin-btn-danger admin-btn-sm delete-sanction-btn"
                                                            data-id="{{ $sanction->disciplinary_sanction_id }}">
                                                            <i class="bi bi-trash me-1"></i>
                                                            Delete
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>