@extends('layouts.admin')

@section('title', 'Violation Confirmations')

@section('content')

<div class="container-fluid">
    <div class="page-header-modern d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-1">Violation Confirmations</h3>
            <p class="text-muted mb-0">Review violations recorded by security and confirm or deny them before they appear on the student's record.</p>
        </div>
        <a href="{{ route('admin.violations.index') }}" class="admin-btn admin-btn-neutral">
            <i class="bi bi-arrow-left"></i> Back to Violations
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @error('violation')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror
    @error('reason')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <span class="text-muted small">
            <span class="badge text-bg-warning me-1">{{ $pendingCount }}</span>
            violation(s) awaiting confirmation
        </span>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 admin-filter-form">
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
            <div class="table-responsive">
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
                                        data-evidence='@json($violation->evidence->map(fn ($ev) => [
                                            "id" => $ev->evidence_id,
                                            "code" => $ev->evidence_code ?: ("EVD #" . $ev->evidence_id),
                                            "name" => $ev->original_name ?: "Evidence",
                                            "is_image" => $ev->is_image,
                                            "method" => $ev->capture_method,
                                            "watermarked" => (bool) $ev->watermarked,
                                            "url" => route("admin.violations.evidence", $ev),
                                            "events_url" => route("admin.violations.evidence.events", $ev),
                                        ]))'>
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
</div>

{{-- Review + Confirm / Deny modal --}}
<div class="modal fade admin-modal" id="violationDetailModal" tabindex="-1" aria-labelledby="violationDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content admin-modal-content">
            <div class="modal-header admin-modal-header">
                <h5 class="modal-title admin-modal-title" id="violationDetailModalLabel">Review Violation</h5>
                <button type="button" class="btn-close admin-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body admin-modal-body">
                <div class="mb-2"><strong>Student Number:</strong> <span id="confirmModalStudent">-</span></div>
                <div class="mb-2"><strong>Violation:</strong> <span id="confirmModalViolation">-</span></div>
                <div class="mb-2"><strong>Date:</strong> <span id="confirmModalDate">-</span></div>
                <div class="mb-2"><strong>Recorded By:</strong> <span id="confirmModalRecordedBy">-</span></div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Description / Remarks</label>
                    <div class="border rounded p-3 bg-light" id="confirmModalDescription"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Supporting Evidence</label>
                    <div class="border rounded p-3 bg-light d-flex flex-wrap gap-2" id="confirmModalEvidence">
                        <span class="text-muted small">No evidence attached.</span>
                    </div>
                </div>

                <div class="alert alert-info small mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Confirming adds this violation to the student's record. Denying requires a reason and notifies the student and the recording security officer.
                </div>
            </div>
            <div class="modal-footer admin-modal-footer d-flex justify-content-between">
                <button type="button" class="admin-btn admin-btn-danger" data-bs-toggle="modal" data-bs-target="#denyViolationModal" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Deny
                </button>

                <div class="d-flex gap-2">
                    <button type="button" class="admin-btn admin-btn-neutral" data-bs-dismiss="modal">Close</button>
                    <form id="confirmViolationForm" method="POST">
                        @csrf
                        <button type="submit" class="admin-btn admin-btn-success">
                            <i class="bi bi-check-circle me-1"></i>Confirm Violation
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Deny with reason modal --}}
<div class="modal fade admin-modal" id="denyViolationModal" tabindex="-1" aria-labelledby="denyViolationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content admin-modal-content">
            <div class="modal-header admin-modal-header">
                <h5 class="modal-title admin-modal-title" id="denyViolationModalLabel">Deny Violation</h5>
                <button type="button" class="btn-close admin-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="denyViolationForm" method="POST">
                @csrf
                <div class="modal-body admin-modal-body">
                    <p class="text-muted">
                        The recorded violation will be marked as denied and will <strong>not</strong> appear on the student's record. Provide a reason below.
                    </p>
                    <div class="mb-2"><strong>Student Number:</strong> <span id="denyModalStudent">-</span></div>
                    <label for="denyReason" class="form-label fw-semibold mt-2">Reason <span class="text-danger">*</span></label>
                    <textarea name="reason" id="denyReason" class="form-control" rows="3" placeholder="Required — explain why this violation is being denied..." maxlength="1000" required></textarea>
                </div>
                <div class="modal-footer admin-modal-footer">
                    <button type="button" class="admin-btn admin-btn-neutral" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="admin-btn admin-btn-danger">
                        <i class="bi bi-x-circle me-1"></i>Deny Violation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Evidence Security Events timeline modal --}}
