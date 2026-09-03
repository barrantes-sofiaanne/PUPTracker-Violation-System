@extends('layouts.admin')

@section('title', 'Evidence Security Events')

@section('content')

<div class="container-fluid">

    <div class="page-header-modern d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">Evidence Security Events</h2>
            <p class="text-muted mb-0">Security events recorded against violation evidence (capture, access, interruption detection).</p>
        </div>
        <a href="{{ route('admin.super-admin.dashboard') }}" class="admin-btn admin-btn-neutral">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Back to Super Admin
        </a>
    </div>

    <div class="alert alert-warning small mb-3">
        <i class="bi bi-shield-exclamation me-1"></i>
        These events are <strong>detection indicators</strong> recorded during evidence capture and access.
        A blur, focus loss, or possible screen-capture event does <strong>not</strong> prove a screenshot
        occurred — it may be caused by legitimate interruptions and requires administrative review.
    </div>

    <div class="card shadow-sm border-0 mb-4 admin-filter-card">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.super-admin.evidence-security-events') }}" class="row g-3 admin-filter-form">
                <div class="col-md-2">
                    <label for="from" class="admin-filter-label">From</label>
                    <input id="from" type="date" name="from" value="{{ request('from') }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label for="to" class="admin-filter-label">To</label>
                    <input id="to" type="date" name="to" value="{{ request('to') }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <label for="event_type" class="admin-filter-label">Event Type</label>
                    <select id="event_type" name="event_type" class="form-select">
                        <option value="">All</option>
                        @foreach($eventTypes as $eventType)
                            <option value="{{ $eventType }}" @selected(request('event_type') === $eventType)>{{ $eventType }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="severity" class="admin-filter-label">Severity</label>
                    <select id="severity" name="severity" class="form-select">
                        <option value="">All</option>
                        @foreach($severities as $severity)
                            <option value="{{ $severity }}" @selected(request('severity') === $severity)>{{ $severity }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="keyword" class="admin-filter-label">Search</label>
                    <input id="keyword" name="keyword" value="{{ request('keyword') }}" class="form-control" placeholder="Event type or IP">
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <span class="text-muted small">
                Showing {{ $events->firstItem() ?? 0 }}–{{ $events->lastItem() ?? 0 }} of {{ $events->total() }} security events
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Event</th>
                            <th>Severity</th>
                            <th>Evidence</th>
                            <th>Violation / Student</th>
                            <th>Actor</th>
                            <th>IP</th>
                            <th>Recorded</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($events as $event)
                            <tr>
                                <td>
                                    <span class="badge {{ $event->severity === 'warning' || $event->severity === 'critical' ? 'text-bg-warning' : 'text-bg-light border' }}">
                                        {{ $event->event_type }}
                                    </span>
                                </td>
                                <td>{{ ucfirst($event->severity ?? 'info') }}</td>
                                <td>
                                    @if($event->evidence)
                                        {{ $event->evidence->evidence_code ?? ('#' . $event->evidence_id) }}
                                    @else
                                        <span class="text-muted">#{{ $event->evidence_id ?? '—' }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($event->violation)
                                        #{{ $event->violation->violation_id }}
                                        <div class="text-muted small">{{ $event->violation->student_number ?? '' }}</div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $event->actor_type ?? 'system' }}
                                    @if($event->actor_id) #{{ $event->actor_id }} @endif
                                </td>
                                <td><span class="small">{{ $event->ip_address ?? '—' }}</span></td>
                                <td>{{ optional($event->created_at)->format('M d, Y h:i A') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-shield-x fs-2 d-block mb-2"></i>
                                    No evidence security events found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex justify-content-center">
        {{ $events->withQueryString()->links() }}
    </div>

</div>

@endsection
