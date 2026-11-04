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
            <i class="bi bi-lock-fill" aria-hidden="true"></i>
            <span><strong>Existing student record found.</strong> The information below is read-only. Only an authorized PUPTracker administrator can edit account and academic information. To request a correction, email the appropriate PUPTracker administrator and attach valid proof of identity.</span>
        </div>
        <div class="ap-card" style="padding:1.25rem;border:1px solid rgba(128,0,0,.16);border-radius:16px;margin-bottom:1.25rem;">
            <h3 class="ap-step-title">Your matched student record</h3>
            <div class="ap-grid-2">
                <div class="ap-field-group"><label class="ap-label">Student number</label><div class="ap-field ap-field--locked"><input value="{{ $matchedStudent->student_number ?: '—' }}" readonly disabled></div></div>
                <div class="ap-field-group"><label class="ap-label">Program/Course</label><div class="ap-field ap-field--locked"><input value="{{ optional(optional($matchedStudent->studentInfo)->program)->program_name ?: '—' }}" readonly disabled></div></div>
                <div class="ap-field-group"><label class="ap-label">Year level</label><div class="ap-field ap-field--locked"><input value="{{ optional(optional($matchedStudent->studentInfo)->year)->year ?: '—' }}" readonly disabled></div></div>
                <div class="ap-field-group"><label class="ap-label">Section</label><div class="ap-field ap-field--locked"><input value="{{ optional(optional($matchedStudent->studentInfo)->section)->section_name ?: '—' }}" readonly disabled></div></div>
            </div>
            <p class="ap-field-help">If any displayed information is incorrect, do not proceed. Contact an administrator and provide proof of identity so the record can be reviewed.</p>
        </div>
        <form method="POST" action="{{ route('student.idp.link.post') }}" class="ap-form">
            @csrf
            <input type="hidden" name="match_mode" value="single">
            <div class="ap-field-group" style="margin-bottom:1rem;">
                <label style="display:flex;align-items:flex-start;gap:.65rem;">
                    <input type="checkbox" name="confirm_record" value="1" required style="margin-top:.25rem;">
                    <span>I confirm that the read-only student record displayed above belongs to me.</span>
                </label>
            </div>
            <div class="ap-field-group" style="margin-bottom:1rem;">
                <label style="display:flex;align-items:flex-start;gap:.65rem;">
                    <input type="checkbox" name="privacy_acknowledged" value="1" required style="margin-top:.25rem;">
                    <span>I have read and acknowledge the PUPTracker Information Privacy Notice.</span>
                </label>
            </div>
            <button type="submit" class="ap-btn ap-btn--compact"><span>Confirm and Link My Account</span><i class="bi bi-link-45deg" aria-hidden="true"></i></button>
        </form>

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
            <div class="ap-field-group"><label style="display:flex;gap:.65rem;align-items:flex-start;"><input type="checkbox" name="privacy_acknowledged" value="1" required style="margin-top:.25rem;"><span>I have read and acknowledge the PUPTracker Information Privacy Notice.</span></label></div>
            <button type="submit" class="ap-btn ap-btn--compact"><span>Verify and Link Existing Record</span><i class="bi bi-link-45deg" aria-hidden="true"></i></button>
        </form>

    @else
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
                <div class="ap-field-group"><label class="ap-label" for="program_id">Program/Course <span class="ap-req">*</span></label><div class="ap-field ap-field--select"><select id="program_id" name="program_id" required><option value="">Select program</option>@foreach($programs as $program)<option value="{{ $program->program_id }}" @selected((string)old('program_id') === (string)$program->program_id)>{{ $program->program_name }}</option>@endforeach</select></div></div>
                <div class="ap-field-group"><label class="ap-label" for="year_id">Year level <span class="ap-req">*</span></label><div class="ap-field ap-field--select"><select id="year_id" name="year_id" required><option value="">Select year</option>@foreach($years as $year)<option value="{{ $year->year_id }}" @selected((string)old('year_id') === (string)$year->year_id)>{{ $year->year }}</option>@endforeach</select></div></div>
                <div class="ap-field-group"><label class="ap-label" for="section_id">Section <span class="ap-req">*</span></label><div class="ap-field ap-field--select"><select id="section_id" name="section_id" required><option value="">Select section</option>@foreach($sections as $section)<option value="{{ $section->section_id }}" @selected((string)old('section_id') === (string)$section->section_id)>{{ $section->section_name }}</option>@endforeach</select></div></div>
            </div>
            <div class="ap-field-group"><label style="display:flex;gap:.65rem;align-items:flex-start;"><input type="checkbox" name="privacy_acknowledged" value="1" required style="margin-top:.25rem;"><span>I have read and acknowledge the PUPTracker Information Privacy Notice.</span></label></div>
            <button type="submit" class="ap-btn ap-btn--compact"><span>Create Student Account and Continue</span><i class="bi bi-check2-circle" aria-hidden="true"></i></button>
        </form>
    @endif

    <a href="{{ route('student.login') }}" class="ap-back" style="display:inline-flex;margin-top:1.25rem;">
        <i class="bi bi-arrow-left" aria-hidden="true"></i><span>Back to Student Login</span>
    </a>
</div>
@endsection
