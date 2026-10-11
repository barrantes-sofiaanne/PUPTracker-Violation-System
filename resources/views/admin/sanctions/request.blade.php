@extends('layouts.admin')

@section('title', 'Sanction Request')

@section('content')
<div class="container-fluid">
    <div class="admin-breadcrumb-standalone mb-3">
        <a href="{{ route('admin.sanctions.index', ['sanctions_tab' => 'sanction-requests']) }}" class="text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i>Sanction Requests
        </a>
    </div>

    <div class="page-header-modern mb-3">
        <div>
            <h3 class="mb-1">Disciplinary Sanction Request #{{ $sanctionRequest->request_id }}</h3>
            <p class="mb-0">Review the exact request associated with this notification.</p>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-6">
                    <span class="admin-filter-label">Student</span>
                    <h5 class="mb-1">{{ trim(($sanctionRequest->student?->first_name ?? '') . ' ' . ($sanctionRequest->student?->last_name ?? '')) ?: 'Student record unavailable' }}</h5>
                    <div class="text-muted">{{ $sanctionRequest->student_number }}</div>
                </div>
                <div class="col-md-3">
                    <span class="admin-filter-label">Status</span>
                    <div><x-admin.badge :status="strtolower($sanctionRequest->status ?: 'pending')" :label="$sanctionRequest->status ?: 'Pending'" /></div>
                </div>
                <div class="col-md-3">
                    <span class="admin-filter-label">Requested</span>
                    <div>{{ optional($sanctionRequest->request_date)->format('M d, Y h:i A') ?: 'Date unavailable' }}</div>
                </div>
                <div class="col-md-6">
                    <span class="admin-filter-label">Violation Type</span>
                    <div>{{ $sanctionRequest->violationType?->violation_type ?: 'Violation type unavailable' }}</div>
                </div>
                <div class="col-md-6">
                    <span class="admin-filter-label">Offense Level</span>
                    <div>{{ $offenseLevel ?: 'Not available' }}</div>
                </div>
                <div class="col-12">
                    <span class="admin-filter-label">Violation Remarks</span>
                    <div class="border rounded-3 p-3 bg-light">{{ $latestViolation?->description ?: 'No remarks recorded.' }}</div>
                </div>
                <div class="col-12">
                    <span class="admin-filter-label">Sanction Details</span>
                    <div class="border rounded-3 p-3 bg-light">{{ $assignedSanction?->disciplinary_sanction ?: 'No matching sanction is configured yet.' }}</div>
                </div>
            </div>

            @if($sanctionRequest->is_active)
                <div class="border-top mt-4 pt-4">
                    <form action="{{ route('admin.sanctions.requests.approve', $sanctionRequest) }}" method="POST" class="d-flex flex-wrap align-items-end gap-2" data-sanction-text="{{ $assignedSanction?->disciplinary_sanction ?? '' }}" data-service-sanction="{{ !empty($isServiceSanction) ? '1' : '0' }}">
                        @csrf
                        <div>
                            <label for="notification_date" class="admin-filter-label">Date Assigned</label>
                            <input type="date" id="notification_date" name="notification_date" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                        </div>
                            <div>
                                <label for="deadline_date" class="admin-filter-label">Deadline (if needed)</label>
                                <input type="date" id="deadline_date" name="deadline_date" class="form-control" value="{{ $suggestedDeadline?->format('Y-m-d') }}">
                                <small class="text-muted" id="deadlineHelp">Auto-filled from the stated sanction duration when available. You can adjust it.</small>
                        </div>

                        {{-- Community Service / Campus Service assignment fields.
                             Only rendered (and only required) for those two types. --}}
                        @if(!empty($isServiceSanction))
                            <div data-service-field>
                                <label for="designated_office" class="admin-filter-label">Designated Office <span class="text-danger">*</span></label>
                                <input type="text" id="designated_office" name="designated_office" class="form-control" value="{{ old('designated_office') }}" placeholder="e.g. Office of Student Affairs" maxlength="255" required>
                            </div>
                            <div data-service-field>
                                <label for="starting_date" class="admin-filter-label">Starting Date <span class="text-danger">*</span></label>
                                <input type="date" id="starting_date" name="starting_date" class="form-control" value="{{ old('starting_date', now()->format('Y-m-d')) }}" required>
                                <small class="text-muted">Date the student is authorized to start rendering the service.</small>
                            </div>
                        @endif

                        <button type="submit" class="admin-btn admin-btn-success"><i class="bi bi-check2-circle"></i>Approve &amp; Notify</button>
                    </form>
                        @push('scripts')
                        <script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('form[data-sanction-text]');
        const notificationDate = document.getElementById('notification_date');
        const deadlineDate = document.getElementById('deadline_date');
        const deadlineHelp = document.getElementById('deadlineHelp');

        if (!form || !notificationDate || !deadlineDate) {
            return;
        }

        const words = {
            a: 1, an: 1, one: 1, two: 2, three: 3, four: 4, five: 5,
            six: 6, seven: 7, eight: 8, nine: 9, ten: 10, eleven: 11, twelve: 12,
        };
        const pattern = /\b(\d+|a|an|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve)\s+(day|week|month|year)s?\b/i;
        let manuallyEdited = false;

        function suggestedDeadline() {
            const match = String(form.dataset.sanctionText || '').toLowerCase().match(pattern);
            const start = notificationDate.value ? new Date(notificationDate.value + 'T00:00:00') : null;
            if (!match || !start || Number.isNaN(start.getTime())) {
                return '';
            }

            const amount = /^\d+$/.test(match[1]) ? Number(match[1]) : words[match[1]];
            if (!amount || amount < 1) {
                return '';
            }

            if (match[2] === 'day') start.setDate(start.getDate() + amount);
            if (match[2] === 'week') start.setDate(start.getDate() + amount * 7);
            if (match[2] === 'month') start.setMonth(start.getMonth() + amount);
            if (match[2] === 'year') start.setFullYear(start.getFullYear() + amount);

            return start.toISOString().slice(0, 10);
        }

        deadlineDate.addEventListener('input', function () {
            manuallyEdited = deadlineDate.value !== '';
        });

        notificationDate.addEventListener('change', function () {
            if (!manuallyEdited) {
                deadlineDate.value = suggestedDeadline();
            }
        });

        if (!deadlineDate.value) {
            deadlineDate.value = suggestedDeadline();
        }
    });
    </script>
    @endpush
                    <form action="{{ route('admin.sanctions.requests.decline', $sanctionRequest) }}" method="POST" class="mt-2">
                        @csrf
                        <button type="submit" class="admin-btn admin-btn-danger"><i class="bi bi-x-circle"></i>Decline Request</button>
                    </form>
                </div>
            @else
                <div class="border-top mt-4 pt-4 text-muted">This request has already been processed.</div>
            @endif
        </div>
    </div>
</div>
@endsection