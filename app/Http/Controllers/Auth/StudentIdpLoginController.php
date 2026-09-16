<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
                // The IDP id is not linked to any local student yet. Store the
                // full authenticated IDP profile server-side and guide the
                // student through "Connect your PUPTracker Account". The IDP id
                // is never sent to the browser — it is referenced from session
                // only, and the link is completed after verifying the student
                // number and matching the IDP identity to the local record.
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
                    ->with('info', 'Your PUP IDP account was authenticated. Connect your PUPTracker student account to continue.');
            }

            // Security: prevent an IdP account from binding to a local student
            // that is already linked to a different IdP subject.
            if ((string) $user->idp_subject !== '' && (string) $user->idp_subject !== $idpSubject) {
                return redirect()->route('student.login')
                    ->withErrors([
                        'login' => 'This IDP account is not linked to the local student account.',
                    ]);
            }

            if ((int) $user->status_id !== 1) {
                return redirect()->route('student.login')
                    ->withErrors([
                        'login' => 'Your account has been deactivated. Please contact an administrator.',
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

        return view('student.idp-link', compact('profile'));
    }

    /**
     * Handle the submitted student number.
     *
     * This is the "Yes, Continue" step after the confirmation modal: we look up
     * the local student record, verify it is active and unbound, and compare the
     * authenticated IDP identity against the local name. On success the found
     * student (and programs/years for the preview) is staged in the session and
     * the student proceeds to the information preview. No IDP link is written
     * yet — that only happens on the final Submit.
     */
    public function linkAccount(Request $request)
    {
        $profile = $this->pendingProfile($request);

        if ($profile === null) {
            return redirect()->route('student.login')
                ->withErrors([
'login' => 'Your PUP IDP session has expired. Please sign in again.',
                ]);
        }

        $validated = $request->validate([
            'student_number' => ['required', 'string'],
        ]);

        $idpSubject = $profile['subject'];

        // The IDP id is already bound to someone else — block and log.
        $alreadyBound = User::query()->where('idp_subject', $idpSubject)->first();
        if ($alreadyBound) {
            $this->logSecurityEvent('IDP id already linked to another student', [
                'idp_subject' => $idpSubject,
                'attempted_student_number' => $validated['student_number'],
            ]);

            return redirect()->route('student.login')
                ->withErrors([
                    'login' => 'This PUP IDP account is already linked to another account. Please contact the administrator.',
                ]);
        }

        $student = User::query()
            ->where('student_number', $validated['student_number'])
            ->first();

        if (! $student) {
            return back()->withErrors([
                'student_number' => 'We could not find a student record with that student number. Please check and try again.',
            ]);
        }

        if ((int) $student->status_id !== 1) {
            return back()->withErrors([
                'student_number' => 'This student account is inactive. Please contact the administrator.',
            ]);
        }

        // The local student must not already be bound to a different IDP id.
        if ((string) $student->idp_subject !== '' && (string) $student->idp_subject !== $idpSubject) {
            $this->logSecurityEvent('Attempt to overwrite existing IDP link', [
                'idp_subject' => $idpSubject,
                'student_number' => $student->student_number,
                'existing_subject' => (string) $student->idp_subject,
            ]);

            return back()->withErrors([
                'student_number' => 'This student account is already linked to a different PUP IDP account.',
            ]);
        }

        // Security gate: compare the authenticated IDP name against the local
        // record's name. A significant mismatch means we must NOT auto-link —
        // this prevents someone who knows another student's number from
        // attaching their own IDP account to that student's record.
        if (! $this->idpIdentityMatchesLocal($profile, $student)) {
            $this->logSecurityEvent('IDP identity does not match local student record', [
                'idp_subject' => $idpSubject,
                'student_number' => $student->student_number,
                'idp_name' => $this->displayName($profile),
                'local_name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
            ]);

            return back()->withErrors([
                'student_number' => 'Information mismatch: the name on your PUP IDP account does not match this student record. Please contact the administrator to complete linking.',
            ]);
        }

        // Stage the verified student for the preview. Still nothing is saved.
        $request->session()->put('student.idp.pending_student_number', $student->student_number);

        return redirect()->route('student.idp.link.preview');
    }

    /**
     * Show the information preview ("Verify / Connect your PUPTracker account").
     *
     * Identity details come from the authenticated IDP profile; academic details
     * (student number, program, year) come from the found local record. All
     * fields are read-only by default. The student may unlock ONLY Program and
     * Year for correction (options come from the DB dropdowns), then Apply to
     * review, then Submit to establish the permanent link.
     */
    public function showPreview(Request $request)
    {
        $profile = $this->pendingProfile($request);
        $studentNumber = (string) $request->session()->get('student.idp.pending_student_number', '');

        if ($profile === null || $studentNumber === '') {
            return redirect()->route('student.idp.link')
                ->withErrors([
                    'student_number' => 'Your PUP IDP session has expired. Please start again.',
                ]);
        }

        $student = User::query()
            ->with('studentInfo.program', 'studentInfo.year')
            ->where('student_number', $studentNumber)
            ->first();

        if (! $student) {
            return redirect()->route('student.idp.link')
                ->withErrors([
                    'student_number' => 'We could not find the student record. Please start again.',
                ]);
        }

        $programs = \App\Models\Program::query()->orderBy('program_name')->get();
        $years = \App\Models\Year::query()->orderBy('year')->get();

        // Any program/year the student picked during a prior Apply is remembered.
        $pendingProgramId = $request->session()->get('student.idp.pending_program_id');
        $pendingYearId = $request->session()->get('student.idp.pending_year_id');
        $pendingLadderized = $request->session()->get('student.idp.pending_ladderized');

        return view('student.idp-link-preview', compact(
            'profile',
            'student',
            'programs',
            'years',
            'pendingProgramId',
            'pendingYearId',
            'pendingLadderized'
        ));
    }

    /**
     * Programs that offer a Ladderized track (BSOA / BSIT).
     *
     * Shared with the Admin screens and the graduating-report filter through the
     * SAME config key, so the three can never disagree about which programs are
     * ladderizable.
     *
     * @return array<int, string> upper-cased program names
     */
    protected function ladderizedPrograms(): array
    {
        $configured = (array) config(
            'graduation_review.graduating.ladderized_programs',
            ['BSOA', 'BSIT']
        );

        return array_values(array_filter(array_map(
            static fn ($name): string => strtoupper(trim((string) $name)),
            $configured
        )));
    }

    /**
     * Does this program offer the Ladderized option?
     *
     * Matched case-insensitively against the configured list, and ALSO accepted
     * when the stored name merely CONTAINS the code ("BS Information
     * Technology" still counts as BSIT), because program names are entered by
     * hand on the Admin screens and both spellings exist in the data.
     */
    protected function programSupportsLadderized(?string $programName): bool
    {
        $name = strtoupper(trim((string) $programName));

        if ($name === '') {
            return false;
        }

        foreach ($this->ladderizedPrograms() as $code) {
            if ($name === $code || str_contains($name, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apply the student's program/year corrections (from DB dropdowns).
     *
     * This only stages the selection — nothing is saved to the database and the
     * relationship is not established. The student returns to the preview which
     * now shows the final "Review your information" state with [Edit] and
     * [Submit].
     */
    public function updateProgramYear(Request $request)
    {
        $profile = $this->pendingProfile($request);
        $studentNumber = (string) $request->session()->get('student.idp.pending_student_number', '');

        if ($profile === null || $studentNumber === '') {
            return redirect()->route('student.idp.link');
        }

        $validated = $request->validate([
            'program_id' => ['nullable', 'integer'],
            'year_id' => ['nullable', 'integer'],
            'ladderized' => ['nullable', 'in:0,1'],
        ]);

        if ($request->filled('program_id')) {
            $request->session()->put('student.idp.pending_program_id', (int) $validated['program_id']);
        }
        if ($request->filled('year_id')) {
            $request->session()->put('student.idp.pending_year_id', (int) $validated['year_id']);
        }

        // Ladderized is only meaningful for the ladderizable programs (BSOA /
        // BSIT). For any other program the choice is CLEARED rather than kept,
        // so switching from BSIT to a non-ladderized program cannot leave a
        // stale "ladderized" flag that would later appear in the graduating
        // report filter.
        $effectiveProgramId = $request->filled('program_id')
            ? (int) $validated['program_id']
            : $request->session()->get('student.idp.pending_program_id');

        if ($this->programSupportsLadderized($this->programNameFor($effectiveProgramId))) {
            $request->session()->put(
                'student.idp.pending_ladderized',
                (int) ($validated['ladderized'] ?? 0)
            );
        } else {
            $request->session()->forget('student.idp.pending_ladderized');
        }

        return redirect()->route('student.idp.link.preview');
    }

    /**
     * The stored program name for an id, or null when the id is empty/unknown.
     */
    protected function programNameFor($programId): ?string
    {
        if ($programId === null || $programId === '') {
            return null;
        }

        return \App\Models\Program::query()->whereKey((int) $programId)->value('program_name');
    }

    /**
     * Final Submit — establish the permanent IDP link.
     *
     * Only now does the system write the association:
     *   idp_subject (permanent), idp_email (refreshed), idp_connected_at.
     * If program/year were legitimately corrected, those are applied to the
     * student's student_info record too. Then the student is logged in.
     */
    public function submitLink(Request $request)
    {
        $profile = $this->pendingProfile($request);
        $studentNumber = (string) $request->session()->get('student.idp.pending_student_number', '');

        if ($profile === null || $studentNumber === '') {
            return redirect()->route('student.login')
                ->withErrors([
                    'login' => 'Your PUP IDP session has expired. Please sign in again.',
                ]);
        }

        $idpSubject = $profile['subject'];

        // Re-verify the IDP id is not bound to a DIFFERENT student than the one
        // being linked (defence in depth; the student number comes from session).
        $alreadyBound = User::query()->where('idp_subject', $idpSubject)->first();
        if (
            $alreadyBound
            && (string) $alreadyBound->student_number !== $studentNumber
        ) {
            $this->logSecurityEvent('IDP id already linked to another student on submit', [
                'idp_subject' => $idpSubject,
                'attempted_student_number' => $studentNumber,
            ]);

            return redirect()->route('student.login')
                ->withErrors([
                    'login' => 'This PUP IDP account is already linked to another account. Please contact the administrator.',
                ]);
        }

        $student = User::query()
            ->where('student_number', $studentNumber)
            ->first();

        if (! $student || (int) $student->status_id !== 1) {
            return redirect()->route('student.login')
                ->withErrors([
                    'login' => 'We could not verify the student record. Please sign in again.',
                ]);
        }

        if (! $this->idpIdentityMatchesLocal($profile, $student)) {
            $this->logSecurityEvent('IDP identity mismatch on submit', [
                'idp_subject' => $idpSubject,
                'student_number' => $student->student_number,
            ]);

            return redirect()->route('student.login')
                ->withErrors([
                    'login' => 'Information mismatch with this student record. Please contact the administrator.',
                ]);
        }

        $wasFirstLink = (string) ($student->idp_subject ?? '') === '';

        $student->forceFill([
            'idp_subject' => $idpSubject,
            'idp_email' => $profile['email'] !== '' ? $profile['email'] : $student->idp_email,
            'idp_connected_at' => $wasFirstLink ? now() : ($student->idp_connected_at ?? now()),
        ])->save();

        // The IDP is the authoritative source for identity/name. Copy the
        // authenticated IDP name fields (first/middle/last/name_suffix) into
        // the local record on this first link (they may have been blank).
        $this->syncIdentityFromIdp($student->fresh(), $profile);

        // Apply program/year corrections if the student chose them and they are
        // valid options (guarded — student_info/program/year tables may not exist
        // in every environment, and a bad id must never be written).
        $programId = $request->session()->get('student.idp.pending_program_id');
        $yearId = $request->session()->get('student.idp.pending_year_id');
        $ladderized = $request->session()->get('student.idp.pending_ladderized');
        $this->applyProgramYearCorrections($student->fresh(), $programId, $yearId, $ladderized);

        // Clear the pending flow and log the student in.
        $request->session()->forget([
            'student.idp.pending_profile',
            'student.idp.pending_subject',
            'student.idp.pending_email',
            'student.idp.pending_student_number',
            'student.idp.pending_program_id',
            'student.idp.pending_year_id',
            'student.idp.pending_ladderized',
        ]);

        Auth::guard('student')->login($student);
        $request->session()->regenerate();
        $request->session()->put('show_privacy_consent_modal', true);

        return redirect()->route('student.dashboard');
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
     * Security gate: does the authenticated IDP identity match the local record?
     *
     * The IDP is the authoritative source for identity/name. Before a student's
     * first IDP login their local first/last name may be NULL/blank (the student
     * record is located by student number; names are populated from the IDP
     * during first linking).
     *
     * Rules:
     *   - When the LOCAL record has no first/last name yet → allow (the IDP will
     *     populate them). There is no existing name to conflict with.
     *   - When the LOCAL record HAS a first/last name → it must match the IDP
     *     (case/space-insensitive), otherwise block. This prevents someone who
     *     knows another student's number from attaching their own IDP account to
     *     a record that already carries a different person's name.
     *   - Middle names must agree only when both are provided.
     */
    protected function idpIdentityMatchesLocal(array $profile, User $student): bool
    {
        $first = $this->normalizeNamePart((string) ($profile['first_name'] ?? ''));
        $last = $this->normalizeNamePart((string) ($profile['last_name'] ?? ''));
        $middle = $this->normalizeNamePart((string) ($profile['middle_name'] ?? ''));

        $localFirst = $this->normalizeNamePart((string) $student->first_name);
        $localMiddle = $this->normalizeNamePart((string) $student->middle_name);
        $localLast = $this->normalizeNamePart((string) $student->last_name);

        // The IDP must always carry at least a first + last name to populate.
        if ($first === '' || $last === '') {
            return false;
        }

        // Local record not yet named → nothing to compare against; linking will
        // populate the name from the authoritative IDP source.
        if ($localFirst === '' && $localLast === '') {
            return true;
        }

        // Local record already named → require first+last to agree. If one side
        // is blank and the other is not, that is a conflict (defensive).
        if ($first !== $localFirst || $last !== $localLast) {
            return false;
        }

        // Middle names must agree only when both are provided.
        if ($middle !== '' && $localMiddle !== '' && $middle !== $localMiddle) {
            return false;
        }

        return true;
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
     * Apply the student's chosen program/year/ladderized corrections, if valid.
     *
     * The Ladderized flag is written ONLY when the (effective) program actually
     * offers it, so a forged request cannot mark a non-ladderized program as
     * ladderized. When the program does offer it, the flag is always written
     * (1 or 0) so the student's explicit "No" is persisted rather than the
     * column silently keeping an old value.
     *
     * @param  \App\Models\User  $student
     * @param  int|null  $programId
     * @param  int|null  $yearId
     * @param  int|null  $ladderized  null = not chosen
     */
    protected function applyProgramYearCorrections(User $student, $programId, $yearId, $ladderized = null): void
    {
        try {
            $studentInfo = $student->studentInfo;

            if (! $studentInfo) {
                return;
            }

            $programValid = $programId !== null
                && \App\Models\Program::query()->whereKey((int) $programId)->exists();
            $yearValid = $yearId !== null
                && \App\Models\Year::query()->whereKey((int) $yearId)->exists();

            $changes = [];

            if ($programValid) {
                $changes['program_id'] = (int) $programId;
            }
            if ($yearValid) {
                $changes['year_id'] = (int) $yearId;
            }

            // Ladderized is keyed off the EFFECTIVE program — the one being
            // applied, or the one already on the record when none was chosen.
            $effectiveProgramId = $changes['program_id'] ?? $studentInfo->program_id;
            $effectiveProgramName = $this->programNameFor($effectiveProgramId);

            if ($this->programSupportsLadderized($effectiveProgramName)
                && \Illuminate\Support\Facades\Schema::hasColumn('student_info_tbl', 'ladderized')) {
                $changes['ladderized'] = (int) ($ladderized ?? 0);
            }

            if ($changes !== []) {
                $studentInfo->update($changes);
            }
        } catch (\Throwable $e) {
            Log::warning('Could not apply program/year corrections', ['error' => $e->getMessage()]);
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