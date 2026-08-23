    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h5 class="mb-1">

                Violation History

            </h5>

            <small class="text-muted">

                View all recorded student violations.

            </small>

        </div>

    </div>

    {{-- Search & Filters --}}
    <form method="GET" action="{{ route('admin.violations.index') }}" class="mb-4 admin-filter-form">

        <div class="row g-3">

            <div class="col-md-4">

                <input
                    type="text"
                    name="history_search"
                    class="form-control"
                    placeholder="Search student or violation..."
                    value="{{ request('history_search') }}">

            </div>

            <div class="col-md-3">

                <input
                    type="date"
                    name="history_from"
                    class="form-control"
                    value="{{ request('history_from') }}">

            </div>

            <div class="col-md-3">

                <input
                    type="date"
                    name="history_to"
                    class="form-control"
                    value="{{ request('history_to') }}">

            </div>

            <div class="col-md-2 d-grid">

                <button
                    type="submit"
                    class="admin-btn admin-btn-primary">

                    <i class="bi bi-search me-1"></i>

                    Search

                </button>

            </div>

        </div>

    </form>

    {{-- History Table --}}
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <span class="text-muted small">
            Showing {{ $violationHistory->firstItem() ?? 0 }}–{{ $violationHistory->lastItem() ?? 0 }} of {{ $violationHistory->total() }} violations
        </span>
    </div>
    <div class="table-responsive">

        <table class="table table-hover align-middle">

            <thead class="table-light">

                <tr>

                    <th width="60">#</th>

                    <th>Date</th>

                    <th>Student</th>

                    <th>Violation Type</th>

                    <th>Remarks</th>

                    <th>Recorded By</th>

                    <th width="120">Action</th>

                </tr>

            </thead>

            <tbody>

                @forelse($violationHistory as $history)

                    <tr>

                        <td>

                            {{ $loop->iteration }}

                        </td>

                        <td>

                           {{ $history->violation_date
    ? \Carbon\Carbon::parse($history->violation_date)->format('M d, Y')
    : '-' }}

                        </td>

                        <td>
                            {{ $history->student?->last_name ?? $history->student_number }},
                            {{ $history->student?->first_name ?? '' }}

                        </td>

                        <td>

{{ optional($history->violationType)->violation_type ?? '-' }}
                        </td>

                        <td>
                            {{ $history->description ?: '-' }}

                        </td>

                        <td>

                            {{ $history->recorded_by_display }}
                        </td>

                        <td>

                            <button
                                type="button"
                                class="admin-btn admin-btn-view admin-btn-sm viewStudent"
                                data-student="{{ $history->student_number }}"
                                aria-label="View {{ $history->student?->last_name ?? 'student' }}'s violation record">

                                <i class="bi bi-eye me-1"></i>
                                View

                            </button>

                        </td>

                    </tr>

                @empty

                    <x-admin.empty-state
                        :colspan="7"
                        icon="bi-clock-history"
                        title="No violation records found"
                        message="No violation records match your current filters."
                        :clearUrl="route('admin.violations.index')" />

                @endforelse

            </tbody>

        </table>


    <div class="mt-3">

        {{ $violationHistory->appends(request()->except('history_page'))->links() }}

    </div>

</div>