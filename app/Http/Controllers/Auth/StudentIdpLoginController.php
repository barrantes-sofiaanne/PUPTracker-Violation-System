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

// The IDP subject already resolves to this local student record, so this is
            // an established binding. Use the same completion path as first-time
            // linking so profile_completed_at is set before dashboard access.
            return $this->completeRegistrationFor($request, $user);
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

        $verifiedName = $this->displayName($profile);
        $verifiedEmail = trim((string) ($profile['email'] ?? ''));
        $nameMatches = $this->findStudentsByIdpName($profile);
        $matchCount = $nameMatches->count();
        $matchMode = $matchCount === 1 ? 'single' : ($matchCount > 1 ? 'duplicate' : 'new');
        $matchedStudent = $matchCount === 1
            ? $nameMatches->first()
            : null;

        $programs = Program::query()->orderBy('program_name')->get(['program_id', 'program_name']);
        $years = Year::query()->orderBy('year_id')->get(['year_id', 'year']);
        $sections = $this->sectionsForRegistration();
        $genders = $this->gendersForRegistration();
        $ladderizedProgramIds = $programs
            ->filter(fn ($program): bool => $this->programSupportsLadderized($program->program_name))
            ->pluck('program_id')->map(static fn ($id): int => (int) $id)->values()->all();

        return view('student.idp-link', compact(
            'verifiedName', 'verifiedEmail', 'matchMode', 'matchCount',
            'matchedStudent', 'programs', 'years', 'sections', 'genders',
            'ladderizedProgramIds'
        ));
    }

    /** Find preloaded student accounts using the verified IDP first/middle/last name. */
    protected function findStudentsByIdpName(array $profile)
    {
        $first = $this->normalizeNamePart((string) ($profile['first_name'] ?? ''));
        $middle = $this->normalizeNamePart((string) ($profile['middle_name'] ?? ''));
        $last = $this->normalizeNamePart((string) ($profile['last_name'] ?? ''));

        if ($first === '' || $last === '') {
            return collect();
        }

        return User::query()
            ->with(['studentInfo.program', 'studentInfo.year', 'studentInfo.section', 'gender'])
            ->where('roles_id', 2)
            ->whereRaw("LOWER(TRIM(COALESCE(first_name, ''))) = ?", [$first])
            ->whereRaw("LOWER(TRIM(COALESCE(middle_name, ''))) = ?", [$middle])
            ->whereRaw("LOWER(TRIM(COALESCE(last_name, ''))) = ?", [$last])
            ->where(function ($query) {
                $query->whereNull('idp_subject')->orWhere('idp_subject', '');
            })
            ->get();
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
        if ($profile === null) {
            return redirect()->route('student.login')->withErrors([
                'login' => 'Your PUP IDP session has expired. Please sign in again.',
            ]);
        }

        $idpSubject = trim((string) ($profile['subject'] ?? ''));
        if ($idpSubject === '') {
            return redirect()->route('student.login')->withErrors([
                'login' => 'Your IDP profile is missing its identity reference. Please try again.',
            ]);
        }

        // Idempotency: an IDP subject already linked to a student always signs in
        // to that same local account; it must never create or select another one.
        $alreadyLinked = User::query()->where('idp_subject', $idpSubject)->first();
        if ($alreadyLinked) {
            if (! $this->userIsStudent($alreadyLinked) || ! $alreadyLinked->isActiveAccount()) {
                return redirect()->route('student.login')->withErrors([
                    'login' => 'This PUPTracker student account is not eligible to sign in.',
                ]);
            }
            return $this->completeRegistrationFor($request, $alreadyLinked);
        }

        $mode = (string) $request->input('match_mode');
        $nameMatches = $this->findStudentsByIdpName($profile);
        $idpEmail = strtolower(trim((string) ($profile['email'] ?? '')));

        if ($mode === 'single') {
            // Re-check the unique name match on the server; never trust a posted user_id.
            if ($nameMatches->count() !== 1) {
                return redirect()->route('student.idp.link')->withErrors([
                    'registration' => 'The matching student records changed. Please review the updated result.',
                ]);
            }
            $student = $nameMatches->first();
            $request->validate([
                'privacy_acknowledged' => ['accepted'],
            ], [
                'privacy_acknowledged.accepted' => 'Please acknowledge the PUPTracker Privacy Statement to continue.',
            ]);
            return $this->bindExistingStudent($request, $student, $idpSubject, $idpEmail, $profile);
        }

        if ($mode === 'duplicate') {
            if ($nameMatches->count() < 2) {
                return redirect()->route('student.idp.link')->withErrors([
                    'registration' => 'The duplicate-name results changed. Please review the updated result.',
                ]);
            }
            $validated = $request->validate([
                'student_number' => ['required', 'string', 'max:50'],
                'program_id' => ['required', 'integer', 'exists:program_tbl,program_id'],
                'section_id' => ['required', 'integer', 'exists:section_tbl,section_id'],
                'confirm_record' => ['accepted'],
                'privacy_acknowledged' => ['accepted'],
            ]);
            $candidates = $nameMatches->filter(function ($candidate) use ($validated) {
                $info = $candidate->studentInfo;
                return $info
                    && trim((string) $candidate->student_number) === trim((string) $validated['student_number'])
                    && (int) $info->program_id === (int) $validated['program_id']
                    && (int) $info->section_id === (int) $validated['section_id'];
            });

            if ($candidates->count() !== 1) {
                return back()->withInput()->withErrors([
                    'registration' => $candidates->isEmpty()
                        ? 'We could not verify a unique record using those details. Please check them or contact an administrator.'
                        : 'Those details still match more than one record. Please contact an administrator for assistance.',
                ]);
            }
            return $this->bindExistingStudent($request, $candidates->first(), $idpSubject, $idpEmail, $profile);
        }

        if ($mode !== 'new' || $nameMatches->isNotEmpty()) {
            return redirect()->route('student.idp.link')->withErrors([
                'registration' => 'The matching result changed. Please review the page and try again.',
            ]);
        }

        // No existing student with this full name: allow first-time registration.
        $validated = $request->validate([
            'student_number' => ['required', 'string', 'max:50'],
            'program_id' => ['required', 'integer', 'exists:program_tbl,program_id'],
            'year_id' => ['required', 'integer', 'exists:year_tbl,year_id'],
            'section_id' => ['required', 'integer', 'exists:section_tbl,section_id'],
            'gender_id' => ['required', 'integer', 'exists:gender_tbl,gender_id'],
            'privacy_acknowledged' => ['accepted'],
        ]);

        if (User::query()->where('student_number', trim($validated['student_number']))->exists()) {
            return back()->withInput()->withErrors([
                'student_number' => 'That student number is already registered. Please contact an administrator to link your existing record.',
            ]);
        }

        try {
            $student = DB::transaction(function () use ($validated, $profile, $idpSubject, $idpEmail) {
                $student = User::create([
                    'idp_subject' => $idpSubject,
                    'idp_email' => $idpEmail ?: null,
                    'email' => $idpEmail ?: null,
                    'idp_connected_at' => now(),
                    'student_number' => trim($validated['student_number']),
                    'gender_id' => (int) $validated['gender_id'],
                    'status_id' => $this->defaultStudentStatusId(),
                    'roles_id' => 2,
                    'password_hash' => '',
                    'first_name' => trim((string) ($profile['first_name'] ?? '')) ?: null,
                    'middle_name' => trim((string) ($profile['middle_name'] ?? '')) ?: null,
                    'last_name' => trim((string) ($profile['last_name'] ?? '')) ?: null,
                    'name_suffix' => trim((string) ($profile['suffix'] ?? '')) ?: null,
                    'profile_completed_at' => now(),
                ]);
                $this->createStudentInfo(
                    $student,
                    (int) $validated['program_id'],
                    (int) $validated['year_id'],
                    (int) $validated['section_id'],
                    0
                );
                return $student;
            });
        } catch (\Throwable $e) {
            $this->logSecurityEvent('New student IDP registration failed', [
                'idp_subject' => $idpSubject,
                'error' => $e->getMessage(),
            ]);
            return back()->withInput()->withErrors([
                'registration' => 'We could not complete your registration. Please verify your information or contact an administrator.',
            ]);
        }

        return $this->completeRegistrationFor($request, $student);
    }

    /** Bind the verified IDP identity to one existing, unlinked student record. */
    protected function bindExistingStudent(Request $request, User $candidate, string $idpSubject, string $email, array $profile): RedirectResponse
    {
        try {
            $student = DB::transaction(function () use ($candidate, $idpSubject, $email, $profile) {
                $student = User::query()->whereKey($candidate->user_id)->lockForUpdate()->firstOrFail();
                if (! $this->userIsStudent($student) || ! $student->isActiveAccount()) {
                    throw new \RuntimeException('The selected student account is not eligible for linking.');
                }
                if (trim((string) ($student->idp_subject ?? '')) !== '') {
                    throw new \RuntimeException('This student record is already linked to an IDP identity.');
                }
                if (User::query()->where('idp_subject', $idpSubject)->where('user_id', '!=', $student->user_id)->exists()) {
                    throw new \RuntimeException('This IDP identity is already linked to another student record.');
                }
                $this->persistIdpBinding($student, $idpSubject, $email, $profile);
                $student->refresh();
                if ((string) $student->idp_subject !== $idpSubject) {
                    throw new \RuntimeException('The IDP identity could not be saved.');
                }
                $student->profile_completed_at = $student->profile_completed_at ?? now();
                $student->saveOrFail();
                $this->touchLastLogin($student);
                return $student;
            });
        } catch (\Throwable $e) {
            $this->logSecurityEvent('Existing student IDP linking failed', [
                'idp_subject' => $idpSubject,
                'user_id' => $candidate->user_id,
                'error' => $e->getMessage(),
            ]);
            return back()->withInput()->withErrors([
                'registration' => 'We could not link this student record. Please verify the information or contact an administrator.',
            ]);
        }
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
    protected function completeRegistrationFor(Request $request, User $student): \Illuminate\Http\RedirectResponse
    {
        // Never mark a profile complete unless this existing record has a
        // confirmed permanent IDP identity binding.
        if (trim((string) ($student->idp_subject ?? '')) === '') {
            Log::warning('Student IDP completion blocked: missing permanent IDP binding', [
                'user_id' => $student->user_id,
            ]);

            return redirect()->route('student.login')
                ->withErrors([
                    'login' => 'Your PUP IDP account is not yet linked to a student record. Please sign in again.',
                ]);
        }

        // A successfully linked existing student record is complete for the
        // Student Module profile gate. Do not change student or enrollment data.
        if (empty($student->profile_completed_at)) {
            $student->profile_completed_at = now();
            $student->save();
        }

        $request->session()->forget([
            'student.idp.pending_profile',
            'student.idp.pending_subject',
            'student.idp.pending_email',
            'student.idp.pending_student_number',
            'student.idp.pending_program_id',
            'student.idp.pending_year_id',
        ]);

Auth::guard('student')->login($student);
$request->session()->regenerate();

EnforceInactivityTimeout::startFreshActivityWindow($request);
        $request->session()->put('show_privacy_consent_modal', true);

        return redirect()->route('student.dashboard')
            ->with('success', 'Your existing student record has been linked successfully. You may now access PUPTracker.');
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
     * Persist only the IDP link fields on the existing PUPTracker student.
     *
     * PUPTracker remains authoritative for the preloaded student name,
     * student number, enrollment, gender, status, and disciplinary records.
     * The IDP contributes the permanent subject and the email address only.
     * Database errors deliberately propagate so linking cannot appear
     * successful when the binding was not saved.
     */
    protected function persistIdpBinding(User $user, string $idpSubject, string $email, array $profile = []): void
    {
        $idpSubject = trim($idpSubject);
        $email = strtolower(trim($email));

        if ($idpSubject === '') {
            throw new \InvalidArgumentException('The IDP subject cannot be empty.');
        }

        $wasFirstLink = trim((string) ($user->idp_subject ?? '')) === '';

        $changes = [
            'idp_subject' => $idpSubject,
            'idp_connected_at' => $wasFirstLink
                ? now()
                : ($user->idp_connected_at ?? now()),
        ];

        if ($email !== '') {
            // Store the verified IDP email in the account's primary email
            // field (used by existing PUPTracker features) and retain the
            // IDP-specific copy for identity/audit purposes.
            $changes['email'] = $email;
            $changes['idp_email'] = $email;
        }

        $user->forceFill($changes)->saveOrFail();
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