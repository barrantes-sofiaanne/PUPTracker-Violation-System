@extends('layouts.admin')

@section('title', 'Email Incident #' . $incident->incident_id)

@section('content')

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h3 class="mb-1">Email Dispatch Incident #{{ $incident->incident_id }}</h3>
            <p class="text-muted mb-0">
                {{ $incident->email_type ?: 'System Email' }} · {{ optional($incident->occurred_at)->format('M d, Y h:i A') }}
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.super-admin.email-incidents.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @php
        $badge = match ($incident->status) {
            'failed' => 'danger',
            'acknowledged' => 'warning',
            'resolved' => 'success',
            default => 'secondary',
        };
    @endphp

    {{-- Header --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <small class="text-muted d-block">Status</small>
                    <span class="badge bg-{{ $badge }}">{{ ucfirst($incident->status) }}</span>
                </div>
                <div class="col-md-3">
                    <small class="text-muted d-block">Email Type</small>
                    <strong>{{ $incident->email_type ?: '—' }}</strong>
                </div>
                <div class="col-md-3">
                    <small class="text-muted d-block">Related Record</small>
                    <strong>
                        @if ($incident->related_record_type || $incident->related_record_id)
                            {{ $incident->related_record_type ?: 'System' }}
                            @if ($incident->related_record_id)
                                #{{ $incident->related_record_id }}
                            @endif
                        @else
                            —
                        @endif
                    </strong>
                </div>
                <div class="col-md-3">
                    <small class="text-muted d-block">Occurred</small>
                    <strong>{{ optional($incident->occurred_at)->format('M d, Y h:i A') }}</strong>
                </div>
            </div>

            <hr>

            <h6 class="text-muted">Error Summary</h6>
            <p class="mb-0">{{ $incident->safe_error_message ?: '—' }}</p>
        </div>
    </div>

    {{-- Handling timeline --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white"><strong>Acknowledgment</strong></div>
                <div class="card-body">
                    @if ($incident->acknowledged_at)
                        <p class="mb-1">
                            <i class="bi bi-check2-circle text-success me-1"></i>
                            Acknowledged on {{ $incident->acknowledged_at->format('M d, Y h:i A') }}
                        </p>
                        <small class="text-muted">By {{ $incident->acknowledgedBy?->email ?? '—' }}</small>
                    @else
                        <p class="text-muted mb-0">Not yet acknowledged.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white"><strong>Resolution</strong></div>
                <div class="card-body">
                    @if ($incident->resolved_at)
                        <p class="mb-1">
                            <i class="bi bi-check-circle-fill text-success me-1"></i>
                            Resolved on {{ $incident->resolved_at->format('M d, Y h:i A') }}
                        </p>
                        <small class="text-muted">By {{ $incident->resolvedBy?->email ?? '—' }}</small>
                    @else
                        <p class="text-muted mb-0">Not yet resolved.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Actions --}}
    @if ($incident->status !== 'resolved')
        <div class="d-flex gap-2">
            @if ($incident->status === 'failed')
                <form action="{{ route('admin.super-admin.email-incidents.acknowledge', $incident->incident_id) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check2"></i> Acknowledge
                    </button>
                </form>
            @endif
            <form action="{{ route('admin.super-admin.email-incidents.resolve', $incident->incident_id) }}" method="POST"
                  onsubmit="return confirm('Mark this incident as resolved? This records that the incident was handled — it does not confirm the original email was delivered.');">
                @csrf
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check2-circle"></i> Mark as Resolved
                </button>
            </form>
        </div>
        <div class="small text-muted mt-2">
            <i class="bi bi-info-circle"></i>
            Resolving records that the incident has been handled. It does not re-send the original email.
        </div>
    @endif

</div>

@endsection
