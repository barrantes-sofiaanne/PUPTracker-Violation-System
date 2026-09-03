@extends('layouts.security')

@section('title', 'Submitted Violations')

@section('content')
<div class="container-fluid py-1">
    <div class="page-header-modern d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">Submitted Violations</h2>
            <p class="text-muted mb-0">Track the violations you recorded and their administrative review status.</p>
        </div>
        <a href="{{ route('security.dashboard') }}" class="admin-btn admin-btn-neutral">
            <i class="bi bi-speedometer2 me-1"></i>Back to Dashboard
        </a>
    </div>

    {{-- Status summary chips --}}
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('security.violations.submitted') }}" class="admin-btn admin-btn-sm {{ $status === '' ? 'admin-btn-primary' : 'admin-btn-neutral' }}">
            All ({{ array_sum($counts) }})
        </a>
        <a href="{{ route('security.violations.submitted', ['status' => 'pending']) }}" class="admin-btn admin-btn-sm {{ $status === 'pending' ? 'admin-btn-primary' : 'admin-btn-neutral' }}">
            <i class="bi bi-hourglass-split me-1"></i>Pending ({{ $counts['pending'] }})
        </a>
        <a href="{{ route('security.violations.submitted', ['status' => 'confirmed']) }}" class="admin-btn admin-btn-sm {{ $status === 'confirmed' ? 'admin-btn-primary' : 'admin-btn-neutral' }}">
            <i class="bi bi-check-circle me-1"></i>Confirmed ({{ $counts['confirmed'] }})
        </a>
        <a href="{{ route('security.violations.submitted', ['status' => 'rejected']) }}" class="admin-btn admin-btn-sm {{ $status === 'rejected' ? 'admin-btn-primary' : 'admin-btn-neutral' }}">
            <i class="bi bi-x-circle me-1"></i>Rejected ({{ $counts['denied'] }})
        </a>
    </div>

    {{-- Filters --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('security.violations.submitted') }}" class="row g-2 align-items-end">
                @if($status !== '')
                    <input type="hidden" name="status" value="{{ $status }}">
                @endif
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search student or violation..." value="{{ request('search') }}">
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
                            <tr>
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
                                            <a href="{{ route('security.violations.evidence', $ev) }}" target="_blank" class="d-inline-block small text-decoration-none me-1" title="{{ $ev->original_name }}">
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
                                    @if($violation->review_remarks)
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
</div>
@endsection
