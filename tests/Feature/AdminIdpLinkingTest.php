<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminIdpLinkingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTablesForTests();
        $this->configureIdp();
    }

    private function configureIdp(): void
    {
        Config::set('services.idp.base_url', 'https://idp.test/api/v1');
        Config::set('services.idp.client_id', 'student-client');
        Config::set('services.idp.client_secret', 'student-secret');
        Config::set('services.idp.redirect_uri', 'https://puptvs.com/student/idp/callback');
        Config::set('services.idp.timeout', 15);
        Config::set('services.idp.student_roles', 'student');
    }

    private function fakeIdp(
        string $idpSubject = 'user-123',
        string $email = 'student.idp@example.com',
        string $roles = 'student',
        array $profileOverrides = []
    ): void {
        Http::fake([
            'https://idp.test/api/v1/auth/token' => Http::response([
                'access_token' => 'access-token-123',
                'refresh_token' => 'refresh-token-123',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
            'https://idp.test/api/v1/me' => Http::response(array_merge([
                'id' => $idpSubject,
                'email' => $email,
                'first_name' => 'IDP',
                'middle_name' => null,
                'last_name' => 'Student',
                'name_suffix' => null,
                'roles' => $roles,
            ], $profileOverrides), 200),
        ]);
    }

    private function createAdmin(): Admin
    {
        return Admin::create([
            'username' => 'it.admin',
            'email' => 'it.admin@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    private function createStudent(array $overrides = []): User
    {
        $student = User::create(array_merge([
            'student_number' => '2026-00003',
            'first_name' => 'IDP',
            'last_name' => 'Student',
            'email' => 'student.idp@example.com',
            'idp_subject' => null,
            'idp_email' => null,
            'status_id' => 1,
            'roles_id' => 2,
            'password_hash' => Hash::make('password123'),
            'mfa_totp_enabled' => false,
        ], $overrides));

        // Attach academic info (program/year) used by the info preview when the
        // student_info_tbl table exists.
        if (Schema::hasTable('student_info_tbl')) {
            $infoOverrides = [
                'user_id' => $student->user_id,
                'program_id' => 1,
                'year_id' => 1,
                'student_status_id' => 1,
            ];
            $student->studentInfo()->updateOrCreate(
                ['user_id' => $student->user_id],
                $infoOverrides
            );
        }

        return $student;
    }

    private function hitCallback(string $code = 'auth-code-123', ?string $state = null): TestResponse
    {
        $session = ['student.idp.state' => 'state-123'];
        $params = ['code' => $code];
        if ($state !== null) {
            $params['state'] = $state;
        }

        return $this->withSession($session)->get(route('student.idp.callback', $params));
    }

    // ------------------------------------------------------------------
    // 1. Existing linked IDP account logs in successfully
    // ------------------------------------------------------------------

    public function test_existing_linked_idp_account_logs_in_successfully(): void
    {
        $this->createStudent(['idp_subject' => 'user-linked', 'idp_email' => 'linked@example.com']);
        $this->fakeIdp(idpSubject: 'user-linked', email: 'linked@example.com');

        $this->hitCallback()->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated('student');
    }

    // ------------------------------------------------------------------
    // 2. Unlinked IDP account is rejected
    // ------------------------------------------------------------------

    public function test_unlinked_idp_account_is_rejected(): void
    {
        // Local student email does NOT match the IDP email, so no safe
        // auto-link occurs. The student is NOT logged in; they are sent to the
        // secure self-linking step instead.
        $this->createStudent(['email' => 'different@example.com']);
        $this->fakeIdp(idpSubject: 'user-unknown', email: 'nobody@example.com');

        $response = $this->hitCallback();
        $response->assertRedirect(route('student.idp.link'));
        $this->assertGuest('student');
    }

    // ------------------------------------------------------------------
    // 3. Administrator can link an IDP Account ID to a student
    // ------------------------------------------------------------------

    public function test_admin_can_link_idp_account_to_student(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent();

        $this->actingAs($admin, 'admin');

        $this->post(route('admin.students.link-idp', $student->student_number), [
            'idp_subject' => 'ABC123',
            'idp_email' => 'student@student.pup.edu.ph',
        ])->assertRedirect();

        $this->assertDatabaseHas('users_tbl', [
            'user_id' => $student->user_id,
            'idp_subject' => 'ABC123',
            'idp_email' => 'student@student.pup.edu.ph',
        ]);
        $this->assertNotNull($student->fresh()->idp_connected_at);

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
            'action' => 'Link IDP Account',
            'module' => 'Students',
        ]);
    }

    // ------------------------------------------------------------------
    // 4. Duplicate IDP Account IDs are rejected
    // ------------------------------------------------------------------

    public function test_duplicate_idp_account_id_is_rejected(): void
    {
        $admin = $this->createAdmin();
        $this->createStudent([
            'student_number' => '2026-00001',
            'idp_subject' => 'ABC123',
            'email' => 'one@example.com',
        ]);
        $other = $this->createStudent([
            'student_number' => '2026-00002',
            'email' => 'two@example.com',
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->post(route('admin.students.link-idp', $other->student_number), [
            'idp_subject' => 'ABC123',
        ]);

        $response->assertSessionHasErrors('idp_subject');
        $this->assertSame(null, $other->fresh()->idp_subject);
    }

    // ------------------------------------------------------------------
    // 5. After linking, the student can log in using PUP IDP
    // ------------------------------------------------------------------

    public function test_after_linking_student_can_log_in_with_pup_id(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent();

        // Link via admin
        $this->actingAs($admin, 'admin');
        $this->post(route('admin.students.link-idp', $student->student_number), [
            'idp_subject' => 'user-linked',
            'idp_email' => 'student.idp@example.com',
        ])->assertRedirect();

        // Now log in via IDP
        $this->fakeIdp(idpSubject: 'user-linked', email: 'student.idp@example.com');
        $this->hitCallback()->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated('student');
    }

    // ------------------------------------------------------------------
    // 6. Changing IDP email does not change linked local student
    // ------------------------------------------------------------------

    public function test_changing_idp_email_keeps_same_linked_student(): void
    {
        $this->createStudent([
            'idp_subject' => 'ABC123',
            'idp_email' => 'old@student.pup.edu.ph',
        ]);

        // IDP now returns the same subject but a new email.
        $this->fakeIdp(idpSubject: 'ABC123', email: 'new@student.pup.edu.ph');

        $this->hitCallback()->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated('student');

        $this->assertDatabaseHas('users_tbl', [
            'idp_subject' => 'ABC123',
            'idp_email' => 'new@student.pup.edu.ph',
        ]);
        $this->assertSame(1, User::count());
    }

    // ------------------------------------------------------------------
    // 7. Inactive local student cannot log in even with valid IDP account
    // ------------------------------------------------------------------

    public function test_inactive_local_student_cannot_log_in_with_valid_idp(): void
    {
        $this->createStudent([
            'idp_subject' => 'user-inactive',
            'idp_email' => 'inactive@example.com',
            'status_id' => 2,
        ]);
        $this->fakeIdp(idpSubject: 'user-inactive', email: 'inactive@example.com');

        $response = $this->hitCallback();
        $response->assertRedirect(route('student.login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest('student');
    }

    // ------------------------------------------------------------------
    // 8. Admin unlink
    // ------------------------------------------------------------------

    public function test_admin_can_unlink_idp_account(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent([
            'idp_subject' => 'ABC123',
            'idp_email' => 'student.idp@example.com',
        ]);

        $this->actingAs($admin, 'admin');

        $this->post(route('admin.students.unlink-idp', $student->student_number))
            ->assertRedirect();

        $this->assertDatabaseHas('users_tbl', [
            'user_id' => $student->user_id,
            'idp_subject' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
            'action' => 'Unlink IDP Account',
            'module' => 'Students',
        ]);
    }

    // ------------------------------------------------------------------
    // 9. Only admins can link (student cannot)
    // ------------------------------------------------------------------

    public function test_student_cannot_link_idp_account(): void
    {
        $student = $this->createStudent();

        // Acting as a student (not an admin) — the route is admin-only. The
        // auth:admin middleware redirects non-admin users away (302), so the
        // student can never submit an IDP Account ID.
        $this->actingAs($student, 'student');

        $response = $this->post(route('admin.students.link-idp', $student->student_number), [
            'idp_subject' => 'ABC123',
        ]);
        $response->assertRedirect();
        $this->assertGuest('admin');

        $this->assertSame(null, $student->fresh()->idp_subject);
    }

    // ------------------------------------------------------------------
    // ------------------------------------------------------------------
    // FIRST-TIME STUDENT REGISTRATION (self-registration).
    //
    // PUPTracker has NO pre-created student-number roster. A successfully
    // authenticated IDP identity is enough to register: the student number,
    // program and year are COLLECTED from the student during registration.
    // Identity (name / email / idp_subject) always comes from the verified
    // server-side IDP session, never from the request.
    // ------------------------------------------------------------------

    private function pendingProfileSession(
        string $subject = 'user-new',
        string $email = 'student.idp@example.com',
        array $profileOverrides = []
    ): array {
        return [
            'student.idp.pending_profile' => array_merge([
                'subject' => $subject,
                'email' => $email,
                'first_name' => 'IDP',
                'middle_name' => '',
                'last_name' => 'Student',
                'suffix' => '',
            ], $profileOverrides),
            // backward-compatible aliases
            'student.idp.pending_subject' => $subject,
            'student.idp.pending_email' => $email,
        ];
    }

    /** Valid registration payload with sensible defaults. */
    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'student_number' => '2026-00003',
            'program_id' => 1,
            'year_id' => 1,
            'privacy_acknowledged' => '1',
        ], $overrides);
    }

    public function test_unlinked_student_is_redirected_to_registration_form(): void
    {
        // Even when the local email matches, an unlinked IDP user is NOT
        // auto-linked — they are always routed to first-time registration.
        $this->createStudent(['email' => 'student.idp@example.com']);
        $this->fakeIdp(idpSubject: 'user-new', email: 'student.idp@example.com');

        $response = $this->hitCallback();
        $response->assertRedirect(route('student.idp.link'));
        $this->assertGuest('student');
    }

    // ---- 1. register page access -------------------------------------

    public function test_registration_page_requires_pending_idp_session(): void
    {
        // No pending session -> back to IDP login. Direct access cannot register.
        $response = $this->get(route('student.idp.link'));
        $response->assertRedirect(route('student.idp.login'));
        $this->assertSame(0, User::count());
    }

    public function test_registration_page_renders_for_first_time_idp_user(): void
    {
        $response = $this->withSession($this->pendingProfileSession())
            ->get(route('student.idp.link'));

        $response->assertOk();
        $response->assertSee('Complete Your PUPTracker Registration', false);
        $response->assertSee('PUPTracker information', false);
        $response->assertSee('Information Privacy Notice', false);
    }

    // ---- 2/3/4. IDP identity auto-populated -------------------------

    public function test_registration_page_auto_populates_idp_name_and_email(): void
    {
        $response = $this->withSession($this->pendingProfileSession(
            email: 'juan.delacruz@university.edu',
            profileOverrides: ['first_name' => 'Juan', 'middle_name' => 'Dela', 'last_name' => 'Cruz'],
        ))->get(route('student.idp.link'));

        $response->assertOk();
        $response->assertSee('Juan Dela Cruz', false);
        $response->assertSee('juan.delacruz@university.edu', false);
    }

    public function test_registration_page_does_not_expose_idp_subject(): void
    {
        $response = $this->withSession($this->pendingProfileSession('secret-subject-xyz'))
            ->get(route('student.idp.link'));

        $response->assertOk();
        $response->assertDontSee('secret-subject-xyz', false);
    }

    // ---- 10. student number need not pre-exist -----------------------

    public function test_first_time_student_can_register_without_preexisting_record(): void
    {
        // NO student record exists for this number at all.
        $this->assertSame(0, User::count());

        $response = $this->withSession($this->pendingProfileSession())
            ->post(route('student.idp.link.post'), $this->registrationPayload());

        $response->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated('student');

        $this->assertDatabaseHas('users_tbl', [
            'student_number' => '2026-00003',
            'idp_subject' => 'user-new',
            'idp_email' => 'student.idp@example.com',
            'roles_id' => 2,
        ]);
    }

    // ---- 5/6. identity cannot be forged via the request --------------

    public function test_idp_name_cannot_be_changed_by_manipulating_request(): void
    {
        $this->withSession($this->pendingProfileSession(profileOverrides: [
            'first_name' => 'Juan', 'middle_name' => 'Dela', 'last_name' => 'Cruz', 'suffix' => '',
        ]))->post(route('student.idp.link.post'), $this->registrationPayload([
            // Attacker-supplied identity fields — must be ignored.
            'first_name' => 'Impostor',
            'last_name' => 'Hacker',
            'name_suffix' => 'Sr.',
        ]))->assertRedirect(route('student.dashboard'));

        $this->assertDatabaseHas('users_tbl', [
            'student_number' => '2026-00003',
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz',
        ]);
        $this->assertDatabaseMissing('users_tbl', ['first_name' => 'Impostor']);
    }

    public function test_idp_email_cannot_be_changed_by_manipulating_request(): void
    {
        $this->withSession($this->pendingProfileSession(email: 'verified@university.edu'))
            ->post(route('student.idp.link.post'), $this->registrationPayload([
                'email' => 'attacker@evil.test',
                'idp_email' => 'attacker@evil.test',
                'idp_subject' => 'forged-subject',
            ]))->assertRedirect(route('student.dashboard'));

        $this->assertDatabaseHas('users_tbl', [
            'student_number' => '2026-00003',
            'idp_email' => 'verified@university.edu',
            'idp_subject' => 'user-new',
        ]);
        $this->assertDatabaseMissing('users_tbl', ['idp_subject' => 'forged-subject']);
    }

    // ---- 7/8/9. student-provided academic fields ---------------------

    public function test_student_provided_program_and_year_are_saved(): void
    {
        $this->withSession($this->pendingProfileSession())
            ->post(route('student.idp.link.post'), $this->registrationPayload([
                'program_id' => 2,
                'year_id' => 2,
            ]))->assertRedirect(route('student.dashboard'));

        $student = User::where('student_number', '2026-00003')->firstOrFail();

        $this->assertDatabaseHas('student_info_tbl', [
            'user_id' => $student->user_id,
            'program_id' => 2,
            'year_id' => 2,
        ]);
    }

    // ---- 14. duplicate IDP identity ---------------------------------

    public function test_duplicate_idp_identity_does_not_create_second_account(): void
    {
        // The IDP identity already owns an account.
        $existing = $this->createStudent([
            'student_number' => '2026-00001',
            'idp_subject' => 'user-bound',
            'idp_email' => 'bound@example.com',
        ]);

        $this->withSession($this->pendingProfileSession('user-bound', 'bound@example.com'))
            ->post(route('student.idp.link.post'), $this->registrationPayload([
                'student_number' => '2026-00099',
            ]));

        // Still exactly one account, and the existing one was not rewritten.
        $this->assertSame(1, User::count());
        $this->assertSame('2026-00001', $existing->fresh()->student_number);
        $this->assertDatabaseMissing('users_tbl', ['student_number' => '2026-00099']);
    }

    // ---- 15. duplicate student number --------------------------------

    public function test_duplicate_student_number_is_handled(): void
    {
        // A DIFFERENT account already uses this number.
        $this->createStudent([
            'student_number' => '2026-00003',
            'idp_subject' => 'someone-else',
        ]);

        $response = $this->withSession($this->pendingProfileSession())
            ->post(route('student.idp.link.post'), $this->registrationPayload([
                'student_number' => '2026-00003',
            ]));

        $response->assertSessionHasErrors('student_number');
        $this->assertGuest('student');

        // No second account, and the pre-existing account keeps its own IDP link.
        $this->assertSame(1, User::count());
        $this->assertSame(0, User::where('idp_subject', 'user-new')->count());
        $this->assertSame('someone-else', User::where('student_number', '2026-00003')->firstOrFail()->idp_subject);
    }

    // ---- 16. double submission --------------------------------------

    public function test_double_submission_creates_only_one_account(): void
    {
        $session = $this->pendingProfileSession();

        $this->withSession($session)
            ->post(route('student.idp.link.post'), $this->registrationPayload())
            ->assertRedirect(route('student.dashboard'));

        // Replay the exact same submission.
        $this->withSession($session)
            ->post(route('student.idp.link.post'), $this->registrationPayload())
            ->assertRedirect(route('student.dashboard'));

        $this->assertSame(1, User::count());
        $this->assertSame(1, User::where('idp_subject', 'user-new')->count());
    }

    // ---- 13. missing IDP session cannot submit -----------------------

    public function test_missing_idp_session_cannot_submit_registration(): void
    {
        $response = $this->post(route('student.idp.link.post'), $this->registrationPayload());

        $response->assertRedirect(route('student.login'));
        $response->assertSessionHasErrors('login');
        $this->assertSame(0, User::count());
        $this->assertGuest('student');
    }

    // ---- 18. invalid input ------------------------------------------

    public function test_registration_rejects_invalid_input(): void
    {
        $session = $this->pendingProfileSession();

        // All required fields missing / invalid.
        $this->withSession($session)
            ->post(route('student.idp.link.post'), [])
            ->assertSessionHasErrors(['student_number', 'program_id', 'year_id', 'privacy_acknowledged']);

        // Unknown program / year ids must fail validation.
        $this->withSession($session)
            ->post(route('student.idp.link.post'), $this->registrationPayload([
                'program_id' => 999,
                'year_id' => 999,
            ]))
            ->assertSessionHasErrors(['program_id', 'year_id']);

        $this->assertSame(0, User::count());
        $this->assertGuest('student');
    }

    public function test_registration_requires_privacy_notice_acknowledgement(): void
    {
        $this->withSession($this->pendingProfileSession())
            ->post(route('student.idp.link.post'), [
                'student_number' => '2026-00003',
                'program_id' => 1,
                'year_id' => 1,
                // no privacy_acknowledged
            ])
            ->assertSessionHasErrors('privacy_acknowledged');

        $this->assertSame(0, User::count());
    }

    // ---- 17. CSRF ----------------------------------------------------

    public function test_registration_submission_is_csrf_protected(): void
    {
        // The POST route must sit in the `web` group, which is what carries CSRF.
        $route = app(\Illuminate\Routing\Router::class)->getRoutes()->getByName('student.idp.link.post');

        $this->assertNotNull($route, 'The registration POST route must be registered.');
        $this->assertContains('web', $route->gatherMiddleware());

        // Boot the HTTP kernel so the framework's default middleware groups are
        // registered, then confirm CSRF verification is part of `web`.
        $this->app->make(\Illuminate\Contracts\Http\Kernel::class)->bootstrap();

        $groups = app(\Illuminate\Routing\Router::class)->getMiddlewareGroups();

        $this->assertContains(
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            $groups['web'] ?? [],
            'The web middleware group must keep ValidateCsrfToken so registration POSTs are CSRF-protected.'
        );

        // The rendered form carries a token.
        $page = $this->withSession($this->pendingProfileSession())
            ->get(route('student.idp.link'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/name="_token"\s+value="[^"]+"/', $page);
    }

    // ---- 19. rollback / no partial account ---------------------------

    public function test_registration_rolls_back_when_info_creation_fails(): void
    {
        // Force the enrollment insert to fail AFTER the user row is created.
        Schema::drop('student_info_tbl');
        Schema::create('student_info_tbl', function (Blueprint $table): void {
            $table->id('student_id');
            $table->unsignedBigInteger('user_id')->nullable();
            // `program_id` is deliberately absent -> the enrollment insert throws.
            $table->unsignedBigInteger('year_id')->nullable();
            $table->unsignedBigInteger('section_id')->nullable();
            $table->unsignedBigInteger('student_status_id')->nullable();
            $table->tinyInteger('ladderized')->default(0);
        });

        $response = $this->withSession($this->pendingProfileSession())
            ->post(route('student.idp.link.post'), $this->registrationPayload());

        $response->assertSessionHasErrors('registration');
        $this->assertSame(0, User::count(), 'A partial account must not survive a failed registration.');
        $this->assertGuest('student');
    }

    // ---- 20. existing functionality ---------------------------------

    public function test_existing_linked_student_bypasses_registration_entirely(): void
    {
        $this->createStudent([
            'student_number' => '2026-00001',
            'idp_subject' => 'user-linked',
            'idp_email' => 'linked@example.com',
        ]);

        $this->fakeIdp(idpSubject: 'user-linked', email: 'linked@example.com');

        // Straight to the dashboard — never through registration.
        $this->hitCallback()->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated('student');
        $this->assertSame(1, User::count());
    }

    public function test_subsequent_login_refreshes_name_from_idp(): void
    {
        // Already linked with an older local name snapshot.
        $this->createStudent([
            'idp_subject' => 'user-linked',
            'idp_email' => 'student.idp@example.com',
            'first_name' => 'Old',
            'last_name' => 'Name',
            'name_suffix' => null,
        ]);
        // The IDP now returns an updated (authoritative) name.
        $this->fakeIdp(
            idpSubject: 'user-linked',
            email: 'student.idp@example.com',
            profileOverrides: [
                'first_name' => 'Juan',
                'middle_name' => 'Dela Cruz',
                'last_name' => 'Santos',
                'name_suffix' => 'III',
            ]
        );

        $this->hitCallback()->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated('student');

        $this->assertDatabaseHas('users_tbl', [
            'user_id' => User::where('idp_subject', 'user-linked')->first()->user_id,
            'idp_subject' => 'user-linked',
            'first_name' => 'Juan',
            'middle_name' => 'Dela Cruz',
            'last_name' => 'Santos',
            'name_suffix' => 'III',
        ]);
    }

    public function test_full_name_and_formal_name_accessors_include_suffix(): void
    {
        $student = $this->createStudent([
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz',
            'name_suffix' => 'Jr.',
        ]);

        $this->assertSame('Juan Dela Cruz Jr.', $student->full_name);
        $this->assertSame('Cruz, Juan Dela, Jr.', $student->formal_name);
    }

    public function test_name_accessors_handle_blank_suffix_and_blank_names(): void
    {
        // No suffix, middle name blank.
        $student = $this->createStudent([
            'first_name' => 'Maria',
            'middle_name' => '',
            'last_name' => 'Santos',
            'name_suffix' => null,
        ]);
        $this->assertSame('Maria Santos', $student->full_name);
        $this->assertSame('Santos, Maria', $student->formal_name);

        // Blank names entirely (pre-first-IDP-login state).
        $blank = $this->createStudent([
            'student_number' => '2026-00004',
            'first_name' => null,
            'middle_name' => null,
            'last_name' => null,
            'name_suffix' => null,
        ]);
        $this->assertSame('', $blank->full_name);
        $this->assertSame('', $blank->formal_name);
    }
    private function ensureTablesForTests(): void
    {
        if (! Schema::hasTable('roles_tbl')) {
            Schema::create('roles_tbl', function (Blueprint $table): void {
                $table->id('roles_id');
                $table->string('roles_name')->nullable();
            });
            DB::table('roles_tbl')->insert([
                ['roles_id' => 1, 'roles_name' => 'Admin'],
                ['roles_id' => 2, 'roles_name' => 'Student'],
                ['roles_id' => 3, 'roles_name' => 'Security'],
            ]);
        }

        if (! Schema::hasTable('status_tbl')) {
            Schema::create('status_tbl', function (Blueprint $table): void {
                $table->id('status_id');
                $table->string('status_name')->nullable();
            });
            DB::table('status_tbl')->insert([
                ['status_id' => 1, 'status_name' => 'Active'],
                ['status_id' => 2, 'status_name' => 'Inactive'],
            ]);
        }

        if (! Schema::hasTable('users_tbl')) {
            Schema::create('users_tbl', function (Blueprint $table): void {
                $table->id('user_id');
                $table->string('student_number')->unique();
                $table->string('first_name')->nullable();
                $table->string('middle_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('name_suffix')->nullable();
                $table->string('email')->nullable();
                $table->string('idp_subject')->nullable()->unique();
                $table->string('idp_email')->nullable();
                $table->timestamp('idp_connected_at')->nullable();
                $table->timestamp('idp_last_login_at')->nullable();
                $table->timestamp('profile_completed_at')->nullable();
                $table->unsignedBigInteger('gender_id')->nullable();
                $table->unsignedBigInteger('status_id')->nullable();
                $table->unsignedBigInteger('roles_id')->nullable();
                $table->string('password_hash');
                $table->string('reset_token_hash')->nullable();
                $table->timestamp('reset_token_expires_at')->nullable();
                $table->timestamp('new_until')->nullable();
                $table->text('mfa_totp_secret')->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('program_tbl')) {
            Schema::create('program_tbl', function (Blueprint $table): void {
                $table->id('program_id');
                $table->string('program_name')->nullable();
                $table->string('degree_type')->nullable();
            });
            DB::table('program_tbl')->insert([
                ['program_id' => 1, 'program_name' => 'BSIT', 'degree_type' => null],
                ['program_id' => 2, 'program_name' => 'BSCS', 'degree_type' => null],
            ]);
        }

        if (! Schema::hasTable('year_tbl')) {
            Schema::create('year_tbl', function (Blueprint $table): void {
                $table->id('year_id');
                $table->string('year')->nullable();
            });
            DB::table('year_tbl')->insert([
                ['year_id' => 1, 'year' => '1st Year'],
                ['year_id' => 2, 'year' => '4th Year'],
            ]);
        }

        if (! Schema::hasTable('student_info_tbl')) {
            Schema::create('student_info_tbl', function (Blueprint $table): void {
                $table->id('student_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('program_id')->nullable();
                $table->unsignedBigInteger('year_id')->nullable();
                $table->unsignedBigInteger('section_id')->nullable();
                $table->unsignedBigInteger('student_status_id')->nullable();
                $table->tinyInteger('ladderized')->default(0);
            });
        }

        if (! Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $table): void {
                $table->id();
                $table->string('username')->nullable();
                $table->string('email')->unique();
                $table->string('password');
                $table->text('mfa_totp_secret')->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
            });
        }

        if (! Schema::hasTable('admin_info_tbl')) {
            Schema::create('admin_info_tbl', function (Blueprint $table): void {
                $table->unsignedBigInteger('admin_id')->primary();
                $table->string('position')->nullable();
                $table->string('designation')->nullable();
                $table->string('role')->nullable();
                $table->string('title')->nullable();
            });
        }

        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->id('audit_log_id');
                $table->string('actor_type')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('action');
                $table->string('module')->nullable();
                $table->text('description')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }
}
