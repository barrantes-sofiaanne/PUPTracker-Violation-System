<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule;
use App\Models\Gender;
use App\Models\Program;
use App\Models\Section;
use App\Models\User;
use App\Models\Year;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Http\Middleware\EnforceInactivityTimeout;

class StudentIdpLoginController extends Controller
{
    /**
     * Start the SSO flow by redirecting the student to the IDP authorization
     * endpoint. No student number is required — the IDP session is the source
     * of truth, and first-time linking is handled safely in the callback.
     *
     * GET /auth/authorize?client_id=...&redirect_uri=...&response_type=code&state=...
     */
    public function start(Request $request): RedirectResponse
    {
        $clientId = (string) config('services.idp.client_id');
        $baseUrl = rtrim((string) config('services.idp.base_url'), '/');
        $redirectUri = (string) config('services.idp.redirect_uri');

        if ($clientId === '' || $baseUrl === '' || $redirectUri === '') {
            return redirect()->route('student.login')
                ->withErrors([
                    'login' => 'IDP login is not configured yet.',
                ]);
        }

        $state = Str::random(40);
        $request->session()->put('student.idp.state', $state);

        $authorizeUrl = $baseUrl . '/auth/authorize';

        return redirect()->away($authorizeUrl . '?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'state' => $state,
        ]));
    }

    /**
     * Handle the IdP callback.
     *
     * Exchanges the authorization code for a token (POST /auth/token, JSON body),
     * fetches the user profile (GET /me, Bearer), maps the IdP identity to a
     * users_tbl record, verifies the local account, and authenticates with the
     * Laravel "student" guard.
     */
    public function callback(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['nullable', 'string'],
        ]);

        $expectedState = $request->session()->pull('student.idp.state');

        // Validate state when the IdP echoes it back. The IdP login SPA does not
        // always preserve state, so validation is skipped when state is absent.
        if (
            is_string($expectedState)
            && $expectedState !== ''
            && $request->filled('state')
            && ! hash_equals($expectedState, (string) $request->string('state'))
        ) {
            return redirect()->route('student.login')
                ->withErrors([
                    'login' => 'The IDP login session expired. Please try again.',
                ]);
        }

        $clientId = (string) config('services.idp.client_id');
        $clientSecret = (string) config('services.idp.client_secret');
        $baseUrl = rtrim((string) config('services.idp.base_url'), '/');
        $timeout = (int) config('services.idp.timeout', 15);

        if ($clientId === '' || $clientSecret === '' || $baseUrl === '') {
            return redirect()->route('student.login')
                ->withErrors([
                    'login' => 'IDP login is not configured yet.',
                ]);
        }

        try {
            // POST /auth/token — body (JSON): { client_id, client_secret, code }
            $tokenResponse = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->post('/auth/token', [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'code' => $validated['code'],
                ]);

            if (! $tokenResponse->successful()) {
                throw new \RuntimeException('Token exchange failed (HTTP ' . $tokenResponse->status() . ').');
            }

            $accessToken = (string) $tokenResponse->json('access_token');
            $refreshToken = (string) $tokenResponse->json('refresh_token');

            if ($accessToken === '') {
                throw new \RuntimeException('Missing access token in IDP response.');
            }

            // GET /me — Bearer token → UserInfoResponse { id, email, first_name,
            // last_name, middle_name, name_suffix, roles }
            $profileResponse = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->withToken($accessToken)
                ->timeout($timeout)
                ->get('/me');

            if (! $profileResponse->successful()) {
                throw new \RuntimeException('Unable to retrieve IDP profile (HTTP ' . $profileResponse->status() . ').');
            }

            $profile = $profileResponse->json();
            $idpSubject = trim((string) data_get($profile, 'id', ''));
            $email = strtolower(trim((string) data_get($profile, 'email', '')));

            if ($idpSubject === '') {
                return redirect()->route('student.login')
                    ->withErrors([
                        'login' => 'Your IDP account is missing a stable identity reference.',
                    ]);
            }

            // IDP role must be student (exact normalized match).
            if (! $this->profileHasStudentRole($profile)) {
                return redirect()->route('student.login')
                    ->withErrors([
                        'login' => 'Your IDP account is not allowed to sign in as a student.',
                    ]);
            }

            $user = $this->resolveLocalUser($idpSubject);

            if (! $user) {
                // FIRST-TIME registrant: this IDP identity has no PUPTracker
                // account yet. Stage the verified IDP profile SERVER-SIDE and
                // send the student to first-time registration, where they supply
                // their student number / program / year.
                //
                // The IDP subject is never sent to the browser — it is referenced
                // from the session only, and it is the sole authority for the
                // account's identity.
                //
                // NOTE: PUPTracker has no pre-existing student-number roster, so
                // this is reached purely from the absence of an IDP-linked
                // account — never from a student-number lookup.
                $request->session()->put('student.idp.pending_profile', [
                    'subject' => $idpSubject,
                    'email' => $email,
                    'first_name' => trim((string) data_get($profile, 'first_name', '')),
                    'middle_name' => trim((string) data_get($profile, 'middle_name', '')),
                    'last_name' => trim((string) data_get($profile, 'last_name', '')),
                    'suffix' => trim((string) data_get($profile, 'name_suffix', '')),
                ]);

                // Backward-compatible aliases used by earlier tests.
                $request->session()->put('student.idp.pending_subject', $idpSubject);
                $request->session()->put('student.idp.pending_email', $email);

                return redirect()->route('student.idp.link')
                    ->with('info', 'Your university account was authenticated. Complete your PUPTracker registration to continue.');
            }

            // Security: prevent an IdP account from binding to a local student
            // that is already linked to a different IdP subject.
            if ((string) $user->idp_subject !== '' && (string) $user->idp_subject !== $idpSubject) {
                return redirect()->route('student.login')
                    ->withErrors([
                        'login' => 'This IDP account is not linked to the local student account.',
                    ]);
            }

            // PUPTracker ACCOUNT STATUS gate.
            //
            // Successful IDP authentication proves IDENTITY but does not grant
            // PUPTracker access. A deactivated account must be denied here even
            // though its IDP credentials are valid and its email is real. This
            // is the first of two checks — EnsureStudentProfileComplete re-checks
            // on every subsequent request so a mid-session deactivation also
            // takes effect.
            if (! $user->isActiveAccount()) {
                $this->logSecurityEvent('IDP login denied: PUPTracker account inactive', [
                    'idp_subject' => $idpSubject,
                    'user_id' => $user->user_id,
                ]);

                return redirect()->route('student.login')
                    ->withErrors([
                        'login' => 'Your PUPTracker account is currently inactive. You cannot access the system at this time. Please contact the appropriate office for assistance.',
                    ]);
            }

            if (! $this->userIsStudent($user)) {
                return redirect()->route('student.login')
                    ->withErrors([
                        'login' => 'This account is not assigned the student role.',
                    ]);
            }

            $this->persistIdpBinding($user, $idpSubject, $email, $profile);
            $this->touchLastLogin($user);

            // Keep tokens server-side (session) for optional refresh/logout,
            // never exposed to the browser.
            $request->session()->put('student.idp.access_token', $accessToken);
            if ($refreshToken !== '') {
                $request->session()->put('student.idp.refresh_token', $refreshToken);
            }

Auth::guard('student')->login($user);
$request->session()->regenerate();

EnforceInactivityTimeout::startFreshActivityWindow($request);

$request->session()->put('show_privacy_consent_modal', true);

            return redirect()->route('student.dashboard');
        } catch (\Throwable $exception) {
            Log::error('Student IDP login failed.', [
                'error' => $exception->getMessage(),
            ]);

            return redirect()->route('student.login')
                ->withErrors([
                    'login' => 'Unable to complete IDP login right now. Please try again later.',
                ]);
        }
    }

    /**
     * Show the "Connect your PUPTracker Account" form.
     *
     * Only reachable when the student has an authenticated-but-unlinked IDP
     * identity in the session (server-side). The student enters ONLY their
     * local PUPTracker student number; the IDP id itself is never shown or
     * typed by the student.
     */
    public function showLinkForm(Request $request)
    {
        $profile = $this->pendingProfile($request);

        if ($profile === null) {
            return redirect()->route('student.idp.login');
        }

        // The IDP is authoritative for identity, so the verified name and
        // institutional email are DISPLAYED read-only instead of being asked for
        // again. The raw IDP subject is never passed to the view as a value.
        $verifiedName = $this->displayName($profile);
        $verifiedEmail = trim((string) ($profile['email'] ?? ''));

        // Academic details are chosen by the student: PUPTracker does not hold a
        // pre-created student record that could supply them. The dropdowns are
        // the SAME reference tables the Admin student screens use, so there is
        // no duplicate program/year/section/gender source.
        $programs = Program::query()
            ->orderBy('program_name')
            ->get(['program_id', 'program_name']);

        $years = Year::query()
            ->orderBy('year_id')
            ->get(['year_id', 'year']);

        $sections = $this->sectionsForRegistration();
        $genders = $this->gendersForRegistration();

        // Which of these programs offer a Ladderized track. Computed here so the
        // rendered page and the browser both use ONE rule — the same config key
        // the Admin student screens and the graduating report read.
        $ladderizedProgramIds = $programs
            ->filter(fn ($program): bool => $this->programSupportsLadderized($program->program_name))
            ->pluck('program_id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();

        return view('student.idp-link', compact(
            'verifiedName',
            'verifiedEmail',
            'programs',
            'years',
            'sections',
            'genders',
            'ladderizedProgramIds'
        ));
    }

    /**
     * Section options for the registration dropdown.
     *
     * Guarded because section_tbl is a LEGACY table in some environments and the
     * registration page must still render (with a clear message) rather than 500.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Section>
     */
    protected function sectionsForRegistration(): \Illuminate\Support\Collection
    {
        try {
            if (! Schema::hasTable('section_tbl')) {
                return collect();
            }

            return Section::query()->orderBy('section_name')->get(['section_id', 'section_name']);
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * Gender options for the registration dropdown.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Gender>
     */
    protected function gendersForRegistration(): \Illuminate\Support\Collection
    {
        try {
            if (! Schema::hasTable('gender_tbl')) {
                return collect();
            }

            return Gender::query()->orderBy('gender_name')->get(['gender_id', 'gender_name']);
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * Program names that offer a Ladderized track (BSOA / BSIT by default).
     *
     * Read from the SAME config key the Admin student screens and the graduating
     * report filter use, so the three can never disagree about which programs
     * qualify. An institution can change the list via
     * GRADUATION_REVIEW_LADDERIZED_PROGRAMS without touching any code.
     *
     * @return array<int, string> upper-cased program names
     */
    protected function ladderizedProgramCodes(): array
    {
        $configured = (array) config(
            'graduation_review.graduating.ladderized_programs',
            ['BSOA', 'BSIT']
        );

        $codes = array_map(
            static fn ($name): string => strtoupper(trim((string) $name)),
            $configured
        );

        return array_values(array_filter(
            $codes,
            static fn (string $name): bool => $name !== ''
        ));
    }

    /**
     * Does this program offer the Ladderized option?
     *
     * Matched case-insensitively, and also accepted when the stored name merely
     * CONTAINS the code — mirroring the Admin student screens. Program names are
     * hand-entered there, so "BS Information Technology" and "BSIT" are the same
     * program and both spellings exist in real data.
     */
    protected function programSupportsLadderized(?string $programName): bool
    {
        $name = strtoupper(trim((string) $programName));

        if ($name === '') {
            return false;
        }

        foreach ($this->ladderizedProgramCodes() as $code) {
            if ($name === $code || str_contains($name, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the ladderized value to store for the chosen program.
     *
     * Returns 0 whenever the program does not offer the option, so selecting a
     * non-ladderized program can never persist a ladderized flag. The client is
     * never trusted for this: a forged "ladderized=1" is discarded here.
     *
     * @param  mixed  $requested  the raw submitted value, if any
     */
    protected function resolveLadderizedSelection(int $programId, $requested): int
    {
        $programName = Program::query()->whereKey($programId)->value('program_name');

        return $this->programSupportsLadderized($programName)
            ? (int) ($requested ?? 0)
            : 0;
    }

    /**
     * FIRST-TIME REGISTRATION — create the student's PUPTracker record.
     *
     * IMPORTANT: PUPTracker has NO pre-existing student-number roster. A
     * successfully authenticated IDP identity is therefore enough to register;
     * the student number is COLLECTED here, not looked up. The old behaviour
     * ("find the admin-created record with this number") is gone.
     *
     * Identity (name/email/idp_subject) is taken ONLY from the server-side IDP
     * session — never from the request. The request contributes academic data
     * (student number, program, year, section, gender) and the privacy-notice
     * acknowledgement. Every academic value is validated against its reference
     * table, so an arbitrary browser-supplied id is rejected.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function linkAccount(Request $request)
    {
        $profile = $this->pendingProfile($request);

        // A first-time link is allowed only after a successful IDP callback.
        if ($profile === null) {
            return redirect()->route('student.login')
                ->withErrors([
                    'login' => 'Your PUP IDP session has expired. Please sign in again.',
                ]);
        }

        // These values are used to locate an EXISTING preloaded student record.
        // They are never used to create a new student account.
        $validated = $request->validate([
            'student_number' => ['required', 'string', 'max:50'],
            'program_id' => ['required', 'integer', 'exists:program_tbl,program_id'],
            'year_id' => ['required', 'integer', 'exists:year_tbl,year_id'],
            'section_id' => ['required', 'integer', 'exists:section_tbl,section_id'],
            'privacy_acknowledged' => ['accepted'],
        ], [
            'student_number.required' => 'Please enter your student number.',
            'program_id.required' => 'Please select your program/course.',
            'program_id.exists' => 'Please select a valid program/course.',
            'year_id.required' => 'Please select your year level.',
            'year_id.exists' => 'Please select a valid year level.',
            'section_id.required' => 'Please select your section.',
            'section_id.exists' => 'Please select a valid section.',
            'privacy_acknowledged.accepted' => 'Please read and acknowledge the Information Privacy Notice to continue.',
        ]);

        $idpSubject = trim((string) ($profile['subject'] ?? ''));

        if ($idpSubject === '') {
            return redirect()->route('student.login')
                ->withErrors([
                    'login' => 'Your IDP profile is missing its identity reference. Please try again.',
                ]);
        }

        // If this IDP identity is already linked, do not attempt another match.
        $alreadyLinked = User::query()
            ->where('idp_subject', $idpSubject)
            ->first();

        if ($alreadyLinked) {
            if (! $this->userIsStudent($alreadyLinked) || ! $alreadyLinked->isActiveAccount()) {
                return redirect()->route('student.login')
                    ->withErrors([
                        'login' => 'This PUPTracker student account is not eligible to sign in.',
                    ]);
            }

            return $this->completeRegistrationFor($request, $alreadyLinked);
        }

        $firstName = $this->normalizeNamePart((string) ($profile['first_name'] ?? ''));
        $middleName = $this->normalizeNamePart((string) ($profile['middle_name'] ?? ''));
        $lastName = $this->normalizeNamePart((string) ($profile['last_name'] ?? ''));

        if ($firstName === '' || $lastName === '') {
            return back()
                ->withInput($request->except(['_token']))
                ->withErrors([
                    'registration' => 'Your IDP profile does not contain enough name information to match your student record. Please contact the appropriate office.',
                ]);
        }

        // Match the IDP-verified name AND all submitted academic identifiers
        // against the existing student roster. No User or StudentInfo row is created.
        $matchingStudents = User::query()
            ->where('roles_id', 2)
            ->where('student_number', trim((string) $validated['student_number']))
            ->whereRaw('LOWER(TRIM(COALESCE(first_name, ?))) = ?', ['', $firstName])
            ->whereRaw('LOWER(TRIM(COALESCE(middle_name, ?))) = ?', ['', $middleName])
            ->whereRaw('LOWER(TRIM(COALESCE(last_name, ?))) = ?', ['', $lastName])
            ->whereHas('studentInfo', function ($query) use ($validated) {
                $query->where('program_id', (int) $validated['program_id'])
                    ->where('year_id', (int) $validated['year_id'])
                    ->where('section_id', (int) $validated['section_id']);
            })
            ->get();

        if ($matchingStudents->count() !== 1) {
            $this->logSecurityEvent(
                $matchingStudents->isEmpty()
                    ? 'First-time IDP linking rejected: no existing student record matched'
                    : 'First-time IDP linking rejected: multiple existing student records matched',
                [
                    'idp_subject' => $idpSubject,
                    'student_number' => trim((string) $validated['student_number']),
                ]
            );

            return back()
                ->withInput($request->except(['_token']))
                ->withErrors([
                    'registration' => $matchingStudents->isEmpty()
                        ? 'We could not find an existing student record matching the information provided. Please check your details or contact the appropriate office.'
                        : 'More than one student record matched your information. Please contact the appropriate office for assistance.',
                ]);
        }

        $studentId = $matchingStudents->first()->user_id;

        try {
            $student = DB::transaction(function () use ($studentId, $idpSubject, $profile) {
                // Re-read and lock the selected record to prevent concurrent linking.
                $student = User::query()
                    ->where('user_id', $studentId)
                    ->lockForUpdate()
                    ->first();

                if (! $student || (int) $student->roles_id !== 2) {
                    throw new \RuntimeException('The matched student record is no longer available.');
                }

                if (! $student->isActiveAccount()) {
                    throw new \RuntimeException('The matched student account is inactive.');
                }

                $currentSubject = trim((string) ($student->idp_subject ?? ''));

                if ($currentSubject !== '' && $currentSubject !== $idpSubject) {
                    throw new \RuntimeException('The matched student record is already linked to another IDP identity.');
                }

                $subjectInUse = User::query()
                    ->where('idp_subject', $idpSubject)
                    ->where('user_id', '!=', $student->user_id)
                    ->exists();

                if ($subjectInUse) {
                    throw new \RuntimeException('This IDP identity is already linked to another student record.');
                }

                // Preserve the existing student number, enrollment, gender,
                // status, violations, and all other PUPTracker-owned data.
                $this->persistIdpBinding(
                    $student,
                    $idpSubject,
                    (string) ($profile['email'] ?? ''),
                    $profile
                );

                // persistIdpBinding logs its own database errors; verify that the
                // permanent link was actually saved before allowing the login.
                $student->refresh();

                if ((string) $student->idp_subject !== $idpSubject) {
                    throw new \RuntimeException('The IDP account binding could not be saved.');
                }

                $this->touchLastLogin($student);

                return $student;
            });
        } catch (\Throwable $e) {
            $this->logSecurityEvent('First-time IDP linking failed', [
                'idp_subject' => $idpSubject,
                'user_id' => $studentId,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput($request->except(['_token']))
                ->withErrors([
                    'registration' => 'We could not link your IDP account to that student record. Please verify your details or contact the appropriate office.',
                ]);
        }

        $this->logSecurityEvent('Existing student record linked to IDP identity', [
            'idp_subject' => $idpSubject,
            'user_id' => $student->user_id,
        ]);

        return $this->completeRegistrationFor($request, $student);
    }

    /**
     * Finish a successful registration: clear the staged IDP flow, log the
     * student in, and send them to the dashboard.
     *
     * Shared by the normal path and the duplicate/race paths so a re-submitted
     * registration (or a lost race) still ends in a signed-in dashboard visit
     * rather than a duplicate account.
     */
protected function completeRegistrationFor(
    Request $request,
    User $student
): \Illuminate\Http\RedirectResponse {

    // A successfully linked existing student record is complete
    // for purposes of the Student Module profile middleware.
    if (empty($student->profile_completed_at)) {
        $student->profile_completed_at = now();
        $student->save();
    }

    // Clear temporary IDP registration information.
    $request->session()->forget([
        'student.idp.pending_profile',
        'student.idp.pending_subject',
        'student.idp.pending_email',
        'student.idp.pending_student_number',
        'student.idp.pending_program_id',
        'student.idp.pending_year_id',
    ]);

    // Authenticate the student using the student guard.
    Auth::guard('student')->login($student);

    // Regenerate the session after authentication.
    $request->session()->regenerate();

    EnforceInactivityTimeout::startFreshActivityWindow($request);

    $request->session()->put('show_privacy_consent_modal', true);

    return redirect()->route('student.dashboard')
        ->with(
            'success',
            'Your existing student record has been linked successfully. You may now access PUPTracker.'
        );
}

    /**
     * Create the enrollment record for a newly registered student.
     *
     * Runs INSIDE the registration transaction and deliberately lets failures
     * propagate: an account without its enrollment row would be a partial
     * registration, so the whole transaction must roll back instead.
     *
     * @param  int  $ladderized  0/1 — only meaningful for a ladderized program
     *
     * @throws \Throwable
     */
    protected function createStudentInfo(
        User $student,
        int $programId,
        int $yearId,
        int $sectionId,
        int $ladderized = 0
    ): void {
        $data = [
            'user_id' => $student->user_id,
            'program_id' => $programId,
            'year_id' => $yearId,
        ];

        // Guarded like the other optional columns: student_info_tbl is a LEGACY
        // table, so a deployment without this column must still register.
        if (Schema::hasColumn('student_info_tbl', 'ladderized')) {
            $data['ladderized'] = $ladderized;
        }

        // Section is student-selected, never defaulted to N/A for a new student.
        if (Schema::hasColumn('student_info_tbl', 'section_id')) {
            $data['section_id'] = $sectionId;
        }

        if (Schema::hasColumn('student_info_tbl', 'student_status_id')) {
            $data['student_status_id'] = $this->defaultStudentStatusId();
        }

        $student->studentInfo()->updateOrCreate(['user_id' => $student->user_id], $data);
    }

    /**
     * Status id that marks an account active (used for users_tbl.status_id).
     * Falls back to 1, which is the seeded "Active" row in this project.
     */
    protected function defaultStudentStatusId(): int
    {
        try {
            $id = DB::table('status_tbl')
                ->whereRaw('LOWER(status_name) = ?', ['active'])
                ->value('status_id');

            return $id !== null ? (int) $id : 1;
        } catch (\Throwable $e) {
            return 1;
        }
    }

    /** Lowercased IDP email, or null when the IDP returned none. */
    protected function normalizedEmail(array $profile): ?string
    {
        $email = strtolower(trim((string) ($profile['email'] ?? '')));

        return $email === '' ? null : $email;
    }

    /** Whether a QueryException is a unique/duplicate-key violation. */
    protected function isUniqueViolation(\Illuminate\Database\QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        // 23000 = integrity constraint violation (MySQL + SQLite),
        // 1062 = MySQL duplicate entry, 19 = SQLite constraint failed.
        return $sqlState === '23000' || $driverCode === 1062 || $driverCode === 19;
    }



    /**
     * Read the pending IDP profile from the session, or null when absent.
     */
    protected function pendingProfile(Request $request): ?array
    {
        $profile = $request->session()->get('student.idp.pending_profile');

        if (! is_array($profile) || trim((string) ($profile['subject'] ?? '')) === '') {
            return null;
        }

        return $profile;
    }

    /**
     * Build the display name from an IDP profile array.
     */
    protected function displayName(array $profile): string
    {
        return trim(implode(' ', array_filter([
            $profile['first_name'] ?? '',
            $profile['middle_name'] ?? '',
            $profile['last_name'] ?? '',
        ], fn ($part) => $part !== '')));
    }

    /**
     * Normalize a name part for comparison: trim, collapse whitespace, lowercase.
     */
    protected function normalizeNamePart(string $value): string
    {
        return strtolower((string) preg_replace('/\s+/u', ' ', trim($value)));
    }


    /**
     * Copy the authoritative IDP identity fields into the local user.
     *
     * The IDP owns first_name, middle_name, last_name, name_suffix and
     * idp_email. These are written on first linking and refreshed on every
     * subsequent login so PUPTracker stays synchronized with the IDP.
     *
     * PUPTracker-owned academic fields (student_number, program, year, section,
     * status) are intentionally NOT touched here.
     *
     * Never blanks a local name: IDP values that are empty leave the existing
     * value in place.
     *
     * @param  array{first_name?: string, middle_name?: string, last_name?: string, suffix?: string, name_suffix?: string, email?: string}  $profile
     */
    protected function syncIdentityFromIdp(User $user, array $profile): void
    {
        $changes = [];

        $firstName = trim((string) ($profile['first_name'] ?? ''));
        $middleName = trim((string) ($profile['middle_name'] ?? ''));
        $lastName = trim((string) ($profile['last_name'] ?? ''));
        // Support both the /me response key (name_suffix) and the staged
        // session profile key (suffix).
        $suffix = trim((string) ($profile['name_suffix'] ?? $profile['suffix'] ?? ''));
        $email = strtolower(trim((string) ($profile['email'] ?? '')));

        if ($firstName !== '') {
            $changes['first_name'] = $firstName;
        }
        if ($middleName !== '') {
            $changes['middle_name'] = $middleName;
        }
        if ($lastName !== '') {
            $changes['last_name'] = $lastName;
        }
        if ($suffix !== '') {
            // Guarded: name_suffix may not exist on a legacy/unmigrated DB.
            if (\Illuminate\Support\Facades\Schema::hasColumn('users_tbl', 'name_suffix')) {
                $changes['name_suffix'] = $suffix;
            }
        }
        if ($email !== '') {
            $changes['idp_email'] = $email;
        }

        if ($changes !== []) {
            try {
                $user->forceFill($changes)->save();
            } catch (\Throwable $e) {
                Log::warning('Could not sync IDP identity into local user', [
                    'user_id' => $user->user_id ?? $user->getKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }


    /**
     * Log a security-relevant event without exposing tokens or secrets.
     */
    protected function logSecurityEvent(string $message, array $context = []): void
    {
        Log::warning('IDP linking security event: ' . $message, $context);
    }

    /**
     * Determine whether the IdP profile carries a student role.
     * IdP /me returns "roles" as a string (e.g. "student").
     */
    protected function profileHasStudentRole(array $profile): bool
    {
        $rolesRaw = (string) data_get($profile, 'roles', '');

        if (trim($rolesRaw) === '') {
            return true; // No role info returned; rely on local role check.
        }

        $roles = $this->normalizeRoleTokens($rolesRaw);
        $aliases = $this->normalizeRoleTokens((string) config('services.idp.student_roles', 'student'));

        foreach ($roles as $role) {
            if (in_array($role, $aliases, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Split a role string into normalized whole tokens.
     */
    protected function normalizeRoleTokens(string $value): array
    {
        $tokens = preg_split('/[\s,|]+/', strtolower(trim($value)), -1, PREG_SPLIT_NO_EMPTY);

        return $tokens === false ? [] : array_values(array_unique($tokens));
    }

    /**
     * Resolve the local users_tbl record for the given IdP identity.
     *
     * The idp_subject binding is the permanent identity link — this is the ONLY
     * auto-login path. There is intentionally no email fallback here: an
     * unlinked IDP user is always sent through the "Connect your PUPTracker
     * Account" flow, where the student number is verified against the local
     * record and the IDP identity is compared to the local name before linking.
     *
     * Never auto-creates accounts and never links by email.
     */
    protected function resolveLocalUser(string $idpSubject): ?User
    {
        try {
            return User::query()->where('idp_subject', $idpSubject)->first();
        } catch (\Throwable $e) {
            Log::warning('Could not query idp_subject column', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Persist the IdP subject binding + authoritative identity on the local user.
     *
     * idp_subject is the permanent identity link; idp_email and the name fields
     * (first/middle/last/name_suffix) are refreshed from the IDP profile on
     * every login because the IDP is the authoritative source for identity.
     */
    protected function persistIdpBinding(User $user, string $idpSubject, string $email, array $profile = []): void
    {
        try {
            $wasFirstLink = $user->idp_subject === null || (string) $user->idp_subject === '';

            $user->forceFill([
                'idp_subject' => $idpSubject,
                'idp_email' => $email !== '' ? $email : $user->idp_email,
                'idp_connected_at' => $wasFirstLink ? now() : ($user->idp_connected_at ?? now()),
            ])->save();

            // IDP is authoritative for name identity — refresh the local snapshot
            // from the authenticated profile (fresh from /me).
            if ($profile !== []) {
                $this->syncIdentityFromIdp($user->fresh(), $profile);
            }
        } catch (\Throwable $e) {
            Log::warning('Could not update idp fields', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Verify the local user is assigned the student role (exact normalized match).
     */
    protected function userIsStudent(User $user): bool
    {
        try {
            $role = $user->role;

            if ($role === null) {
                // No role record; treat "roles_id" as the student role id (2).
                return (int) $user->roles_id === 2;
            }

            $roleName = (string) $role->roles_name;

            return in_array('student', $this->normalizeRoleTokens($roleName), true);
        } catch (\Throwable $e) {
            Log::warning('Could not verify student role', ['error' => $e->getMessage()]);

            return false; // Fail closed when role verification is unavailable.
        }
    }

    /**
     * Update the last login timestamp if the column exists.
     */
    protected function touchLastLogin(User $user): void
    {
        try {
            $user->forceFill([
                'idp_last_login_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('Could not update idp_last_login_at', ['error' => $e->getMessage()]);
        }
    }
}