{{--

    Existing Student Record Linking — four visual steps.



    Presentation only. This is still ONE form posting to student.idp.link.post

    with the required matching fields; the server validates them against the existing student roster. The student's name and email come

    from the verified PUP IDP session and are display-only (never submitted).



    Step 1  Identity Information   verified name + email (read-only)

    Step 2  Student Record Matching  student number, program, year, section

    Step 3  Account Setup         IDP-linked account + Information Privacy Notice

    Step 4  Review & Submit       read-back, then Link Student Record




    Without JavaScript every step is shown on one page and the form still works.

--}}

@php

    // Which step a server-side validation error belongs to, so a re-rendered

    // form opens on the step that needs attention.

    $stepOfField = [


        'student_number' => 2,

        'program_id' => 2,


        'year_id' => 2,

        'section_id' => 2,

        'privacy_acknowledged' => 3,

    ];



    $errorSteps = [];



    foreach ($stepOfField as $field => $stepNumber) {

        if ($errors->has($field)) {

            $errorSteps[$stepNumber] = true;

        }

    }



    $initialStep = $errorSteps !== []

        ? min(array_keys($errorSteps))

        : (($errors->has('registration') || $errors->has('login')) ? 4 : 1);






    $stepLabels = [

        1 => 'Identity Information',

        2 => 'Student Record Matching',

        3 => 'Account Setup',

        4 => 'Review & Submit',

    ];

@endphp



@extends('layouts.auth-portal', [

    'title'    => 'Link Your Existing PUPTracker Student Record',

    'portal'   => 'student',

    'brandKey' => 'signup',

    'wide'     => true,

])



