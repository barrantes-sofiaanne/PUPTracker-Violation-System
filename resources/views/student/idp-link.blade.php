@extends('layouts.auth-portal', ['title' => 'Complete Your PUPTracker Registration', 'portal' => 'student'])

@section('auth-content')

        <p class="module-chip">Student Module</p>

        <h2>Complete Your PUPTracker Registration</h2>

        <p class="subtitle">
            Welcome to PUPTracker! Your university account has been successfully authenticated.
            To complete your PUPTracker registration, please provide the required student information below.
        </p>

        @if (session('info'))
            <div class="alert alert-info">{{ session('info') }}</div>
        @endif

        @if($errors->has('login'))
            <div class="login-error">
                <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
                <span>{{ $errors->first('login') }}</span>
            </div>
        @endif

        @if($errors->has('registration'))
            <div class="login-error">
                <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
                <span>{{ $errors->first('registration') }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('student.idp.link.post') }}" autocomplete="off"
              class="auth-login-form" id="registrationForm">
            @csrf

            {{--
                SECTION A — IDENTITY FROM THE UNIVERSITY ACCOUNT.

                These values are rendered for DISPLAY ONLY. The server never reads
                them back: name and email are taken from the verified IDP session
                when the account is created. `disabled` inputs are not submitted by
                the browser at all, which makes casual tampering a non-issue; the
                controller additionally ignores any such keys.
            --}}
            <section class="registration-section" aria-labelledby="idpSectionHeading">
                <h3 class="registration-section-title" id="idpSectionHeading">
                    <i class="bi bi-buildings-fill" aria-hidden="true"></i>
                    Information from your university account
                </h3>

                <p class="registration-section-help" id="idpSectionHelp">
                    These details were provided by your university account and cannot be edited in PUPTracker.
                </p>

                <div class="mb-3">
                    <label class="form-label" for="verified_name">Full Name</label>
                    <div class="input-group login-input-group">
                        <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                        <input
                            id="verified_name"
                            type="text"
                            class="form-control locked-field"
                            value="{{ $verifiedName ?: '—' }}"
                            aria-describedby="idpSectionHelp"
                            disabled
                            readonly>
                        <span class="input-group-text locked-field-icon" title="Provided by PUP IDP" aria-hidden="true">
                            <i class="bi bi-lock-fill"></i>
                        </span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="verified_email">Email Address</label>
                    <div class="input-group login-input-group">
                        <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
                        <input
                            id="verified_email"
                            type="text"
                            class="form-control locked-field"
                            value="{{ $verifiedEmail ?: '—' }}"
                            aria-describedby="idpSectionHelp"
                            disabled
                            readonly>
                        <span class="input-group-text locked-field-icon" title="Provided by PUP IDP" aria-hidden="true">
                            <i class="bi bi-lock-fill"></i>
                        </span>
                    </div>
                </div>

                <p class="verified-identity-badge">
                    <i class="bi bi-patch-check-fill" aria-hidden="true"></i>
                    Verified by PUP Identity Provider
                </p>
            </section>

            {{--
                SECTION B — INFORMATION THE STUDENT SUPPLIES.

                PUPTracker has no pre-existing student-number roster, so the student
                number is COLLECTED here (not looked up). Program and Year come from
                the real dropdown tables.
            --}}
            <section class="registration-section" aria-labelledby="pupSectionHeading">
                <h3 class="registration-section-title" id="pupSectionHeading">
                    <i class="bi bi-clipboard-data-fill" aria-hidden="true"></i>
                    PUPTracker information
                </h3>

                <div class="mb-3">
                    <label class="form-label" for="student_number">
                        Student Number <span class="required-asterisk" aria-hidden="true">*</span>
                    </label>
                    <div class="input-group login-input-group">
                        <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                        <input
                            id="student_number"
                            type="text"
                            name="student_number"
                            class="form-control @error('student_number') is-invalid @enderror"
                            value="{{ old('student_number') }}"
                            placeholder="e.g. 2026-00001"
                            autocomplete="off"
                            aria-describedby="studentNumberHelp"
                            required>
                    </div>
                    <small class="form-text text-muted" id="studentNumberHelp">
                        Enter your official student number. It is recorded on your new PUPTracker account.
                    </small>
                    @error('student_number')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label" for="program_id">
                        Program/Course <span class="required-asterisk" aria-hidden="true">*</span>
                    </label>
                    @php
                        // The Ladderized question is offered ONLY for the programs that
                        // actually run a ladderized curriculum (BSIT / BSOA). The list is
                        // computed server-side from config so this page and the Admin
                        // student screens can never disagree. Program ids travel with the
                        // select, so the script depends on no hard-coded ids.
                        $ladderizedProgramIds = (array) ($ladderizedProgramIds ?? []);
                        $selectedProgramId = (string) old('program_id');
                        $ladderizedOffered = $selectedProgramId !== ''
                            && in_array((int) $selectedProgramId, array_map('intval', $ladderizedProgramIds), true);
                    @endphp
                  <select
    id="program_id"
    name="program_id"
    class="form-select @error('program_id') is-invalid @enderror"
    data-ladderized-programs='@json(array_values($ladderizedProgramIds))'
    required>
                        <option value="">Select Program/Course</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->program_id }}"
                                @selected((string) old('program_id') === (string) $program->program_id)>
                                {{ $program->program_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('program_id')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                {{--
                    LADDERIZED — shown only when the selected Program/Course is BSIT
                    or BSOA (strictly those two). The script reveals and hides it on
                    program change; it starts revealed after a validation error so a
                    repopulated form does not silently lose the student's answer.
                    The server independently re-checks the program, so hiding this
                    field can never set the flag on a non-ladderized program.
                --}}
                <div class="mb-3 {{ $ladderizedOffered ? '' : 'd-none' }}" id="ladderizedWrap">
                    <label class="form-label d-block">
                        Ladderized
                    </label>
                    <div class="form-check form-check-inline">
<input
    class="form-check-input @error('ladderized') is-invalid @enderror"
    type="radio"
    name="ladderized"
    id="ladderizedYes"
    value="1"
    @checked((string) old('ladderized') === '1')>
<label class="form-check-label" for="ladderizedYes">Yes</label>
                    </div>
                    <div class="form-check form-check-inline">
<input
    class="form-check-input @error('ladderized') is-invalid @enderror"
    type="radio"
    name="ladderized"
    id="ladderizedNo"
    value="0"
    @checked((string) old('ladderized') === '0')>
<label class="form-check-label" for="ladderizedNo">No</label>
                    </div>
                    <small class="form-text text-muted d-block">
                        Choose <strong>Yes</strong> if you are enrolled under the ladderized
                        curriculum of this program.
                    </small>
                    @error('ladderized')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label" for="year_id">
                        Year Level <span class="required-asterisk" aria-hidden="true">*</span>
                    </label>
                    <select
                        id="year_id"
                        name="year_id"
                        class="form-select @error('year_id') is-invalid @enderror"
                        required>
                        <option value="">Select Year Level</option>
                        @foreach ($years as $year)
                            <option value="{{ $year->year_id }}"
                                @selected((string) old('year_id') === (string) $year->year_id)>
                                {{ $year->year }}
                            </option>
                        @endforeach
                    </select>
                    @error('year_id')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label" for="section_id">
                        Section <span class="required-asterisk" aria-hidden="true">*</span>
                    </label>
                    <select
                        id="section_id"
                        name="section_id"
                        class="form-select @error('section_id') is-invalid @enderror"
                        required>
                        <option value="">Select Section</option>
                        @foreach ($sections as $section)
                            <option value="{{ $section->section_id }}"
                                @selected((string) old('section_id') === (string) $section->section_id)>
                                {{ $section->section_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('section_id')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label" for="gender_id">
                        Gender <span class="required-asterisk" aria-hidden="true">*</span>
                    </label>
                    <select
                        id="gender_id"
                        name="gender_id"
                        class="form-select @error('gender_id') is-invalid @enderror"
                        required>
                        <option value="">Select Gender</option>
                        @foreach ($genders as $gender)
                            <option value="{{ $gender->gender_id }}"
                                @selected((string) old('gender_id') === (string) $gender->gender_id)>
                                {{ $gender->gender_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('gender_id')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </section>

            {{--
                INFORMATION PRIVACY NOTICE.

                Displayed BEFORE submission so the student reads it before any data
                is sent. The acknowledgement is worded as a notice acknowledgement
                ("I have read and understood"), not as affirmative consent.
            --}}
            <div class="privacy-notice" role="note" aria-labelledby="privacyNoticeHeading">
                <h3 class="privacy-notice-title" id="privacyNoticeHeading">
                    <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
                    Information Privacy Notice
                </h3>

                <p>
                    The information you provide will be collected and processed solely for purposes related to
                    the operation of the PUPTracker system, including student identification, violation and
                    sanction management, record keeping, and other authorized student-related processes.
                </p>

                <p class="mb-0">
                    Only information necessary for these purposes will be collected. Your information will be
                    handled in accordance with applicable institutional policies and data privacy requirements.
                    By proceeding with registration, you acknowledge that you have read and understood this notice.
                </p>
            </div>

            <div class="form-check registration-acknowledgement">
                <input
                    class="form-check-input @error('privacy_acknowledged') is-invalid @enderror"
                    type="checkbox"
                    id="privacy_acknowledged"
                    name="privacy_acknowledged"
                    value="1"
                    @checked(old('privacy_acknowledged'))
                    required>
                <label class="form-check-label" for="privacy_acknowledged">
                    I have read and understood the Information Privacy Notice.
                    <span class="required-asterisk" aria-hidden="true">*</span>
                </label>
                @error('privacy_acknowledged')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-success w-100 login-btn" id="completeRegistrationBtn">
                <span class="btn-label">Complete Registration</span>
                <span class="btn-spinner spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
            </button>
        </form>

        <div class="footer-links">
            <a href="{{ route('student.login') }}">&larr; Back to Login</a>
        </div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('registrationForm');
    const btn = document.getElementById('completeRegistrationBtn');
    if (!form || !btn) {
        return;
    }

    // Loading state while the registration submits. The form posts normally
    // (no AJAX), so the button is disabled + a spinner shown to prevent a
    // double-submit; the server also re-checks for duplicates.
    form.addEventListener('submit', function () {
        const label = btn.querySelector('.btn-label');
        const spinner = btn.querySelector('.btn-spinner');

        btn.disabled = true;
        if (label) { label.textContent = 'Submitting…'; }
        if (spinner) { spinner.classList.remove('d-none'); }
    });
});

// LADDERIZED visibility — the question appears ONLY for BSIT / BSOA.
//
// The ladderizable program ids are rendered onto the select by the server, so
// this never hard-codes an id and automatically follows any change to
// GRADUATION_REVIEW_LADDERIZED_PROGRAMS. The server re-checks the program
// before storing the flag, so this is presentation only.

document.addEventListener('DOMContentLoaded', function () {
    const programSelect = document.getElementById('program_id');
    const wrap = document.getElementById('ladderizedWrap');

    if (!programSelect || !wrap) {
        return;
    }

    let ladderizedIds = [];

    try {
        ladderizedIds = JSON.parse(
            programSelect.dataset.ladderizedPrograms || '[]'
        ).map(Number);
    } catch (e) {
        ladderizedIds = [];
    }

    function syncLadderizedVisibility() {
        const offered = ladderizedIds.includes(
            Number(programSelect.value)
        );

        wrap.classList.toggle('d-none', !offered);

        const yes = document.getElementById('ladderizedYes');
        const no = document.getElementById('ladderizedNo');

        [yes, no].forEach(function (radio) {
            if (!radio) return;

            if (offered) {
                radio.setAttribute('required', 'required');
            } else {
                radio.removeAttribute('required');
                radio.checked = false;
            }
        });
    }

    programSelect.addEventListener(
        'change',
        syncLadderizedVisibility
    );

    syncLadderizedVisibility();
});

</script>
@endpush
