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

                <label class="admin-filter-label" for="history_from">

                    Start Date

                </label>

                <input
                    type="date"
                    id="history_from"
                    name="history_from"
                    class="form-control"
                    placeholder="Start Date"
                    value="{{ request('history_from') }}">

            </div>

            <div class="col-md-3">

                <label class="admin-filter-label" for="history_to">

                    End Date

                </label>

                <input
                    type="date"
                    id="history_to"
                    name="history_to"
                    class="form-control"
                    placeholder="End Date"
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

                    <th>Category</th>

                    <th>Evidence</th>

                    <th>Recorded By</th>

                    <th>Status</th>

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

{{-- Category only. The per-violation "Violation Type" column was removed
     from this table by request; the type is still stored on the record and
     remains visible in View / the Violation Summary. --}}
{{ optional(optional($history->violationType)->violationCategory)->category_name
    ?? optional($history->violationType)->violation_type
    ?? '-' }}
                        </td>

                        <td>

                            {{-- EVIDENCE: links to the existing private, authorization-protected
                                 evidence endpoint. Never a raw storage path. --}}
                            @php $evidenceCount = $history->evidence->count(); @endphp

                            @if($evidenceCount === 0)
                                <span class="text-muted">-</span>
                            @elseif($evidenceCount === 1)
                                <a href="{{ route('admin.violations.evidence', $history->evidence->first()) }}"
                                   class="admin-btn admin-btn-view admin-btn-sm"
                                   target="_blank"
                                   rel="noopener"
                                   aria-label="View evidence for this violation">

                                    <i class="bi bi-paperclip me-1"></i>
                                    View Evidence

                                </a>
                            @else
                                {{-- Multiple files: open the evidence list modal. The URLs are the
                                     same authorized endpoint; each is re-checked server-side when
                                     clicked, so the DOM cannot be used to bypass authorization. --}}
                                <button
                                    type="button"
                                    class="admin-btn admin-btn-view admin-btn-sm viewViolationEvidence"
                                    data-evidence="{{ $history->evidence->map(fn ($e) => [
                                        'url' => route('admin.violations.evidence', $e),
                                        'name' => $e->original_name,
                                        'code' => $e->evidence_code,
                                        'method' => $e->capture_method,
                                    ])->values()->toJson() }}"
                                    aria-label="View {{ $evidenceCount }} evidence files for this violation">

                                    <i class="bi bi-paperclip me-1"></i>
                                    View {{ $evidenceCount }} Evidence Files

                                </button>
                            @endif

                        </td>

                        <td>

                            {{ $history->recorded_by_display }}
                        </td>

                        <td>
                            @php $vStatus = strtolower((string) $history->status); @endphp
                            @if($vStatus === 'pending')
                                <x-admin.badge status="pending" label="Awaiting Confirmation" />
                            @elseif($vStatus === 'confirmed' || $vStatus === '')
                                <x-admin.badge status="confirmed" label="Confirmed" />
                            @elseif($vStatus === 'denied')
                                <x-admin.badge status="rejected" label="Denied" />
                            @else
                                <x-admin.badge status="gray" :label="$history->status" />
                            @endif
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
                        :colspan="8"
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