@extends('layouts.auth-portal', [
    'title' => 'PUPTracker Student Account Setup',
    'portal' => 'student',
    'brandKey' => 'signup',
    'wide' => true,
])

@section('ap-content')
<div class="ap-wizard" style="max-width: 820px; margin: 0 auto;">
    <div style="margin-bottom: 1.5rem;">
        <h2 class="ap-heading ap-heading--left">PUPTracker Student Account Setup</h2>
        <p class="ap-subtitle ap-subtitle--left">
            Your PUP IDP identity has been verified. We will check whether a student record already exists before creating a new account.
        </p>
    </div>

    @if (session('info'))
        <div class="ap-alert ap-alert--info" role="status"><i class="bi bi-info-circle-fill" aria-hidden="true"></i> {{ session('info') }}</div>
    @endif
    @if ($errors->any())
        <div class="ap-alert" role="alert">
            <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
            <div><strong>Please review the information below.</strong><ul style="margin:.35rem 0 0;padding-left:1.25rem;">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        </div>
    @endif

    <div class="ap-card" style="padding:1.25rem; margin-bottom:1.25rem; border:1px solid rgba(128,0,0,.16); border-radius:16px;">
        <div style="display:flex;align-items:center;gap:.65rem;margin-bottom:.8rem;">
            <i class="bi bi-patch-check-fill" style="font-size:1.25rem;color:#198754;" aria-hidden="true"></i>
            <strong>Verified PUP IDP identity</strong>
        </div>
        <div class="ap-grid-2">
            <div><span class="ap-field-help">Full name</span><div class="ap-field ap-field--locked"><input type="text" value="{{ $verifiedName ?: 'Not provided' }}" readonly disabled></div></div>
            <div><span class="ap-field-help">University email</span><div class="ap-field ap-field--locked"><input type="text" value="{{ $verifiedEmail ?: 'Not provided' }}" readonly disabled></div></div>
        </div>
        <p class="ap-field-help" style="margin:.65rem 0 0;">These identity details come directly from PUP IDP and cannot be edited in this form.</p>
    </div>


    @if ($matchMode === 'single' && $matchedStudent)
        <div class="ap-alert ap-alert--info" role="note" style="margin-bottom:1rem;">
            <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
            <span><strong>Existing student record found.</strong> Review the matched academic details below. You may correct the student number, program/course, year level, or section before linking. Your PUP IDP-verified name and email remain unchanged.</span>
        </div>
        <div class="ap-card" style="padding:1.25rem;border:1px solid rgba(128,0,0,.16);border-radius:16px;margin-bottom:1.25rem;">
            <h3 class="ap-step-title">Your matched student record</h3>
            <form method="POST" action="{{ route('student.idp.link.post') }}" class="ap-form">
                @csrf
                <input type="hidden" name="match_mode" value="single">
                <div class="ap-grid-2">
                    <div class="ap-field-group">
                        <label class="ap-label" for="matched_student_number">Student number <span class="ap-req">*</span></label>
                        <div class="ap-field"><input id="matched_student_number" name="student_number" value="{{ old('student_number', $matchedStudent->student_number) }}" required maxlength="50" autocomplete="off"></div>
                    </div>
                    <div class="ap-field-group">
                        <label class="ap-label" for="matched_program_id">Program/Course <span class="ap-req">*</span></label>
                        <div class="ap-field ap-field--select"><select id="matched_program_id" name="program_id" required><option value="">Select program</option>@foreach($programs as $program)<option value="{{ $program->program_id }}" @selected((string)old('program_id', optional($matchedStudent->studentInfo)->program_id) === (string)$program->program_id)>{{ $program->program_name }}</option>@endforeach</select></div>
                    </div>
                    <div class="ap-field-group">
                        <label class="ap-label" for="matched_year_id">Year level <span class="ap-req">*</span></label>
                        <div class="ap-field ap-field--select"><select id="matched_year_id" name="year_id" required><option value="">Select year</option>@foreach($years as $year)<option value="{{ $year->year_id }}" @selected((string)old('year_id', optional($matchedStudent->studentInfo)->year_id) === (string)$year->year_id)>{{ $year->year }}</option>@endforeach</select></div>
                    </div>
                    <div class="ap-field-group">
                        <label class="ap-label" for="matched_section_id">Section <span class="ap-req">*</span></label>
                        <div class="ap-field ap-field--select"><select id="matched_section_id" name="section_id" required><option value="">Select section</option>@foreach($sections as $section)<option value="{{ $section->section_id }}" @selected((string)old('section_id', optional($matchedStudent->studentInfo)->section_id) === (string)$section->section_id)>{{ $section->section_name }}</option>@endforeach</select></div>
                    </div>
                </div>
                <p class="ap-field-help">Please make sure these details are accurate. The corrected academic information will be saved to your matched student record when you confirm and link your account.</p>
                <div class="ap-field-group" style="margin-bottom:1rem;">
                    <label style="display:flex;align-items:flex-start;gap:.65rem;">
                        <input type="checkbox" name="privacy_acknowledged" value="1" required style="margin-top:.25rem;" @checked(old('privacy_acknowledged'))>
                        <span>I acknowledge that PUPTracker may collect, use, store, and process my personal information and verified PUP IDP details to verify my identity, manage my student account and disciplinary records, send system notifications, and protect system security, in accordance with university privacy policies and the Data Privacy Act of 2012.</span>
                    </label>
                </div>
                <button type="submit" class="ap-btn ap-btn--compact"><span>Save Details and Link My Account</span><i class="bi bi-link-45deg" aria-hidden="true"></i></button>
            </form>
        </div>

    @elseif ($matchMode === 'duplicate')
        <div class="ap-alert ap-alert--info" role="status" style="margin-bottom:1rem;">
            <i class="bi bi-people-fill" aria-hidden="true"></i>
            <span>We found {{ $matchCount }} student records with the same first, middle, and last name. To identify your record, provide your student number, program, and section. Your selected information will be checked against the existing records.</span>
        </div>
        <form method="POST" action="{{ route('student.idp.link.post') }}" class="ap-form">
            @csrf
            <input type="hidden" name="match_mode" value="duplicate">
            <div class="ap-grid-2">
                <div class="ap-field-group"><label class="ap-label" for="student_number">Student number <span class="ap-req">*</span></label><div class="ap-field"><input id="student_number" name="student_number" value="{{ old('student_number') }}" required maxlength="50" autocomplete="off"></div></div>
                <div class="ap-field-group"><label class="ap-label" for="program_id">Program/Course <span class="ap-req">*</span></label><div class="ap-field ap-field--select"><select id="program_id" name="program_id" required><option value="">Select program</option>@foreach($programs as $program)<option value="{{ $program->program_id }}" @selected((string)old('program_id') === (string)$program->program_id)>{{ $program->program_name }}</option>@endforeach</select></div></div>
                <div class="ap-field-group"><label class="ap-label" for="section_id">Section <span class="ap-req">*</span></label><div class="ap-field ap-field--select"><select id="section_id" name="section_id" required><option value="">Select section</option>@foreach($sections as $section)<option value="{{ $section->section_id }}" @selected((string)old('section_id') === (string)$section->section_id)>{{ $section->section_name }}</option>@endforeach</select></div></div>
            </div>
            <div class="ap-field-group"><label style="display:flex;gap:.65rem;align-items:flex-start;"><input type="checkbox" name="confirm_record" value="1" required style="margin-top:.25rem;"><span>I confirm that the student number, program, and section I entered identify my own student record.</span></label></div>
            <div class="ap-field-group"><label style="display:flex;gap:.65rem;align-items:flex-start;"><input type="checkbox" name="privacy_acknowledged" value="1" required style="margin-top:.25rem;"><span>I acknowledge that PUPTracker may collect, use, store, and process my personal information and verified PUP IDP details to verify my identity, manage my student account and disciplinary records, send system notifications, and protect system security, in accordance with university privacy policies and the Data Privacy Act of 2012.</span></label></div>
            <button type="submit" class="ap-btn ap-btn--compact"><span>Verify and Link Existing Record</span><i class="bi bi-link-45deg" aria-hidden="true"></i></button>
        </form>

    @else
        @php
            // The server is the authority on which programs support the
            // ladderized track. The ladderized question is only revealed when
            // the (re-populated) selected program is eligible.
            $selectedProgramId = old('program_id') !== null ? (int) old('program_id') : null;
            $ladderizedAvailable = $selectedProgramId !== null
                && in_array($selectedProgramId, $ladderizedProgramIds, true);
        @endphp
        <div class="ap-alert ap-alert--info" role="status" style="margin-bottom:1rem;">
            <i class="bi bi-person-plus-fill" aria-hidden="true"></i>
            <span>No existing student record matched your verified full name. Complete the registration details below. Your name and email will be taken from PUP IDP.</span>
        </div>
        <form method="POST" action="{{ route('student.idp.link.post') }}" class="ap-form">
            @csrf
            <input type="hidden" name="match_mode" value="new">
            <div class="ap-grid-2">
                <div class="ap-field-group"><label class="ap-label" for="student_number">Student number <span class="ap-req">*</span></label><div class="ap-field"><input id="student_number" name="student_number" value="{{ old('student_number') }}" required maxlength="50" autocomplete="off"></div></div>
                <div class="ap-field-group"><label class="ap-label" for="gender_id">Gender <span class="ap-req">*</span></label><div class="ap-field ap-field--select"><select id="gender_id" name="gender_id" required><option value="">Select gender</option>@foreach($genders as $gender)<option value="{{ $gender->gender_id }}" @selected((string)old('gender_id') === (string)$gender->gender_id)>{{ $gender->gender_name }}</option>@endforeach</select></div></div>
                <div class="ap-field-group"><label class="ap-label" for="program_id">Program/Course <span class="ap-req">*</span></label><div class="ap-field ap-field--select"><select id="program_id" name="program_id" required data-ladderized-programs='@json($ladderizedProgramIds)' onchange="syncLadderizedVisibility()"><option value="">Select program</option>@foreach($programs as $program)<option value="{{ $program->program_id }}" @selected((string)old('program_id') === (string)$program->program_id)>{{ $program->program_name }}</option>@endforeach</select></div></div>
                <div class="ap-field-group"><label class="ap-label" for="year_id">Year level <span class="ap-req">*</span></label><div class="ap-field ap-field--select"><select id="year_id" name="year_id" required><option value="">Select year</option>@foreach($years as $year)<option value="{{ $year->year_id }}" @selected((string)old('year_id') === (string)$year->year_id)>{{ $year->year }}</option>@endforeach</select></div></div>
                <div class="ap-field-group"><label class="ap-label" for="section_id">Section <span class="ap-req">*</span></label><div class="ap-field ap-field--select"><select id="section_id" name="section_id" required><option value="">Select section</option>@foreach($sections as $section)<option value="{{ $section->section_id }}" @selected((string)old('section_id') === (string)$section->section_id)>{{ $section->section_name }}</option>@endforeach</select></div></div>
            </div>
            <div class="ap-field-group {{ $ladderizedAvailable ? '' : 'd-none' }}" id="ladderizedWrap">
                <label class="ap-label d-block">Ladderized</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="ladderized" id="ladderizedYes" value="1" @checked(old('ladderized') === '1')>
                    <label class="form-check-label" for="ladderizedYes">Yes</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="ladderized" id="ladderizedNo" value="0" @checked(old('ladderized') !== '1')>
                    <label class="form-check-label" for="ladderizedNo">No</label>
                </div>
            </div>
            <div class="ap-field-group"><label style="display:flex;gap:.65rem;align-items:flex-start;"><input type="checkbox" name="privacy_acknowledged" value="1" required style="margin-top:.25rem;"><span>I acknowledge that PUPTracker may collect, use, store, and process my personal information and verified PUP IDP details to verify my identity, manage my student account and disciplinary records, send system notifications, and protect system security, in accordance with university privacy policies and the Data Privacy Act of 2012.</span></label></div>
            <button type="submit" class="ap-btn ap-btn--compact"><span>Create Student Account and Continue</span><i class="bi bi-check2-circle" aria-hidden="true"></i></button>
        </form>
    @endif

    <a href="{{ route('student.login') }}" class="ap-back" style="display:inline-flex;margin-top:1.25rem;">
        <i class="bi bi-arrow-left" aria-hidden="true"></i><span>Back to Student Login</span>
    </a>
</div>
@endsection

@push('scripts')
<script>
    // Reveal the Ladderized question only when the selected program supports it.
    // Eligibility is supplied by the server via data-ladderized-programs; the
    // browser never decides which programs qualify.
    function syncLadderizedVisibility() {
        const select = document.getElementById('program_id');
        const wrap = document.getElementById('ladderizedWrap');
        if (!select || !wrap) {
            return;
        }

        const selectedOption = select.options[select.selectedIndex];
        const selectedId = selectedOption ? parseInt(selectedOption.value, 10) : null;

        let eligible = [];
        try {
            eligible = (JSON.parse(select.dataset.ladderizedPrograms || '[]'))
                .map(function (id) { return parseInt(id, 10); });
        } catch (e) {
            eligible = [];
        }

        const available = selectedId !== null && eligible.indexOf(selectedId) !== -1;
        wrap.classList.toggle('d-none', !available);
    }

    document.addEventListener('DOMContentLoaded', syncLadderizedVisibility);
</script>
@endpush
