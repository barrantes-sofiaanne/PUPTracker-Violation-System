<!-- Disciplinary Sanction Modal -->
<div class="modal fade"
     id="sanctionModal"
     tabindex="-1"
     aria-labelledby="sanctionModalLabel"
     aria-hidden="true">

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <form id="sanctionForm">

                @csrf

                <input type="hidden"
                       id="sanction_id"
                       name="disciplinary_sanction_id">

                <div class="modal-header">

                    <h5 class="modal-title fw-bold"
                        id="sanctionModalLabel">

                        Add Disciplinary Sanction

                    </h5>

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <div class="mb-3">

                        <label class="form-label">

                            Violation Type

                        </label>

<select
    class="form-select"
    id="violation_type_id"
    name="violation_type_id"
    required>

    <option value="">
        -- Select Violation Type --
    </option>

    @php
        $groupedTypes = $violationTypes->groupBy(function ($type) {
            return optional($type->violationCategory)->category_name ?? 'Uncategorized';
        });
    @endphp

    @foreach($groupedTypes as $category => $types)

        <optgroup label="{{ $category }}">

            @foreach($types as $type)

                <option value="{{ $type->violation_type_id }}">
                    {{ $type->violation_type }}
                </option>

            @endforeach

        </optgroup>

    @endforeach

</select>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">

                            Offense Level

                        </label>

                        <select
                            class="form-select"
                            id="offense_level"
                            name="offense_level"
                            required>

                            <option value="">
                                -- Select Offense Level --
                            </option>

                            <option value="1st Offense">
                                1st Offense
                            </option>

                            <option value="2nd Offense">
                                2nd Offense
                            </option>

                            <option value="3rd Offense">
                                3rd Offense
                            </option>

                            <option value="4th Offense">
                                4th Offense
                            </option>

                            <option value="5th Offense">
                                5th Offense
                            </option>

                            <option value="__other__">
                                Other / Custom Offense Level...
                            </option>

                        </select>

                        <div
                            class="mt-2 d-none"
                            id="offenseLevelCustomWrap">

                            <input
                                type="text"
                                class="form-control"
                                id="offense_level_custom"
                                name="offense_level_custom"
                                placeholder="Enter custom offense level (e.g. Major, Minor, Warning)">

                        </div>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">

                            Disciplinary Sanction

                        </label>

                        <textarea
                            class="form-control"
                            id="disciplinary_sanction"
                            name="disciplinary_sanction"
                            rows="5"
                            required></textarea>

                    </div>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="admin-btn admin-btn-neutral"
                        data-bs-dismiss="modal">

                        Cancel

                    </button>

                    <button
                        type="submit"
                        class="admin-btn admin-btn-primary"
                        id="saveSanctionBtn">

                        <i class="fas fa-save me-2"></i>

                        Save

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>