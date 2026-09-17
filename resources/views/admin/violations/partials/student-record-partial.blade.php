{{--
    Student violation record — rendered inside the studentHistoryModal via AJAX.
    (Partial: no layout wrapper so it loads cleanly inside the modal.)
--}}
<div class="violation-history-modal-panel">
    <div class="row g-3 align-items-center mb-3">
        <div class="col-lg-8">
            <div class="row g-3">
                <div class="col-md-6">
                    <small class="text-muted d-block">Student Name</small>
                    <div class="fw-semibold">{{ $student->formal_name ?: '-' }}</div>
                </div>
                <div class="col-md-6">
                    <small class="text-muted d-block">Student Number</small>
                    <div class="fw-semibold">{{ $student->student_number }}</div>
                </div>
                <div class="col-md-4">
                    <small class="text-muted d-block">Program</small>
                    <div>{{ $student->studentInfo?->program?->program_name ?? '-' }}</div>
                </div>
                <div class="col-md-4">
                    <small class="text-muted d-block">Year / Section</small>
                    <div>{{ $student->studentInfo?->year?->year ?? '-' }} · {{ $student->studentInfo?->section?->section_name ?? '-' }}</div>
                </div>
                <div class="col-md-4">
                    <small class="text-muted d-block">Current Status</small>
                    <div>{{ $student->studentInfo?->studentStatus?->status_name ?? '-' }}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="border rounded-3 bg-light p-3 h-100">
                <small class="text-muted d-block">Disciplinary Summary</small>
                <div class="fw-bold text-danger mt-2">{{ $totalViolations }} total violations</div>
                <div class="small text-muted mt-1">
                    {{ $majorViolations }} major · {{ $outstandingSanctions }} outstanding · {{ $completedSanctions }} completed
                </div>
            </div>
        </div>
    </div>

    <h6 class="fw-semibold mt-3 mb-2">Violation Summary</h6>
    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-3">
            <thead class="table-light">
                <tr>
                    <th>Category</th>
                    <th>Violation Type</th>
                    <th>Offense Level</th>
                    <th>Status</th>
                    <th>Remarks</th>
                    <th>Disciplinary Sanction</th>
                </tr>
            </thead>
            <tbody>
                @forelse($summary as $item)
                    <tr>
                        <td>{{ $item['category'] ?? '-' }}</td>
<td>{{ $item['violation_type'] ?? '-' }}</td>                        <td><span class="admin-badge admin-badge-gold">{{ $item['offense_level'] }}</span></td>
                        <td>
                            @php $status = strtolower((string) ($item['violation_status'] ?? 'unknown')); @endphp
                            @if($status === 'sanction')
                                <x-admin.badge status="info" label="Sanction" />
                            @elseif($status === 'warning')
                                <x-admin.badge status="warning" label="Warning" />
                            @else
                                <x-admin.badge status="gray" label="Unknown" />
                            @endif
                        </td>
                        <td>{{ $item['remarks'] }}</td>
                        <td>{{ $item['sanction'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No violations found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h6 class="fw-semibold mb-2">Violation History</h6>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
    <th>Date</th>
    <th>Category</th>
    <th>Violation Type</th>
    <th>Remarks</th>
    <th>Recorded By</th>
    <th>Position</th>
</tr>
            </thead>
            <tbody>
                @forelse($individualViolations as $violation)
                    <tr>
    <td>
        {{ $violation->violation_date
            ? \Carbon\Carbon::parse($violation->violation_date)->format('M d, Y')
            : '-' }}
    </td>

    <td>
        {{ optional($violation->violationType?->violationCategory)->category_name ?? '-' }}
    </td>

    <td>
        {{ $violation->violation_type ?? '-' }}
    </td>

    <td>
        {{ $violation->description ?? '-' }}
    </td>

    <td>
        {{ $violation->recorded_by_display ?? '-' }}
    </td>

    <td>
    @if($violation->recorded_by_security_id)
        Security Personnel
    @elseif($violation->recorder_id)
        Administrator
    @else
        -
    @endif
</td>
</tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No violation records found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($individualViolations->hasPages())
        <div class="mt-3">
            {{ $individualViolations->links() }}
        </div>
    @endif
</div>
