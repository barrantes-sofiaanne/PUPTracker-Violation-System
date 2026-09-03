@extends('layouts.auth-portal', ['title' => 'Verify Your Information', 'portal' => 'student'])

@section('auth-content')

        <p class="module-chip">Student Module</p>

        @php
            $localProgram = optional($student->studentInfo?->program)->program_name ?? '';
            $localYear = optional($student->studentInfo?->year)->year ?? '';

            // Resolve effective program/year: prefer the student's pending choice
            // (from an Apply step) else the local record value.
            $effectiveProgramId = $pendingProgramId ?? $student->program_id;
            $effectiveYearId = $pendingYearId ?? $student->year_id;
            $effectiveProgram = $effectiveProgramId
                ? optional($programs->firstWhere('program_id', (int) $effectiveProgramId))->program_name
                    ?? $localProgram
                : $localProgram;
            $effectiveYear = $effectiveYearId
                ? optional($years->firstWhere('year_id', (int) $effectiveYearId))->year
                    ?? $localYear
                : $localYear;

            $hasPendingChoices = $pendingProgramId !== null || $pendingYearId !== null;
            $reviewMode = $hasPendingChoices;
        @endphp

        @if ($reviewMode)
            <h2>Review Your Information</h2>
        @else
            <h2>Verify Your Student Information</h2>
        @endif

        <p class="subtitle">
            @if ($reviewMode)
                Confirm the details below, then click <strong>Submit</strong> to connect your PUP ID to this PUPTracker account. Your name, suffix, and email come from PUP ID and will be saved to your student record.
            @else
                We found the following student record. Your identity information comes from your authenticated PUP ID; your student number, program, and year come from your existing PUPTracker record.
            @endif
        </p>

        @if($errors->has('login'))
            <div class="login-error">
                <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
                <span>{{ $errors->first('login') }}</span>
            </div>
        @endif

        {{-- Identity information (always read-only, from IDP — authoritative) --}}
        <div class="mb-2">
            <div class="fw-bold text-muted small text-uppercase mb-1">Identity Information</div>
            <div class="border rounded p-3 bg-light">
                <div class="row g-2">
                    <div class="col-12 d-flex justify-content-between align-items-center">
                        <span class="text-muted">First Name</span>
                        <span class="fw-semibold">{{ $profile['first_name'] ?? '—' }} <i class="bi bi-lock-fill text-muted small" title="Read-only"></i></span>
                    </div>
                    <div class="col-12 d-flex justify-content-between align-items-center">
                        <span class="text-muted">Middle Name</span>
                        <span class="fw-semibold">{{ $profile['middle_name'] ?: '—' }} <i class="bi bi-lock-fill text-muted small" title="Read-only"></i></span>
                    </div>
                    <div class="col-12 d-flex justify-content-between align-items-center">
                        <span class="text-muted">Last Name</span>
                        <span class="fw-semibold">{{ $profile['last_name'] ?? '—' }} <i class="bi bi-lock-fill text-muted small" title="Read-only"></i></span>
                    </div>
                    <div class="col-12 d-flex justify-content-between align-items-center">
                        <span class="text-muted">Suffix</span>
                        <span class="fw-semibold">{{ trim((string) ($profile['suffix'] ?? '')) ?: '—' }} <i class="bi bi-lock-fill text-muted small" title="Read-only"></i></span>
                    </div>
                    <div class="col-12 d-flex justify-content-between align-items-center">
                        <span class="text-muted">Email</span>
                        <span class="fw-semibold">{{ $profile['email'] ?? '—' }} <i class="bi bi-lock-fill text-muted small" title="Read-only"></i></span>
                    </div>
                </div>
            </div>
            <div class="small text-muted mt-1">
                <i class="bi bi-info-circle me-1"></i>Identity information is provided by PUP ID and cannot be edited here.
            </div>
        </div>

        {{-- Academic information (from local record; program/year may be corrected) --}}
        <div class="mb-3">
            <div class="fw-bold text-muted small text-uppercase mb-1">Academic Information</div>
            <div class="border rounded p-3 bg-light">
                <div class="row g-2">
                    <div class="col-12 d-flex justify-content-between align-items-center">
                        <span class="text-muted">Student Number</span>
                        <span class="fw-semibold">{{ $student->student_number }} <i class="bi bi-lock-fill text-muted small" title="Read-only"></i></span>
                    </div>

                    <div class="col-12 d-flex justify-content-between align-items-center">
                        <span class="text-muted">Program</span>
                        @if ($reviewMode)
                            <span class="fw-semibold">{{ $effectiveProgram ?: '—' }}</span>
                        @else
                            <span class="fw-semibold">{{ $effectiveProgram ?: '—' }} <i class="bi bi-lock-fill text-muted small" title="Locked — click Update Information to change"></i></span>
                        @endif
                    </div>

                    <div class="col-12 d-flex justify-content-between align-items-center">
                        <span class="text-muted">Year</span>
                        @if ($reviewMode)
                            <span class="fw-semibold">{{ $effectiveYear ?: '—' }}</span>
                        @else
                            <span class="fw-semibold">{{ $effectiveYear ?: '—' }} <i class="bi bi-lock-fill text-muted small" title="Locked — click Update Information to change"></i></span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- If local program/year are missing, surface a gentle hint. --}}
        @if (! $reviewMode && ($localProgram === '' || $localYear === ''))
            <div class="alert alert-warning small py-2">
                <i class="bi bi-pencil-square me-1"></i>
                Some academic details are missing from your record. You can update Program and Year below if needed.
            </div>
        @endif

        @if ($reviewMode)
            {{-- Final review: [Edit] [Submit] --}}
            <button type="button" class="btn btn-outline-secondary w-100 mb-2" id="editInfoBtn">
                <i class="bi bi-pencil me-1"></i>Edit
            </button>

            <form method="POST" action="{{ route('student.idp.link.submit') }}" class="mt-2" id="submitLinkForm">
                @csrf
                <button type="submit" class="btn btn-success w-100 login-btn">
                    <i class="bi bi-link-45deg me-1"></i>Submit
                </button>
            </form>

            <p class="security-note mt-2">
                <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
                Submitting establishes the permanent connection between your PUP ID and this PUPTracker student record.
            </p>
        @else
            {{-- Preview state: [Update Information] [Submit] --}}
            <form method="POST" action="{{ route('student.idp.link.submit') }}" class="d-grid gap-2" id="submitLinkForm">
                @csrf
                <button type="submit" class="btn btn-success w-100 login-btn">
                    <i class="bi bi-link-45deg me-1"></i>Submit
                </button>
            </form>

            <button type="button" class="btn btn-outline-secondary w-100 mt-2" id="editInfoBtn">
                <i class="bi bi-pencil me-1"></i>Update Information
            </button>

            <p class="security-note mt-2">
                <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
                Only Program and Year can be updated. Your name, email, and student number stay locked.
            </p>
        @endif

        <div class="footer-links">
            <a href="{{ route('student.idp.link') }}">&larr; Back</a>
        </div>

        {{-- Program / Year correction modal --}}
        <div class="modal fade" id="updateInfoModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('student.idp.link.program-year') }}">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Update Information</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small">Only Program and Year may be corrected. Everything else stays locked.</p>
                            <div class="mb-3">
                                <label class="form-label" for="program_id">Program</label>
                                <select name="program_id" id="program_id" class="form-select">
                                    <option value="">Select Program</option>
                                    @foreach ($programs as $program)
                                        <option value="{{ $program->program_id }}" @selected((string) $effectiveProgramId === (string) $program->program_id)>
                                            {{ $program->program_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label" for="year_id">Year</label>
                                <select name="year_id" id="year_id" class="form-select">
                                    <option value="">Select Year</option>
                                    @foreach ($years as $yearOption)
                                        <option value="{{ $yearOption->year_id }}" @selected((string) $effectiveYearId === (string) $yearOption->year_id)>
                                            {{ $yearOption->year }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn portal-btn">
                                <i class="bi bi-check-circle me-1"></i>Apply
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const editBtn = document.getElementById('editInfoBtn');
    const modalEl = document.getElementById('updateInfoModal');
    if (!editBtn || !modalEl) {
        return;
    }
    const modal = new bootstrap.Modal(modalEl);

    editBtn.addEventListener('click', function () {
        modal.show();
    });
});
</script>
@endpush