<div class="modal fade admin-modal" id="evidenceEventsModal" tabindex="-1" aria-labelledby="evidenceEventsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content admin-modal-content">
            <div class="modal-header admin-modal-header">
                <h5 class="modal-title admin-modal-title" id="evidenceEventsModalLabel">Evidence Security Events</h5>
                <button type="button" class="btn-close admin-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body admin-modal-body">
                <div class="mb-2">
                    <strong>Evidence ID:</strong> <span id="evidenceEventsCode">-</span>
                </div>
                <div class="alert alert-warning small mb-3">
                    <i class="bi bi-shield-exclamation me-1"></i>
                    These events are <strong>detection indicators</strong> recorded during evidence capture and access. A blur, focus loss, or possible screen-capture event does <strong>not</strong> prove a screenshot occurred — it may be caused by switching applications, browser UI, notifications, or other legitimate interruptions. Events require administrative review and are never an automatic basis for disciplinary action.
                </div>
                <div id="evidenceEventsLoading" class="text-center py-4 text-muted">
                    <div class="spinner-border spinner-border-sm me-2"></div>Loading security events…
                </div>
                <div id="evidenceEventsEmpty" class="text-center py-4 text-muted d-none">
                    No security events recorded for this evidence.
                </div>
                <ul id="evidenceEventsList" class="list-group list-group-flush"></ul>
            </div>
            <div class="modal-footer admin-modal-footer">
                <button type="button" class="admin-btn admin-btn-neutral" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const confirmUrlTemplate = "{{ route('admin.violations.confirm', ['violation' => '__ID__']) }}";
    const denyUrlTemplate = "{{ route('admin.violations.deny', ['violation' => '__ID__']) }}";

    let currentViolationId = null;

    function renderEvidence(evidenceRaw) {
        const container = document.getElementById('confirmModalEvidence');
        if (!container) { return; }

        let items = [];
        try {
            items = evidenceRaw ? JSON.parse(evidenceRaw) : [];
        } catch (e) {
            items = [];
        }

        if (!items.length) {
            container.innerHTML = '<span class="text-muted small">No evidence attached.</span>';
            return;
        }

        container.innerHTML = items.map(function (ev) {
            const preview = ev.is_image
                ? '<img src="' + ev.url + '" alt="evidence" style="max-width:110px;max-height:90px;object-fit:cover;border-radius:6px;display:block">'
                : '<i class="bi bi-file-earmark-pdf-fill" style="font-size:2.2rem;color:#b91c1c"></i>';
            const methodTag = ev.method === 'camera'
                ? '<span class="badge text-bg-light border mt-1" title="Captured with watermark">camera</span>'
                : '<span class="badge text-bg-light border mt-1">upload</span>';
            const wmTag = ev.watermarked
                ? '<span class="badge text-bg-success-subtle border mt-1" title="Watermark baked into image">watermarked</span>'
                : '';
            return '<div style="text-align:center">'
                + '<a href="' + ev.url + '" target="_blank" rel="noopener" title="' + ev.name + '">' + preview + '</a>'
                + '<div class="small text-muted mt-1" style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + (ev.code || ev.name) + '</div>'
                + '<div class="d-flex justify-content-center gap-1 flex-wrap">' + methodTag + wmTag + '</div>'
                + '<button type="button" class="btn btn-sm btn-outline-secondary mt-1 evidence-security-events-btn" data-events-url="' + ev.events_url + '" data-evidence-code="' + (ev.code || ev.name) + '">'
                + '<i class="bi bi-shield-lock me-1"></i>Security Events</button>'
                + '</div>';
        }).join('');
    }

    // Evidence security events timeline.
    const evidenceEventsModalEl = document.getElementById('evidenceEventsModal');
    const evidenceEventsList = document.getElementById('evidenceEventsList');
    const evidenceEventsCode = document.getElementById('evidenceEventsCode');
    const evidenceEventsEmpty = document.getElementById('evidenceEventsEmpty');
    const evidenceEventsLoading = document.getElementById('evidenceEventsLoading');

    function loadEvidenceEvents(eventsUrl) {
        if (!evidenceEventsModalEl) { return; }
        if (evidenceEventsList) { evidenceEventsList.innerHTML = ''; }
        if (evidenceEventsCode) { evidenceEventsCode.textContent = ''; }
        if (evidenceEventsLoading) { evidenceEventsLoading.classList.remove('d-none'); }
        if (evidenceEventsEmpty) { evidenceEventsEmpty.classList.add('d-none'); }

        fetch(eventsUrl, { headers: { Accept: 'application/json' } })
            .then(function (resp) {
                if (!resp.ok) { throw new Error('Unable to load events.'); }
                return resp.json();
            })
            .then(function (data) {
                if (evidenceEventsLoading) { evidenceEventsLoading.classList.add('d-none'); }
                if (evidenceEventsCode) {
                    evidenceEventsCode.textContent = data.evidence.code + ' — ' + (data.evidence.student || 'N/A');
                }
                if (!data.events || !data.events.length) {
                    if (evidenceEventsEmpty) { evidenceEventsEmpty.classList.remove('d-none'); }
                    return;
                }
                if (evidenceEventsList) {
                    evidenceEventsList.innerHTML = data.events.map(function (e) {
                        const sevIcon = e.severity === 'warning' || e.severity === 'critical'
                            ? '<i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>'
                            : '<i class="bi bi-info-circle-fill text-muted me-2"></i>';
                        let metaText = '';
                        if (e.meta) {
                            try {
                                const m = JSON.parse(e.meta);
                                metaText = m.detail ? (' — ' + m.detail) : '';
                            } catch (err) { /* ignore */ }
                        }
                        return '<li class="list-group-item d-flex align-items-start">'
                            + sevIcon
                            + '<div class="flex-grow-1"><div class="fw-semibold small">' + e.type + '</div>'
                            + '<div class="text-muted small">' + (e.created_at || '') + metaText + '</div>'
                            + '<div class="text-muted small" style="font-size:0.75rem">' + (e.ip ? ('IP ' + e.ip) : '') + (e.user_agent ? (' &middot; ' + e.user_agent) : '') + '</div>'
                            + '</div></li>';
                    }).join('');
                }
            })
            .catch(function () {
                if (evidenceEventsLoading) { evidenceEventsLoading.classList.add('d-none'); }
                if (evidenceEventsEmpty) {
                    evidenceEventsEmpty.textContent = 'Unable to load security events.';
                    evidenceEventsEmpty.classList.remove('d-none');
                }
            });
    }

    document.addEventListener('click', function (event) {
        const btn = event.target.closest('.evidence-security-events-btn');
        if (!btn) { return; }
        const eventsUrl = btn.dataset.eventsUrl;
        if (!eventsUrl) { return; }
        const modal = bootstrap.Modal.getOrCreateInstance(evidenceEventsModalEl);
        loadEvidenceEvents(eventsUrl);
        modal.show();
    });

    document.querySelectorAll('[data-bs-target="#violationDetailModal"]').forEach(function (button) {
        button.addEventListener('click', function () {
            currentViolationId = button.dataset.violationId || '';
            document.getElementById('confirmModalStudent').textContent = button.dataset.student || '-';
            document.getElementById('confirmModalViolation').textContent = button.dataset.violation || '-';
            document.getElementById('confirmModalDate').textContent = button.dataset.date || '-';
            document.getElementById('confirmModalRecordedBy').textContent = button.dataset.recordedBy || '-';
            document.getElementById('confirmModalDescription').textContent = button.dataset.description || '-';
            document.getElementById('confirmViolationForm').action = confirmUrlTemplate.replace('__ID__', currentViolationId);
            document.getElementById('denyViolationForm').action = denyUrlTemplate.replace('__ID__', currentViolationId);
            renderEvidence(button.dataset.evidence || '');
        });
    });

    const denyModalEl = document.getElementById('denyViolationModal');
    if (denyModalEl) {
        denyModalEl.addEventListener('show.bs.modal', function () {
            document.getElementById('denyModalStudent').textContent = document.getElementById('confirmModalStudent').textContent;
        });
    }
});
</script>
@endpush
