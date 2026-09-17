{{--
    OSS Staff → Violations → "Submitted Violations" tab panel.

    Extracted verbatim from the former standalone Submitted Violations page
    page when the two Violations tabs were combined onto a single page. The
    table, status chips, filters, pagination and row highlighting are unchanged.

    The resubmit modal deliberately lives OUTSIDE this panel (see
    `partials/resubmit-violation-modal.blade.php`): Bootstrap's inactive
    `.tab-pane` is `display:none`, which would hide any modal nested inside it.

    Expects: $violations, $status, $counts, $highlightViolationId
--}}

@push('styles')
<style>
    tr.resubmit-highlight {
        animation: submittedRowFlash 2.6s ease-in-out 1;
        box-shadow: inset 0 0 0 3px rgba(201, 152, 31, 0.5);
    }

    @keyframes submittedRowFlash {
        0% { background: rgba(201, 152, 31, 0.4); }
        100% { background: transparent; }
    }
</style>
@endpush

{{-- Status summary chips --}}
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('oss-staff.violations.students', ['tab' => 'submitted']) }}" class="admin-btn admin-btn-sm {{ $status === '' ? 'admin-btn-primary' : 'admin-btn-neutral' }}">
        All ({{ array_sum($counts) }})
    </a>
    <a href="{{ route('oss-staff.violations.students', ['tab' => 'submitted', 'status' => 'pending']) }}" class="admin-btn admin-btn-sm {{ $status === 'pending' ? 'admin-btn-primary' : 'admin-btn-neutral' }}">
        <i class="bi bi-hourglass-split me-1"></i>Pending ({{ $counts['pending'] }})
    </a>
    <a href="{{ route('oss-staff.violations.students', ['tab' => 'submitted', 'status' => 'confirmed']) }}" class="admin-btn admin-btn-sm {{ $status === 'confirmed' ? 'admin-btn-primary' : 'admin-btn-neutral' }}">
        <i class="bi bi-check-circle me-1"></i>Confirmed ({{ $counts['confirmed'] }})
    </a>
    <a href="{{ route('oss-staff.violations.students', ['tab' => 'submitted', 'status' => 'rejected']) }}" class="admin-btn admin-btn-sm {{ $status === 'rejected' ? 'admin-btn-primary' : 'admin-btn-neutral' }}">
        <i class="bi bi-x-circle me-1"></i>Rejected ({{ $counts['denied'] }})
    </a>
</div>