@section('ap-content')

    <div class="ap-wizard">



        <h2 class="ap-heading ap-heading--left">Link Your Existing PUPTracker Student Record</h2>

        <p class="ap-subtitle ap-subtitle--left">

            Welcome to PUPTracker! Your university account has been successfully authenticated.

            To link your university account, provide the details that match your existing student record in PUPTracker.

        </p>



        @if (session('info'))

            <div class="ap-alert ap-alert--info" role="status">

                <i class="bi bi-info-circle-fill" aria-hidden="true"></i>

                <span>{{ session('info') }}</span>

            </div>

        @endif



        @if($errors->has('login'))

            <div class="ap-alert" role="alert">

                <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>

                <span>{{ $errors->first('login') }}</span>

            </div>

        @endif



        @if($errors->has('registration'))

            <div class="ap-alert" role="alert">

                <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>

                <span>{{ $errors->first('registration') }}</span>

            </div>

        @endif



        <ol class="ap-steps ap-steps--wizard" aria-label="Registration progress" data-wizard-steps>

            @foreach($stepLabels as $number => $label)

                <li class="ap-step{{ isset($errorSteps[$number]) ? ' has-error' : '' }}" data-step-indicator="{{ $number }}">

                    <span class="ap-step-dot">{{ $number }}</span>

                    <span class="ap-step-label">{{ $label }}</span>

                </li>

            @endforeach

        </ol>



        <form method="POST"

              action="{{ route('student.idp.link.post') }}"

              autocomplete="off"

              class="ap-form ap-wizard-form"

              id="registrationForm"

              data-wizard-form

              data-initial-step="{{ $initialStep }}">



            @csrf



            <div class="ap-step-viewport" data-wizard-viewport>



                {{-- ================= STEP 1 — BASIC INFORMATION ================= --}}

                <section class="ap-step-panel" data-step-panel="1" aria-labelledby="stepTitle1">

                    <h3 class="ap-step-title" id="stepTitle1" tabindex="-1">Identity Information</h3>

                    <p class="ap-step-help" id="idpSectionHelp">

                        These details were provided by your university account and cannot be edited in PUPTracker.

                    </p>



                    {{-- DISPLAY ONLY. The server never reads these back: name and email

                         are taken from the verified IDP session when the account is

                         created. \`disabled\` inputs are not submitted by the browser. --}}

                    <div class="ap-field-group">

                        <label class="ap-label" for="verified_name">Full Name</label>

                        <div class="ap-field ap-field--locked">

                            <i class="bi bi-person-fill ap-field-icon" aria-hidden="true"></i>

                            <input id="verified_name"

                                   type="text"

                                   value="{{ $verifiedName ?: '—' }}"

                                   aria-describedby="idpSectionHelp"

                                   disabled

                                   readonly>

                            <i class="bi bi-lock-fill ap-field-lock" title="Provided by PUP IDP" aria-hidden="true"></i>

                        </div>

                    </div>



                    <div class="ap-field-group">

                        <label class="ap-label" for="verified_email">Email Address</label>

                        <div class="ap-field ap-field--locked">

                            <i class="bi bi-envelope-fill ap-field-icon" aria-hidden="true"></i>

                            <input id="verified_email"

                                   type="text"

                                   value="{{ $verifiedEmail ?: '—' }}"

                                   aria-describedby="idpSectionHelp"

                                   disabled

                                   readonly>

                            <i class="bi bi-lock-fill ap-field-lock" title="Provided by PUP IDP" aria-hidden="true"></i>

                        </div>

                    </div>



                    <p class="ap-verified">

                        <i class="bi bi-patch-check-fill" aria-hidden="true"></i>

                        Verified by PUP Identity Provider

                    </p>



                    <div class="ap-field-group">


                    </div>

                </section>



                {{-- ================= STEP 2 — STUDENT RECORD MATCHING ================= --}}

                <section class="ap-step-panel" data-step-panel="2" aria-labelledby="stepTitle2">

                    <h3 class="ap-step-title" id="stepTitle2" tabindex="-1">Student Record Matching</h3>

                    <p class="ap-step-help">

                        Enter the details exactly as recorded in your existing PUPTracker student record. These details will be used to find and verify your record.

                    </p>



                    <div class="ap-field-group">

                        <label class="ap-label" for="student_number">

                            Student Number <span class="ap-req" aria-hidden="true">*</span>

                        </label>

                        <div class="ap-field @error('student_number') has-error @enderror">

                            <i class="bi bi-person-badge ap-field-icon" aria-hidden="true"></i>

                            <input id="student_number"

                                   type="text"

                                   name="student_number"

                                   value="{{ old('student_number') }}"

                                   placeholder="e.g. 2026-00001"

                                   maxlength="50"

                                   autocomplete="off"

                                   aria-describedby="studentNumberHelp"

                                   data-required-message="Please enter your student number."

                                   required>

                        </div>

                        <p class="ap-field-help" id="studentNumberHelp">

                            Enter your official student number. It will be matched against your existing student record.

                        </p>

                        <p class="ap-field-error" data-error-for="student_number">{{ $errors->first('student_number') }}</p>

                    </div>



                    <div class="ap-field-group">

                        <label class="ap-label" for="program_id">

                            Program/Course <span class="ap-req" aria-hidden="true">*</span>

                        </label>

                        <div class="ap-field ap-field--select @error('program_id') has-error @enderror">

                            <i class="bi bi-mortarboard ap-field-icon" aria-hidden="true"></i>

                            <select id="program_id"

                                    name="program_id"


                                    data-required-message="Please select your program/course."

                                    required>

                                <option value="">Select Program/Course</option>

                                @foreach ($programs as $program)

                                    <option value="{{ $program->program_id }}"

                                        @selected((string) old('program_id') === (string) $program->program_id)>

                                        {{ $program->program_name }}

                                    </option>

                                @endforeach

                            </select>

                            <i class="bi bi-chevron-down ap-field-caret" aria-hidden="true"></i>

                        </div>

                        <p class="ap-field-error" data-error-for="program_id">{{ $errors->first('program_id') }}</p>

                    </div>




                    </div>



                    <div class="ap-grid-2">

                        <div class="ap-field-group">

                            <label class="ap-label" for="year_id">

                                Year Level <span class="ap-req" aria-hidden="true">*</span>

                            </label>

                            <div class="ap-field ap-field--select @error('year_id') has-error @enderror">

                                <i class="bi bi-calendar3 ap-field-icon" aria-hidden="true"></i>

                                <select id="year_id"

                                        name="year_id"

                                        data-required-message="Please select your year level."

                                        required>

                                    <option value="">Select Year Level</option>

                                    @foreach ($years as $year)

                                        <option value="{{ $year->year_id }}"

                                            @selected((string) old('year_id') === (string) $year->year_id)>

                                            {{ $year->year }}

                                        </option>

                                    @endforeach

                                </select>

                                <i class="bi bi-chevron-down ap-field-caret" aria-hidden="true"></i>

                            </div>

                            <p class="ap-field-error" data-error-for="year_id">{{ $errors->first('year_id') }}</p>

                        </div>



                        <div class="ap-field-group">

                            <label class="ap-label" for="section_id">

                                Section <span class="ap-req" aria-hidden="true">*</span>

                            </label>

                            <div class="ap-field ap-field--select @error('section_id') has-error @enderror">

                                <i class="bi bi-diagram-3 ap-field-icon" aria-hidden="true"></i>

                                <select id="section_id"

                                        name="section_id"

                                        data-required-message="Please select your section."

                                        required>

                                    <option value="">Select Section</option>

                                    @foreach ($sections as $section)

                                        <option value="{{ $section->section_id }}"

                                            @selected((string) old('section_id') === (string) $section->section_id)>

                                            {{ $section->section_name }}

                                        </option>

                                    @endforeach

                                </select>

                                <i class="bi bi-chevron-down ap-field-caret" aria-hidden="true"></i>

                            </div>

                            <p class="ap-field-error" data-error-for="section_id">{{ $errors->first('section_id') }}</p>

                        </div>

                    </div>

                </section>



                {{-- ================= STEP 3 — ACCOUNT SETUP ================= --}}

                <section class="ap-step-panel" data-step-panel="3" aria-labelledby="stepTitle3">

                    <h3 class="ap-step-title" id="stepTitle3" tabindex="-1">Account Setup</h3>

                    <p class="ap-step-help">

                        Your PUPTracker account will be linked to your verified PUP Identity Provider account.

                    </p>



                    <div class="ap-idp-card">

                        <span class="ap-idp-card-icon" aria-hidden="true"><i class="bi bi-patch-check-fill"></i></span>

                        <div>

                            <strong>{{ $verifiedName ?: 'Your university account' }}</strong>

                            <span>{{ $verifiedEmail ?: '—' }}</span>

                        </div>

                    </div>



                    {{-- Displayed BEFORE submission so the student reads it before any data

                         is sent. Worded as a notice acknowledgement, not affirmative consent. --}}

                    <div class="ap-notice" role="note" aria-labelledby="privacyNoticeHeading">

                        <h4 class="ap-notice-title" id="privacyNoticeHeading">

                            <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>

                            Information Privacy Notice

                        </h4>



                        <p>

                            The information you provide will be collected and processed solely for purposes related to

                            the operation of the PUPTracker system, including student identification, violation and

                            sanction management, record keeping, and other authorized student-related processes.

                        </p>



                        <p>

                            Only information necessary for these purposes will be collected. Your information will be

                            handled in accordance with applicable institutional policies and data privacy requirements.

                            By proceeding with registration, you acknowledge that you have read and understood this notice.

                        </p>

                    </div>



                    <div class="ap-field-group">

                        <label class="ap-check @error('privacy_acknowledged') has-error @enderror" for="privacy_acknowledged">

                            <input type="checkbox"

                                   id="privacy_acknowledged"

                                   name="privacy_acknowledged"

                                   value="1"

                                   data-required-message="Please read and acknowledge the Information Privacy Notice to continue."

                                   @checked(old('privacy_acknowledged'))

                                   required>

                            <span>

                                I have read and understood the Information Privacy Notice.

                                <span class="ap-req" aria-hidden="true">*</span>

                            </span>

                        </label>

                        <p class="ap-field-error" data-error-for="privacy_acknowledged">{{ $errors->first('privacy_acknowledged') }}</p>

                    </div>

                </section>



                {{-- ================= STEP 4 — REVIEW & SUBMIT ================= --}}

                <section class="ap-step-panel" data-step-panel="4" aria-labelledby="stepTitle4">

                    <h3 class="ap-step-title" id="stepTitle4" tabindex="-1">Review &amp; Submit</h3>

                    <p class="ap-step-help">

                        Please check your details. Your existing record will only be linked after the details are verified.

                    </p>



                    <div class="ap-review" data-review>

                        <div class="ap-review-group">

                            <div class="ap-review-head">

                                <h4>Identity Information</h4>

                                <button type="button" class="ap-link ap-review-edit" data-goto-step="1">Edit</button>

                            </div>

                            <dl>

                                <div><dt>Full Name</dt><dd>{{ $verifiedName ?: '—' }}</dd></div>

                                <div><dt>Email Address</dt><dd>{{ $verifiedEmail ?: '—' }}</dd></div>


                            </dl>

                        </div>



                        <div class="ap-review-group">

                            <div class="ap-review-head">

                                <h4>Student Record Matching</h4>

                                <button type="button" class="ap-link ap-review-edit" data-goto-step="2">Edit</button>

                            </div>

                            <dl>

                                <div><dt>Student Number</dt><dd data-review-value="student_number">—</dd></div>

                                <div><dt>Program/Course</dt><dd data-review-value="program_id">—</dd></div>


                                <div><dt>Year Level</dt><dd data-review-value="year_id">—</dd></div>

                                <div><dt>Section</dt><dd data-review-value="section_id">—</dd></div>

                            </dl>

                        </div>



                        <div class="ap-review-group">

                            <div class="ap-review-head">

                                <h4>Account Setup</h4>

                                <button type="button" class="ap-link ap-review-edit" data-goto-step="3">Edit</button>

                            </div>

                            <dl>

                                <div><dt>Sign-in</dt><dd>Linked to your PUP Identity Provider account</dd></div>

                                <div><dt>Privacy Notice</dt><dd data-review-value="privacy_acknowledged">—</dd></div>

                            </dl>

                        </div>

                    </div>

                </section>



            </div>



            <div class="ap-step-actions">

                <button type="button" class="ap-btn ap-btn--ghost ap-btn--compact" data-wizard-back hidden>

                    <i class="bi bi-arrow-left" aria-hidden="true"></i>

                    <span>Back</span>

                </button>



                <button type="button" class="ap-btn ap-btn--compact" data-wizard-next hidden>

                    <span>Next</span>

                    <i class="bi bi-arrow-right" aria-hidden="true"></i>

                </button>



                <button type="submit"

                        class="ap-btn ap-btn--compact"

                        id="completeRegistrationBtn"

                        data-wizard-submit>

                    <span>Link Student Record</span>

                    <i class="bi bi-check2-circle" aria-hidden="true"></i>

                </button>

            </div>

        </form>



        <a href="{{ route('student.login') }}"

           class="ap-back"

           data-soft-nav

           data-portal-theme="student">

            <i class="bi bi-arrow-left" aria-hidden="true"></i>

            <span>Back to Login</span>

        </a>



    </div>

@endsection