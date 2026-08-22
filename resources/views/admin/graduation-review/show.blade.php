@extends('layouts.admin')

@section('title', 'Graduation Review — ' . $student->student_number)

@section('content')

<div class="container-fluid">

    <x-admin.page-header title="Graduation Disciplinary Review" :description="$student->last_name . ', ' . $student->first_name . ' ' . $student->middle_name . ' · ' . $student->student_number">
        <x-slot name="actions">
            <a href="{{ route('admin.graduation-review.index') }}" class="admin-btn admin-btn-neutral">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <form action="{{ route('admin.graduation-review.generate', $student->student_number) }}" method="POST" id="aiGenerateForm">
                @csrf
                <button type="submit" class="admin-btn admin-btn-secondary" id="aiGenerateBtn">
                    <i class="bi bi-stars"></i> Generate AI Review
                </button>
            </form>
        </x-slot>
    </x-admin.page-header>

    {{-- AI loading state --}}
    <div id="aiReviewLoading" class="admin-loading d-none" data-button="aiGenerateBtn" role="status" aria-live="polite">
        <div class="admin-loading-card">
            <span class="spinner-border admin-loading-spinner" aria-hidden="true"></span>
            <div class="admin-loading-text">
                <strong class="admin-loading-label">Generating AI Review...</strong>
                <small class="text-muted d-block mt-1">Analyzing disciplinary records. Please wait...</small>
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

    <div class="row g-4">

        {{-- System Screening (deterministic, Laravel) --}}
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white d-flex align-items-center gap-2">
                    <i class="bi bi-database-check text-primary"></i>
                    <strong>System Screening</strong>
                    <span class="badge bg-secondary ms-auto">Deterministic</span>
                </div>
                <div class="card-body">
                    <div class="row text-center g-3 mb-3">
                        <div class="col-4">
                            <div class="border rounded-3 py-2">
                                <div class="h3 fw-bold mb-0 text-danger">{{ $screening['total_violations'] }}</div>
                                <small class="text-muted">Violations</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded-3 py-2">
                                <div class="h3 fw-bold mb-0 text-success">{{ $screening['completed_sanctions'] }}</div>
                                <small class="text-muted">Completed</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded-3 py-2">
                                <div class="h3 fw-bold mb-0 text-warning">{{ $screening['outstanding_sanctions'] }}</div>
                                <small class="text-muted">Outstanding</small>
                            </div>
                        </div>
                    </div>

                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Program</span>
                            <strong>{{ $screening['program'] }}</strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Year / Section</span>
                            <strong>{{ $screening['year'] }} / {{ $screening['section'] }}</strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Pending Sanctions</span>
                            <strong>{{ $screening['pending_sanctions'] }}</strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Most Recent Violation</span>
                            <strong>
                                {{ $screening['most_recent_violation_date'] ?? '—' }}
                                @if ($screening['most_recent_violation_type'])
                                    <span class="text-muted">({{ $screening['most_recent_violation_type'] }})</span>
                                @endif
                            </strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Repeated Violations</span>
                            <strong>
                                @if ($screening['repeated_violations_exist'])
                                    <span class="badge bg-warning text-dark">Yes</span>
                                @else
                                    <span class="badge bg-secondary">No</span>
                                @endif
                            </strong>
                        </li>
                    </ul>

                    @if ($screening['requires_factual_review'])
                        <div class="alert alert-danger mt-3 mb-0">
                            <i class="bi bi-exclamation-octagon"></i>
                            Requires factual review (outstanding sanction or repeated violations).
                        </div>
                    @else
                        <div class="alert alert-success mt-3 mb-0">
                            <i class="bi bi-check-circle"></i>
                            No factual red flags detected.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- AI Analysis --}}
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white d-flex align-items-center gap-2">
                    <i class="bi bi-stars text-warning"></i>
                    <strong>AI Analysis</strong>
                    <span class="badge bg-warning text-dark ms-auto">AI-Assisted Only</span>
                </div>
                <div class="card-body">

                    @if ($savedReview)
                        @php
                            $statusLabel = match ($savedReview->review_status) {
                                'no_immediate_concern' => ['No Immediate Concern', 'success'],
                                'outstanding_sanction_review' => ['Outstanding Sanction Review', 'danger'],
                                default => ['Manual Review Recommended', 'warning'],
                            };
                        @endphp

                        @if ($savedReview->is_outdated)
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                AI review is outdated because the student's disciplinary record has changed.
                                <form action="{{ route('admin.graduation-review.generate', $student->student_number) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="admin-btn admin-btn-secondary admin-btn-sm">
                                        <i class="bi bi-arrow-repeat"></i> Regenerate AI Review
                                    </button>
                                </form>
                            </div>
                        @endif

                        @if (! empty($savedReview->data_contradictions))
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-octagon"></i>
                                <strong>Data Quality Alert</strong>
                                <ul class="mb-0 mt-1">
                                    @foreach ($savedReview->data_contradictions as $c)
                                        <li>{{ $c }}</li>
                                    @endforeach
                                </ul>
                                <small>Administrative verification recommended.</small>
                            </div>
                        @endif

                        <div class="mb-3">
                            <span class="badge bg-{{ $statusLabel[1] }} fs-6">
                                {{ $statusLabel[0] }}
                            </span>
                            <small class="text-muted ms-2">
                                Generated {{ $savedReview->ai_generated_at?->format('M d, Y h:i A') }}
                                @if ($savedReview->ai_model)
                                    · Model: {{ $savedReview->ai_model }}
                                @endif
                            </small>
                        </div>

                        @if ($savedReview->behavior_trend)
                            <h6 class="text-muted">Behavior Trend</h6>
                            <p>
                                <span class="badge bg-secondary">{{ ucwords(str_replace('_', ' ', $savedReview->behavior_trend)) }}</span>
                                @if ($savedReview->trend_explanation)
                                    <span class="d-block mt-1">{{ $savedReview->trend_explanation }}</span>
                                @endif
                            </p>
                        @endif

                        <h6 class="text-muted">Summary</h6>
                        <p>{{ $savedReview->summary ?: '—' }}</p>

                        @if (! empty($savedReview->outstanding_sanctions))
                            <h6 class="text-muted">Outstanding Sanctions</h6>
                            <ul class="mb-3">
                                @foreach ($savedReview->outstanding_sanctions as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($savedReview->disciplinary_pattern)
                            <h6 class="text-muted">Disciplinary Pattern</h6>
                            <p>{{ $savedReview->disciplinary_pattern }}</p>
                        @endif

                        @if (! empty($savedReview->supporting_evidence))
                            <h6 class="text-muted">Supporting Evidence</h6>
                            <ul class="mb-3">
                                @foreach ($savedReview->supporting_evidence as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($savedReview->reason_for_review)
                            <h6 class="text-muted">Reason for Review</h6>
                            <p>{{ $savedReview->reason_for_review }}</p>
                        @endif

                        @if ($savedReview->recommended_action)
                            <h6 class="text-muted">Recommended Action</h6>
                            <p class="mb-0">{{ $savedReview->recommended_action }}</p>
                        @endif

                        <hr>

                        <div class="alert alert-light border mb-0">
                            <small>
                                <i class="bi bi-info-circle"></i>
                                The final graduation clearance / Good Moral decision is made by the Discipline Office, not the AI.
                            </small>
                        </div>

                    @else
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-stars fs-1 d-block mb-2"></i>
                            <p class="mb-1">No AI review has been generated yet.</p>
                            <p class="mb-0">Click <strong>Generate AI Review</strong> to create an AI-assisted summary.</p>
                        </div>
                    @endif

                </div>
            </div>
        </div>

    </div>

    {{-- Final Decision (human-in-the-loop) --}}
    @if ($savedReview)
        <div class="card shadow-sm border-0 mt-4">
            <div class="card-header bg-white">
                <i class="bi bi-clipboard-check text-primary"></i>
                <strong>Administrator Decision</strong>
                <span class="badge bg-secondary ms-1">Made by Discipline Office — AI is advisory only</span>
            </div>
            <div class="card-body">
                @if ($savedReview->decision_status)
                    <div class="alert alert-light border mb-3">
                        <strong>Recorded decision:</strong>
                        {{ ucfirst($savedReview->decision_status) }}
                        @if ($savedReview->decision_remarks)
                            <br><em>{{ $savedReview->decision_remarks }}</em>
                        @endif
                        <br>
                        <small class="text-muted">
                            by {{ $savedReview->decidedBy?->email ?? '—' }}
                            · {{ optional($savedReview->decision_at)->format('M d, Y h:i A') }}
                        </small>
                    </div>
                @endif

                <form action="{{ route('admin.graduation-review.decision', $student->student_number) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">AI Recommendation</label>
                        <div>
                            <span class="badge bg-{{ $statusLabel[1] }}">{{ $statusLabel[0] }}</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Your Decision</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="decision_status" id="decisionAgree" value="agreed" @checked($savedReview->decision_status === 'agreed') required>
                            <label class="form-check-label" for="decisionAgree">Agree with AI</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="decision_status" id="decisionModify" value="modified" @checked($savedReview->decision_status === 'modified')>
                            <label class="form-check-label" for="decisionModify">Modify Recommendation</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="decision_status" id="decisionReject" value="rejected" @checked($savedReview->decision_status === 'rejected')>
                            <label class="form-check-label" for="decisionReject">Reject Recommendation</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="decisionRemarks">Administrator Remarks</label>
                        <textarea id="decisionRemarks" name="decision_remarks" class="form-control" rows="3" placeholder="Final decision and remarks...">{{ $savedReview->decision_remarks }}</textarea>
                    </div>

                    <button type="submit" class="admin-btn admin-btn-primary">
                        <i class="bi bi-check2-circle"></i> Record Decision
                    </button>
                </form>
            </div>
        </div>
    @endif

    {{-- Supporting Records (transparency) --}}
    <div class="card shadow-sm border-0 mt-4">
        <div class="card-header bg-white">
            <i class="bi bi-journal-text text-primary"></i>
            <strong>Supporting Disciplinary Records</strong>
            <span class="text-muted ms-2">Actual records used for this review</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Violation</th>
                        <th>Date</th>
                        <th>Sanction</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($screening['violations'] as $violation)
                        @php
                            $records = collect($screening['sanction_records'])
                                ->where('violation_ref', $violation['ref'])
                                ->values();
                        @endphp
                        @if ($records->isEmpty())
                            <tr>
                                <td>{{ $violation['type'] }}</td>
                                <td>{{ $violation['date'] ?? '—' }}</td>
                                <td colspan="2" class="text-muted">No sanction record</td>
                            </tr>
                        @else
                            @foreach ($records as $index => $record)
                                <tr>
                                    @if ($index === 0)
                                        <td rowspan="{{ $records->count() }}">
                                            {{ $violation['type'] }}
                                            @if ($violation['date'])
                                                <br><small class="text-muted">{{ $violation['date'] }}</small>
                                            @endif
                                        </td>
                                        <td rowspan="{{ $records->count() }}">{{ $violation['date'] ?? '—' }}</td>
                                    @endif
                                    <td>{{ $record['sanction'] }}</td>
                                    <td>
                                        @if (strtolower((string) $record['status']) === 'completed')
                                            <x-admin.badge status="completed" />
                                        @else
                                            <x-admin.badge status="pending" :label="$record['status']" />
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    @empty
                        <x-admin.empty-state
                            :colspan="4"
                            icon="bi-journal-text"
                            title="No disciplinary records"
                            message="No disciplinary records are on file for this student."
                            :clearUrl="null" />
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
        // Prevent duplicate AI review requests + show loading state.
        const aiForm = document.getElementById('aiGenerateForm');
        const aiBtn = document.getElementById('aiGenerateBtn');
        if (aiForm && aiBtn) {
            aiForm.addEventListener('submit', function (event) {
                if (aiBtn.disabled) {
                    event.preventDefault();
                    return;
                }
                window.PUPLoading && window.PUPLoading.start('aiReviewLoading');
                aiBtn.disabled = true;
            });
        }
    });
</script>
@endpush