{{-- Filters --}}
<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('oss-staff.violations.students') }}" class="row g-2 align-items-end">
            <input type="hidden" name="tab" value="submitted">
            @if($status !== '')
                <input type="hidden" name="status" value="{{ $status }}">
            @endif
            <div class="col-md-4">
                <input type="text" name="submitted_search" class="form-control" placeholder="Search student or violation..." value="{{ request('submitted_search') }}">
            </div>
            <div class="col-auto">
                <button type="submit" class="admin-btn admin-btn-primary">Search</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Violation</th>
                        <th>Remarks</th>
                        <th>Evidence</th>
                        <th>Status</th>
                        <th class="text-end">Review Note</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($violations as $violation)
                        <tr class="{{ ($highlightViolationId ?? 0) > 0 && (int) $violation->violation_id === (int) $highlightViolationId ? 'resubmit-highlight' : '' }}"
                            data-violation-id="{{ $violation->violation_id }}">
                            <td>{{ $violation->violation_date ? \Carbon\Carbon::parse($violation->violation_date)->format('M d, Y h:i A') : '-' }}</td>
                            <td>
                                <strong>{{ $violation->student_number }}</strong>
                                <div class="text-muted small">{{ optional($violation->student)->last_name ?? '' }}, {{ optional($violation->student)->first_name ?? '' }}</div>
                            </td>
                            <td>{{ optional($violation->violationType)->violation_type ?? $violation->violation_type_display }}</td>
                            <td style="max-width:220px; white-space:normal;">{{ $violation->description ?: '-' }}</td>
                            <td>
                                @php $evCount = $violation->evidence->count(); @endphp
                                @if($evCount > 0)
                                    <span class="badge text-bg-light border">{{ $evCount }} file(s)</span>
                                    @foreach($violation->evidence as $ev)
                                        <a href="{{ route('oss-staff.violations.evidence', $ev) }}" target="_blank" class="d-inline-block small text-decoration-none me-1" title="{{ $ev->original_name }}">
                                            <i class="bi {{ $ev->is_image ? 'bi-file-earmark-image' : 'bi-file-earmark-pdf' }}"></i>
                                        </a>
                                    @endforeach
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td>
                                @php $vStatus = strtolower((string) $violation->status); @endphp
                                @if($vStatus === 'pending')
                                    <x-admin.badge status="pending" label="Pending Review" />
                                @elseif($vStatus === 'confirmed')
                                    <x-admin.badge status="confirmed" label="Confirmed" />
                                @elseif($vStatus === 'denied')
                                    <x-admin.badge status="rejected" label="Rejected" />
                                @else
                                    <x-admin.badge status="gray" :label="$violation->status" />
                                @endif
                            </td>
                            <td class="text-end">
                                @if($vStatus === 'denied')
                                    <button type="button"
                                        class="btn btn-sm admin-btn admin-btn-warning resubmit-trigger"
                                        data-violation-id="{{ $violation->violation_id }}"
                                        data-student-number="{{ $violation->student_number }}"
                                        data-student-name="{{ optional($violation->student)->last_name ?? '' }}, {{ optional($violation->student)->first_name ?? '' }}"
                                        data-violation-type="{{ optional($violation->violationType)->violation_type ?? $violation->violation_type_display }}"
                                        data-violation-date="{{ $violation->violation_date ? \Carbon\Carbon::parse($violation->violation_date)->format('Y-m-d\TH:i') : '' }}"
                                        data-description="{{ $violation->description ?? '' }}"
                                        data-reason="{{ $violation->review_remarks ?? '' }}">
                                        <i class="bi bi-arrow-repeat me-1"></i>Resubmit
                                    </button>
                                @elseif($violation->review_remarks)
                                    <span class="small text-muted" title="{{ $violation->review_remarks }}">{{ \Illuminate\Support\Str::limit($violation->review_remarks, 60) }}</span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">No submitted violations found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($violations->hasPages())
        <div class="card-footer bg-white">
            {{ $violations->appends(request()->query())->links() }}
        </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Scroll to + highlight a row when arriving from a notification deep link.
    // The panel may be hidden behind the tab, in which case we wait for the tab
    // to be shown before scrolling (a hidden element cannot be scrolled to).
    const highlightedRow = document.querySelector('tr.resubmit-highlight');

    if (highlightedRow) {
        const revealHighlightedRow = function () {
            highlightedRow.scrollIntoView({ behavior: 'smooth', block: 'center' });

            // The highlight is temporary: once the OSS Staff member interacts with the
            // row it fades away, matching the Student Module's behaviour.
            const clearHighlight = function () {
                highlightedRow.classList.remove('resubmit-highlight');
                ['click', 'focusin', 'keydown'].forEach(function (evt) {
                    highlightedRow.removeEventListener(evt, clearHighlight);
                });
            };

            ['click', 'focusin', 'keydown'].forEach(function (evt) {
                highlightedRow.addEventListener(evt, clearHighlight);
            });
        };

        const submittedTab = document.getElementById('submittedViolationsTab');

        if (!submittedTab || submittedTab.classList.contains('active')) {
            revealHighlightedRow();
        } else {
            submittedTab.addEventListener('shown.bs.tab', revealHighlightedRow, { once: true });
        }
    }

    const modal = document.getElementById('resubmitViolationModal');
    const form = document.getElementById('resubmitViolationForm');
    if (!modal || !form) {
        return;
    }

    const modalInstance = function () {
        return bootstrap.Modal.getOrCreateInstance(modal);
    };

    document.querySelectorAll('.resubmit-trigger').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('resubmitViolationId').value = btn.dataset.violationId;
            document.getElementById('resubmitStudent').value = (btn.dataset.studentName || '') + ' (' + (btn.dataset.studentNumber || '') + ')';
            document.getElementById('resubmitViolationType').value = btn.dataset.violationType || '';
            document.getElementById('resubmitViolationDate').value = btn.dataset.violationDate || '';
            document.getElementById('resubmitDescription').value = btn.dataset.description || '';
            document.getElementById('resubmitReason').textContent = btn.dataset.reason || 'No reason provided.';
            document.getElementById('resubmitEvidence').value = '';
            form.action = "{{ url('oss-staff/violations') }}" + "/" + btn.dataset.violationId + "/resubmit";
            modalInstance().show();
        });
    });
});
</script>
@endpush
