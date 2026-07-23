<div class="card border-0">

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
    <form method="GET" action="{{ route('admin.violations.index') }}" class="mb-4">

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
                    class="btn btn-primary">

                    <i class="bi bi-search me-1"></i>

                    Search

                </button>

            </div>

        </div>

    </form>

    {{-- History Table --}}
    <div class="table-responsive">

        <table class="table table-hover align-middle">

            <thead class="table-light">

                <tr>

                    <th width="60">#</th>

                    <th>Date</th>

                    <th>Student</th>

                    <th>Violation Type</th>

                    <th>Sanction</th>

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

{{ trim(
    ($history->recorder?->adminInfo?->first_name ?? '') . ' ' .
    ($history->recorder?->adminInfo?->last_name ?? '')
) ?: '-' }}

                        </td>

                        <td>

{{ optional($history->violationType)->violation_type ?? '-' }}
                        </td>

                        <td>

                            {{ $history->sanction->sanction ?? '-' }}

                        </td>

                        <td>

{{ optional(optional($history->recorder)->adminInfo)->first_name ?? '-' }}                        </td>

                        <td>

                            <button
                                class="btn btn-info btn-sm view-history"
                                data-id="{{ $history->violation_record_id }}">

                                <i class="bi bi-eye"></i>

                            </button>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td colspan="7" class="text-center py-5">

                            <i class="bi bi-clock-history display-5 text-muted"></i>

                            <p class="mt-3 mb-0">

                                No violation records found.

                            </p>

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

    <div class="mt-3">

        {{ $violationHistory->links() }}

    </div>

</div>