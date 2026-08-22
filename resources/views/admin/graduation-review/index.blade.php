@extends('layouts.admin')

@section('title', 'AI-Assisted Graduation Disciplinary Review')

@section('content')

<div class="container-fluid">

    <div class="page-header-modern d-flex justify-content-between align-items-center flex-wrap mb-4">

        <div>
            <h2 class="fw-bold mb-1">
                AI-Assisted Graduation Disciplinary Review
            </h2>
            <p class="mb-0">
                Screen graduating students' disciplinary records and generate AI-assisted summaries.
            </p>
        </div>

        <div class="d-flex gap-2">
            <form action="{{ route('admin.graduation-review.retry-failed') }}" method="POST">
                @csrf
                <button type="submit" class="admin-btn admin-btn-neutral">
                    <i class="bi bi-arrow-repeat"></i>
                    Retry Failed
                </button>
            </form>
            <form action="{{ route('admin.graduation-review.generate-batch') }}" method="POST" id="aiBatchForm">
                @csrf
                <button type="submit" class="admin-btn admin-btn-secondary" id="aiBatchGenerateBtn">
                    <i class="bi bi-stars"></i>
                    Generate AI Review (Batch)
                </button>
            </form>
        </div>

    </div>

    {{-- AI loading overlay --}}
    <div id="aiReviewLoading" class="admin-loading d-none" data-button="aiBatchGenerateBtn" role="status" aria-live="polite">
        <div class="admin-loading-card">
            <span class="spinner-border admin-loading-spinner" aria-hidden="true"></span>
            <div class="admin-loading-text">
                <strong class="admin-loading-label">Generating AI Review...</strong>
                <small class="text-muted d-block mt-1">Analyzing disciplinary records. Please wait...</small>
            </div>
        </div>
    </div>

    {{-- Batch progress (queue) --}}
    <div
        id="batchProgressPanel"
        class="card shadow-sm border-0 mb-4 d-none"
        data-batch-id="{{ session('graduation_review.batch_id', '') }}"
        data-progress-url="{{ route('admin.graduation-review.batch-progress') }}">
        <div class="card-body">
            <div class="d-flex justify-content-between mb-2">
                <strong>AI Review Batch Progress</strong>
                <span id="batchProgressText" class="text-muted">Preparing…</span>
            </div>
            <div class="progress" style="height: 20px;">
                <div id="batchProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%"></div>
            </div>
            <div class="d-flex gap-4 mt-2 small text-muted">
                <span>Completed: <strong id="batchCompleted">0</strong></span>
                <span>Remaining: <strong id="batchRemaining">0</strong></span>
                <span>Failed: <strong id="batchFailed">0</strong></span>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('info'))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            {{ session('info') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->has('ai'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle"></i>
            {{ $errors->first('ai') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Filters --}}
    <div class="card shadow-sm border-0 mb-4 admin-filter-card">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.graduation-review.index') }}" class="row g-3 admin-filter-form">
                <div class="col-md-5">
                    <label class="admin-filter-label">Program</label>
                    <select name="program_id" class="form-select">
                        <option value="">All Programs</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->program_id }}" @selected((int) $programFilter === (int) $program->program_id)>
                                {{ $program->program_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="admin-filter-label">Year</label>
                    <select name="year_id" class="form-select">
                        <option value="">All Years</option>
                        @foreach ($years as $year)
                            <option value="{{ $year->year_id }}" @selected((int) $yearFilter === (int) $year->year_id)>
                                {{ $year->year }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="admin-btn admin-btn-primary w-100">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                    <a href="{{ route('admin.graduation-review.index') }}" class="admin-btn admin-btn-neutral" title="Reset filters" aria-label="Reset filters">
                        <i class="bi bi-arrow-repeat"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Batch report summary --}}
    <div class="row mb-4">

        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <small class="text-muted">Students Reviewed</small>
                    <h2 class="fw-bold mb-0">{{ $report['total'] }}</h2>
                </div>
            </div>
        </div>

        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <small class="text-muted">No Disciplinary History</small>
                    <h2 class="fw-bold text-success mb-0">{{ $report['no_history'] }}</h2>
                </div>
            </div>
        </div>

        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <small class="text-muted">All Sanctions Completed</small>
                    <h2 class="fw-bold text-primary mb-0">{{ $report['all_completed'] }}</h2>
                </div>
            </div>
        </div>

        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <small class="text-muted">Outstanding Sanctions</small>
                    <h2 class="fw-bold text-danger mb-0">{{ $report['outstanding'] }}</h2>
                </div>
            </div>
        </div>

        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <small class="text-muted">Manual Review Recommended</small>
                    <h2 class="fw-bold text-warning mb-0">{{ $report['manual_review'] }}</h2>
                </div>
            </div>
        </div>

        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center d-flex flex-column justify-content-center h-100">
                    <small class="text-muted d-block mb-1">AI Status</small>
                    <span class="badge {{ config('graduation_review.ai.enabled') ? 'bg-success' : 'bg-secondary' }}">
                        {{ config('graduation_review.ai.enabled') ? 'AI Configured' : 'AI Not Configured' }}
                    </span>
                </div>
            </div>
        </div>

    </div>

    {{-- Batch report table --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <strong>Graduating Students — Disciplinary Screening</strong>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Student</th>
                        <th>Program</th>
                        <th>Violations</th>
                        <th>Outstanding</th>
                        <th>Review Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($screened as $student)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $student['student_name'] }}</div>
                                <small class="text-muted">{{ $student['student_number'] }}</small>
                            </td>
                            <td>{{ $student['program'] }}</td>
                            <td>{{ $student['total_violations'] }}</td>
                            <td>
                                @if ((int) $student['outstanding_sanctions'] > 0)
                                    <span class="badge bg-danger">{{ $student['outstanding_sanctions'] }}</span>
                                @else
                                    <span class="text-muted">0</span>
                                @endif
                            </td>
                            <td>
                                @if ((int) $student['total_violations'] === 0)
                                    <x-admin.badge status="success" label="No Immediate Concern" />
                                @elseif ((int) $student['outstanding_sanctions'] > 0)
                                    <x-admin.badge status="outstanding" label="Outstanding Sanction Review" />
                                @elseif ($student['repeated_violations_exist'])
                                    <x-admin.badge status="manual review" label="Manual Review Recommended" />
                                @else
                                    <x-admin.badge status="success" label="No Immediate Concern" />
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.graduation-review.show', $student['student_number']) }}"
                                   class="admin-btn admin-btn-view admin-btn-sm">
                                    <i class="bi bi-eye"></i> Review
                                </a>
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty-state
                            :colspan="6"
                            icon="bi-stars"
                            title="No graduating students found"
                            message="No graduating students were found for the selected filters."
                            :clearUrl="route('admin.graduation-review.index')" />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Prevent duplicate AI batch requests + show loading state.
        const batchForm = document.getElementById('aiBatchForm');
        const batchBtn = document.getElementById('aiBatchGenerateBtn');
        if (batchForm && batchBtn) {
            batchForm.addEventListener('submit', function (event) {
                if (batchBtn.disabled) {
                    event.preventDefault();
                    return;
                }
                window.PUPLoading && window.PUPLoading.start('aiReviewLoading');
                batchBtn.disabled = true;
            });
        }

        const panel = document.getElementById('batchProgressPanel');
        if (!panel) {
            return;
        }

        const bar = document.getElementById('batchProgressBar');
        const text = document.getElementById('batchProgressText');
        const completedEl = document.getElementById('batchCompleted');
        const remainingEl = document.getElementById('batchRemaining');
        const failedEl = document.getElementById('batchFailed');

        const batchId = panel.dataset.batchId || '';
        const progressUrl = panel.dataset.progressUrl || '';

        if (!batchId) {
            return;
        }

        panel.classList.remove('d-none');

        const url = progressUrl + '?batch_id=' + encodeURIComponent(batchId);

        const timer = setInterval(async function () {
            try {
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();

                if (!data.available) {
                    clearInterval(timer);
                    text.textContent = 'No active batch.';
                    return;
                }

                bar.style.width = data.percent + '%';
                text.textContent = data.percent + '%';
                completedEl.textContent = data.processed;
                remainingEl.textContent = data.remaining;
                failedEl.textContent = data.failed;

                if (data.finished) {
                    clearInterval(timer);
                    text.textContent = 'Batch finished (' + data.percent + '%)';
                    bar.classList.remove('progress-bar-animated');
                }
            } catch (error) {
                // ignore transient polling errors
            }
        }, 2000);
    });
</script>
@endpush
