{{--
    Admin → Violations → "Violation Confirmations" tab panel.

    Extracted verbatim from the former standalone
    `admin/violations/confirmations.blade.php` page when the two Violations
    views were combined onto a single tabbed page. The table, filters, search,
    pagination, status handling and the confirm/deny review workflow are
    unchanged — only the surrounding page chrome (its own page header and the
    "Back to Violations" link) was removed, because the parent Violations page
    now supplies the header and the tab supplies the navigation.

    The review/deny/evidence modals deliberately live OUTSIDE this panel (in
    `admin/violations/partials/confirmation-modals.blade.php`): Bootstrap's
    inactive `.tab-pane` is `display:none`, which would hide a modal nested
    inside it.

    Expects: $violations, $pendingCount, $highlightId
--}}

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <span class="text-muted small">
        <span class="badge text-bg-warning me-1">{{ $pendingCount }}</span>
        violation(s) awaiting confirmation
    </span>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 admin-filter-form">
            {{-- Keep the parent page on the Confirmations tab across a filter submit. --}}
            <input type="hidden" name="tab" value="confirmations">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search by student number or name" value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <input type="date" name="from" class="form-control" value="{{ request('from') }}">
            </div>
            <div class="col-md-3">
                <input type="date" name="to" class="form-control" value="{{ request('to') }}">
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="admin-btn admin-btn-primary">Search</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive admin-confirmations-table-wrap">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Violation</th>
                        <th>Remarks</th>
                        <th>Recorded By</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($violations as $violation)
                        @php
                            // Build the evidence payload in plain PHP (NOT inside the
                            // HTML attribute) — Blade's @json parser cannot balance a
                            // multi-line arrow function + array literal, which used to
                            // generate mismatched PHP and a 500 ParseError.
                            $evidencePayload = $violation->evidence->map(function ($ev) {
                                return [
                                    'id' => $ev->evidence_id,
                                    'code' => $ev->evidence_code ?: ('EVD #' . $ev->evidence_id),
                                    'name' => $ev->original_name ?: 'Evidence',
                                    'is_image' => (bool) $ev->is_image,
                                    'method' => $ev->capture_method,
                                    'watermarked' => (bool) $ev->watermarked,
                                    'url' => route('admin.violations.evidence', $ev),
                                    'events_url' => route('admin.violations.evidence.events', $ev),
                                ];
                            })->values()->all();
                        @endphp
                        <tr @if((int) $violation->violation_id === (int) $highlightId) class="table-warning" @endif>
                            <td>{{ $violation->violation_id }}</td>
                            <td>{{ $violation->violation_date ? \Carbon\Carbon::parse($violation->violation_date)->format('M d, Y h:i A') : '-' }}</td>
                            <td>
                                <strong>{{ $violation->student_number }}</strong>
                                <div class="text-muted small">
                                    {{ optional($violation->student)->last_name ?? '' }}, {{ optional($violation->student)->first_name ?? '' }}
                                </div>
                            </td>
                            <td>{{ optional($violation->violationType)->violation_type ?? $violation->violation_type_display }}</td>
                            <td style="max-width:260px; white-space:normal;">{{ $violation->description ?: '-' }}</td>
                            <td>{{ $violation->recorded_by_display }}</td>
                            <td class="text-end">
                                <button
                                    type="button"
                                    class="admin-btn admin-btn-view admin-btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#violationDetailModal"
                                    data-violation-id="{{ $violation->violation_id }}"
                                    data-student="{{ $violation->student_number }}"
                                    data-violation="{{ optional($violation->violationType)->violation_type ?? $violation->violation_type_display }}"
                                    data-date="{{ $violation->violation_date ? \Carbon\Carbon::parse($violation->violation_date)->format('M d, Y h:i A') : '-' }}"
                                    data-description="{{ $violation->description ?: 'No remarks recorded.' }}"
                                    data-recorded-by="{{ $violation->recorded_by_display }}"
                                    data-evidence='@json($evidencePayload)'>
                                    <i class="bi bi-eye me-1"></i>Review
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-check2-circle fs-2 d-block mb-2"></i>
                                No violations awaiting confirmation.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($violations->hasPages())
        <div class="card-footer bg-white">
            {{ $violations->appends(request()->except('confirmation_page'))->links() }}
        </div>
    @endif
</div>
