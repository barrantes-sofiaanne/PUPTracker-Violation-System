@extends('layouts.admin')

@section('title', 'Bulk Record Violations | PUPTracker')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <div class="text-muted small mb-1"><a href="{{ route('admin.violations.index') }}" class="text-decoration-none">Violations</a> / Bulk Record</div>
            <h3 class="fw-bold mb-1">Bulk Violation Recording</h3>
            <p class="text-muted mb-0">Upload one spreadsheet to record multiple student violations at once.</p>
        </div>
        <a href="{{ route('admin.violations.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back to Violations</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger"><strong>Please review the following:</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-xl-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-file-earmark-arrow-up me-2 text-danger"></i>1. Upload spreadsheet</h5>
                    <p class="text-muted small">Supported formats: CSV, XLSX, and XLS. Maximum file size: 5 MB and 500 data rows per import.</p>
                    <a href="{{ route('admin.violations.bulk-import.template') }}" class="btn btn-outline-primary btn-sm mb-3"><i class="bi bi-download me-1"></i> Download CSV Template</a>
                    <form method="POST" action="{{ route('admin.violations.bulk-import.preview') }}" enctype="multipart/form-data">
                        @csrf
                        <label for="bulkViolationFile" class="form-label fw-semibold">Choose file</label>
                        <input type="file" name="file" id="bulkViolationFile" class="form-control" accept=".csv,.txt,.xlsx,.xls" required>
                        <div class="form-text mb-3">Uploading a new file replaces the current preview.</div>
                        <button type="submit" class="btn btn-danger"><i class="bi bi-search me-1"></i> Analyze & Preview</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-info-circle me-2 text-primary"></i>Spreadsheet format</h5>
                    <p class="small text-muted mb-2">Use one row per violation. The column names below are supported:</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-3">
                            <tbody>
                                <tr><th style="width:34%">Student identification</th><td><code>student_number</code> (recommended), OR <code>first_name</code>, <code>last_name</code>, <code>program</code>, and <code>year</code>. <code>middle_name</code> is optional.</td></tr>
                                <tr><th>Violation details</th><td><code>violation_type</code>, <code>violation_date</code>, <code>description</code></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <ul class="small text-muted mb-0">
                        <li>Violation type must already exist in the system's Violation Configuration.</li>
                        <li>Date format: YYYY-MM-DD. Future dates are not accepted.</li>
                        <li>Student matching is exact. Ambiguous or unmatched students are not imported.</li>
                        <li>Existing same-student, same-type, same-date records are flagged as duplicates.</li>
                        <li>Only active student accounts can receive bulk-recorded violations.</li>
                        <li>Imported admin records are confirmed immediately and trigger the normal student notification.</li>
                        <li>Evidence photos/files are not included in spreadsheet imports.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    @if($preview)
        @php
            $rows = collect($preview['rows'] ?? []);
            $validCount = $rows->where('status', 'Valid')->count();
            $reviewCount = $rows->where('status', 'Needs review')->count();
        @endphp
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 p-4 pb-0">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div><h5 class="fw-bold mb-1">2. Review import results</h5><p class="small text-muted mb-0">Only rows marked Valid will be imported. Review all issues before continuing.</p></div>
                    <div class="d-flex gap-2">
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">{{ $validCount }} Valid</span>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2">{{ $reviewCount }} Needs review</span>
                    </div>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive" style="max-height:560px; overflow:auto">
                    <table class="table table-sm table-hover table-bordered align-middle">
                        <thead class="table-light sticky-top">
                            <tr><th>Row</th><th>Student</th><th>Student No.</th><th>Violation Type</th><th>Date</th><th>Status / Issue</th></tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                                <tr>
                                    <td>{{ $row['line'] }}</td>
                                    <td>{{ $row['student_name'] ?? '—' }}</td>
                                    <td>{{ $row['matched_student_number'] ?? ($row['student_number'] ?? '—') }}</td>
                                    <td>{{ $row['violation_type'] ?? '—' }}</td>
                                    <td>{{ $row['violation_date'] ?? ($row['violation_date'] ?? '—') }}</td>
                                    <td>
                                        @if(($row['status'] ?? '') === 'Valid')
                                            <span class="badge bg-success">Valid</span>
                                        @else
                                            <span class="badge bg-warning text-dark">Needs review</span>
                                            <ul class="small text-danger mb-0 mt-1 ps-3">@foreach(($row['issues'] ?? []) as $issue)<li>{{ $issue }}</li>@endforeach</ul>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
                    <div class="small text-muted">Preview expires after 30 minutes. Importing will recheck student status and duplicates.</div>
                    <form method="POST" action="{{ route('admin.violations.bulk-import.commit') }}" onsubmit="return confirm('Import {{ $validCount }} valid violation record(s)? This action will create confirmed violation records and notify the affected students.');">
                        @csrf
                        <input type="hidden" name="token" value="{{ $preview['token'] }}">
                        <button type="submit" class="btn btn-success" @disabled($validCount === 0)><i class="bi bi-check2-circle me-1"></i> Import {{ $validCount }} Valid Row(s)</button>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
