@extends('layouts.admin')

@section('title', 'System Logs')

@section('content')

@push('styles')
<style>
    .system-log-console {
        background: #1f1620;
        color: #e6e1d8;
        font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
        font-size: 0.72rem;
        line-height: 1.5;
        overflow-x: auto;
        max-height: 70vh;
        overflow-y: auto;
    }
    .system-log-console .log-line-error   { color: #ff8a8a; white-space: pre-wrap; word-break: break-word; }
    .system-log-console .log-line-warning { color: #ffd479; white-space: pre-wrap; word-break: break-word; }
    .system-log-console .log-line-info    { color: #a7e0b0; white-space: pre-wrap; word-break: break-word; }
</style>
@endpush

<div class="container-fluid">

    <div class="page-header-modern d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">System Logs</h2>
            <p class="text-muted mb-0">Technical application diagnostics from the Laravel log (Super Admin only).</p>
        </div>
        <a href="{{ route('admin.super-admin.dashboard') }}" class="admin-btn admin-btn-neutral">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Back to Super Admin
        </a>
    </div>

    <div class="alert alert-info small mb-3">
        <i class="bi bi-terminal me-1"></i>
        These are raw technical diagnostics (exceptions, failures). They are <strong>not</strong> part of the audit
        trail or user-management history. No credentials or tokens are displayed here.
    </div>

    <div class="card shadow-sm border-0 mb-4 admin-filter-card">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.super-admin.system-logs') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="level" class="admin-filter-label">Level</label>
                    <select id="level" name="level" class="form-select">
                        <option value="">All levels</option>
                        @foreach($levels as $lvl)
                            <option value="{{ $lvl }}" @selected($level === $lvl)>{{ $lvl }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="admin-btn admin-btn-primary w-100" type="submit"><i class="bi bi-funnel"></i> Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="text-muted small">Last 300 log lines
                @if($level !== '')
                    · filtered to <strong>{{ $level }}</strong>
                @endif
            </span>
            <span class="text-muted small text-truncate ms-3" style="max-width:55%">{{ basename($path) }}</span>
        </div>
        <div class="card-body p-0">
            @if($lines->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-journal-x fs-2 d-block mb-2"></i>
                    No log lines found.
                </div>
            @else
                <div class="p-3 system-log-console">
                    @foreach($lines as $line)
                        @php
                            $lineStr = (string) $line;
                            $cls = str_contains($lineStr, '.ERROR:') || str_contains($lineStr, 'CRITICAL')
                                ? 'log-line-error'
                                : (str_contains($lineStr, '.WARNING:') ? 'log-line-warning' : 'log-line-info');
                        @endphp
                        <div class="{{ $cls }}">{{ $lineStr }}</div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <p class="text-muted small mt-3">
        <i class="bi bi-info-circle me-1"></i>
        Log retention is shorter than accountability records. Historical daily logs rotate per
        <code>config/logging.php</code> (single channel by default).
    </p>

</div>

@endsection
