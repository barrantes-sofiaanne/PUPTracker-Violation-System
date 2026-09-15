<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * First-time STUDENT REGISTRATION (self-registration) flow.
 *
 * PUPTracker has NO pre-created student-number roster: a successfully
 * authenticated IDP identity is enough to register. The student supplies their
 * student number / program / year and acknowledges the privacy notice; the
 * server creates the account from the VERIFIED IDP identity. This suite proves:
 *
 *   1. IDP-authenticated, no linked account -> registration page.
 *   2. Profile complete                    -> dashboard directly.
 *   3. Unauthenticated                     -> never the registration page.
 *   4. Repeated access / double submit     -> no duplicate accounts.
 *   5. Unrelated students unaffected.
 *   6. Dashboard unreachable before completion.
 */
class StudentRegistrationFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Point the student IDP flow at a fake endpoint (mirrors
        // AdminIdpLinkingTest) so the callback can complete without a network.
        Config::set('services.idp.base_url', 'https://idp.test/api/v1');
        Config::set('services.idp.client_id', 'student-client');
        Config::set('services.idp.client_secret', 'student-secret');
        Config::set('services.idp.redirect_uri', 'https://puptvs.com/student/idp/callback');
        Config::set('services.idp.timeout', 15);
        Config::set('services.idp.student_roles', 'student');

        $this->ensureTables();
        $this->fakeIdp();
    }

    // ==================================================================
    // 1. FIRST-TIME STUDENT — IDP LOGIN → REGISTRATION PAGE
    // ==================================================================

    /**
     * An IDP-authenticated student with NO local link is sent to the
     * registration page and is NOT authenticated yet.
     */
    public function test_first_time_student_is_sent_to_registration_after_idp_login(): void
    {
        $this->createStudentRecord(['idp_subject' => null]);

        $response = $this->hitCallback();

        $response->assertRedirect(route('student.idp.link'));
        $this->assertGuest('student');

        // The registration page renders with the privacy notice + verified identity.
        $page = $this->get(route('student.idp.link'))->assertOk()->getContent();

        $this->assertStringContainsString('Information Privacy Notice', $page);
        $this->assertStringContainsString('Complete Your PUPTracker Registration', $page);
        $this->assertStringContainsString('Your university account has been successfully authenticated', $page);
        $this->assertStringContainsString('Verified by PUP Identity Provider', $page);

        // The IDP-provided identity is shown read-only, not asked for again.
        $this->assertStringContainsString('IDP Student', $page);
        $this->assertStringContainsString('student.idp@example.com', $page);

        // The student supplies ONLY academic data + the notice acknowledgement.
        $this->assertStringContainsString('name="student_number"', $page);
        $this->assertStringContainsString('name="program_id"', $page);
        $this->assertStringContainsString('name="year_id"', $page);
        $this->assertStringContainsString('name="privacy_acknowledged"', $page);

        // Identity fields are never submittable form inputs.
        $this->assertStringNotContainsString('name="first_name"', $page);
        $this->assertStringNotContainsString('name="last_name"', $page);
        $this->assertStringNotContainsString('name="email"', $page);
        $this->assertStringNotContainsString('name="idp_subject"', $page);

        // Privacy notice states the actual purposes (not a vague claim).
        // The notice text is wrapped across source lines, so whitespace is
        // collapsed before matching multi-word phrases.
        $flat = preg_replace('/\s+/', ' ', $page);

        $this->assertStringContainsString('violation and sanction management', $flat);
        $this->assertStringContainsString('data privacy requirements', $flat);
        $this->assertStringContainsString('By proceeding with registration, you acknowledge', $flat);
    }

    /**
     * The privacy notice must never claim information is merely "only used for
     * the system" — it must name the legitimate purposes.
     */
    public function test_registration_page_privacy_notice_names_the_purposes(): void
    {
        $this->createStudentRecord(['idp_subject' => null]);
        $this->hitCallback();

        $page = $this->get(route('student.idp.link'))->assertOk()->getContent();
        $flat = preg_replace('/\s+/', ' ', $page);

        $this->assertStringNotContainsString('only used for the system', $flat);

        foreach (['student identification', 'record keeping', 'authorized student-related processes'] as $purpose) {
            $this->assertStringContainsString($purpose, $flat);
        }
    }

    // ==================================================================
    // 2. EXISTING REGISTERED STUDENT — STRAIGHT TO DASHBOARD
    // ==================================================================

    public function test_existing_completed_student_goes_straight_to_dashboard(): void
    {
        // Uses the DEFAULT faked IDP subject ('user-123') that setUp() registers,
        // so no per-test Http::fake override is needed.
        $this->createStudentRecord([
            'idp_subject' => 'user-123',
            'profile_completed_at' => now()->subMonth(),
        ]);

        $this->hitCallback()->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated('student');

        // The dashboard is reachable — the gate does not interfere.
        $this->get(route('student.dashboard'))->assertOk();
    }

    /**
     * A student linked but NOT marked complete cannot reach the dashboard.
     *
     * `profile_completed_at` alone decides access: a linked account that never
     * finished registration is still treated as incomplete.
     */
    public function test_linked_but_incomplete_student_cannot_reach_the_dashboard(): void
    {
        $this->createStudentRecord([
            'idp_subject' => 'half-way',
            'profile_completed_at' => null,
        ]);

        $student = User::where('idp_subject', 'half-way')->firstOrFail();
        $this->actingAs($student, 'student');

        // No staged IDP session → the student is signed out and sent to IDP
        // login rather than shown a registration form without a verified
        // identity.
        $this->get(route('student.dashboard'))
            ->assertRedirect(route('student.login'));

        $this->assertGuest('student');
    }

    // ==================================================================
    // 3. UNAUTHENTICATED ACCESS
    // ==================================================================

    public function test_unauthenticated_visitor_cannot_reach_the_registration_page(): void
    {
        // No IDP session staged at all.
        $this->get(route('student.idp.link'))
            ->assertRedirect(route('student.idp.login'));
    }

    public function test_unauthenticated_visitor_cannot_submit_registration(): void
    {
        $this->post(route('student.idp.link.post'), [
            'student_number' => '2026-00003',
            'program_id' => 1,
            'year_id' => 1,
            'privacy_acknowledged' => '1',
        ])
            ->assertRedirect(route('student.login'))
            ->assertSessionHasErrors('login');

        // No account was created.
        $this->assertDatabaseMissing('users_tbl', ['idp_subject' => 'user-123']);
        $this->assertSame(0, User::count());
    }

    // ==================================================================
    // 4. DASHBOARD GATE
    // ==================================================================

    public function test_dashboard_is_unreachable_before_registration_is_complete(): void
    {
        $this->createStudentRecord(['idp_subject' => null]);
        $student = User::where('student_number', '2026-00003')->firstOrFail();

        // Authenticated but incomplete, with NO staged IDP session: the student
        // is logged out and told to sign in again (never shown registration
        // without a verified IDP identity).
        $this->actingAs($student, 'student');

        $this->get(route('student.dashboard'))
            ->assertRedirect(route('student.login'));

        $this->assertGuest('student');
    }

    /**
     * With a staged IDP session, an incomplete student is redirected to the
     * registration page instead of being logged out.
     */
    public function test_dashboard_redirects_to_registration_when_idp_session_is_staged(): void
    {
        $this->createStudentRecord(['idp_subject' => null]);
        $student = User::where('student_number', '2026-00003')->firstOrFail();

        $this->actingAs($student, 'student');

        // Stage a verified IDP identity exactly as the callback does.
        $this->withSession([
            'student.idp.pending_profile' => [
                'subject' => 'user-123',
                'email' => 'student.idp@example.com',
                'first_name' => 'IDP',
                'middle_name' => '',
                'last_name' => 'Student',
                'suffix' => '',
            ],
        ])->get(route('student.dashboard'))
            ->assertRedirect(route('student.idp.link'));
    }

    // ==================================================================
    // 5. REGISTRATION COMPLETION
    // ==================================================================

    public function test_completing_registration_marks_profile_complete_and_reaches_dashboard(): void
    {
        // No pre-created record: registration creates it.
        $this->assertSame(0, User::count());

        $this->withSession($this->pendingProfileSession())
            ->post(route('student.idp.link.post'), $this->registrationPayload())
            ->assertRedirect(route('student.dashboard'))
            ->assertSessionHas('success', 'Registration completed successfully. You may now access PUPTracker.');

        $student = User::where('student_number', '2026-00003')->firstOrFail();

        $this->assertSame('user-123', (string) $student->idp_subject);
        $this->assertSame('student.idp@example.com', (string) $student->idp_email);
        $this->assertSame(2, (int) $student->roles_id, 'Registered accounts always carry the student role.');
        $this->assertNotNull($student->profile_completed_at, 'Registration must mark the profile complete.');
        $this->assertNotNull($student->idp_connected_at);

        // Enrollment info was created from the submitted program/year.
        $this->assertDatabaseHas('student_info_tbl', [
            'user_id' => $student->user_id,
            'program_id' => 1,
            'year_id' => 1,
        ]);

        // And the dashboard is now reachable.
        $this->get(route('student.dashboard'))->assertOk();
    }

    /**
     * EDGE CASE — double submit. A second identical submission must not create a
     * duplicate record nor move the completion timestamp.
     */
    public function test_double_submit_does_not_duplicate_or_rewrite_completion(): void
    {
        $session = $this->pendingProfileSession();

        $this->withSession($session)
            ->post(route('student.idp.link.post'), $this->registrationPayload())
            ->assertRedirect(route('student.dashboard'));

        $first = User::where('student_number', '2026-00003')->firstOrFail();
        $firstCompletedAt = $first->profile_completed_at;

        $this->assertSame(1, User::where('student_number', '2026-00003')->count(), 'No duplicate student row.');

        // Replay the same submission (as a refresh/double-post would).
        $this->withSession($session)
            ->post(route('student.idp.link.post'), $this->registrationPayload());

        $this->assertSame(1, User::where('student_number', '2026-00003')->count());
        $this->assertSame(1, User::where('idp_subject', 'user-123')->count());
        $this->assertSame(
            $firstCompletedAt?->toDateTimeString(),
            User::where('student_number', '2026-00003')->firstOrFail()->profile_completed_at?->toDateTimeString(),
            'A replay must not rewrite the completion timestamp.'
        );
    }

    // ==================================================================
    // 6. IMPERSONATION IS STILL BLOCKED
    // ==================================================================

    /**
     * A student cannot take over ANOTHER account: the number is already in use,
     * so registration is rejected and the existing account is untouched.
     */
    public function test_student_cannot_register_another_students_number(): void
    {
        // The victim's existing account already uses this number.
        $victim = $this->createStudentRecord([
            'student_number' => '2026-99999',
            'first_name' => 'Victim',
            'last_name' => 'Person',
            'idp_subject' => 'victim-subject',
            'profile_completed_at' => now()->subDay(),
        ]);

        // Attacker authenticates as "IDP Student" and submits the victim's number.
        $this->withSession($this->pendingProfileSession())
            ->post(route('student.idp.link.post'), $this->registrationPayload([
                'student_number' => '2026-99999',
            ]))
            ->assertSessionHasErrors('student_number');

        $this->assertGuest('student');

        // The victim's account is completely untouched, and no new account exists.
        $victim->refresh();
        $this->assertSame('victim-subject', (string) $victim->idp_subject);
        $this->assertSame('Victim', (string) $victim->first_name);
        $this->assertSame(1, User::count());
        $this->assertDatabaseMissing('users_tbl', ['idp_subject' => 'user-123']);
    }

    // ==================================================================
    // 7. EXISTING USERS UNAFFECTED
    // ==================================================================

    /**
     * A completed student's access to the module is unchanged — including the
     * other student routes.
     */
    public function test_existing_student_keeps_access_to_the_module(): void
    {
        $student = $this->createStudentRecord([
            'idp_subject' => 'done-subject',
            'profile_completed_at' => now()->subDay(),
        ]);

        $this->actingAs($student, 'student');

        $this->get(route('student.dashboard'))->assertOk();
        $this->get(route('student.record'))->assertOk();
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    private function fakeIdp(
        string $idpSubject = 'user-123',
        string $email = 'student.idp@example.com',
        string $roles = 'student'
    ): void {
        Http::fake([
            'https://idp.test/api/v1/auth/token' => Http::response([
                'access_token' => 'access-token-123',
                'refresh_token' => 'refresh-token-123',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
            'https://idp.test/api/v1/me' => Http::response([
                'id' => $idpSubject,
                'email' => $email,
                'first_name' => 'IDP',
                'middle_name' => null,
                'last_name' => 'Student',
                'name_suffix' => null,
                'roles' => $roles,
            ], 200),
        ]);
    }

    private function hitCallback(): \Illuminate\Testing\TestResponse
    {
        return $this->withSession(['student.idp.state' => 'state-123'])
            ->get(route('student.idp.callback', ['code' => 'auth-code-123']));
    }

    /** The verified IDP identity staged server-side by the callback. */
    private function pendingProfileSession(
        string $subject = 'user-123',
        string $email = 'student.idp@example.com'
    ): array {
        return [
            'student.idp.pending_profile' => [
                'subject' => $subject,
                'email' => $email,
                'first_name' => 'IDP',
                'middle_name' => '',
                'last_name' => 'Student',
                'suffix' => '',
            ],
            'student.idp.pending_subject' => $subject,
            'student.idp.pending_email' => $email,
        ];
    }

    /** A valid registration submission. */
    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'student_number' => '2026-00003',
            'program_id' => 1,
            'year_id' => 1,
            'section_id' => 1,
            'gender_id' => 1,
            'privacy_acknowledged' => '1',
        ], $overrides);
    }

    private function createStudentRecord(array $overrides = []): User
    {
        $student = User::create(array_merge([
            'student_number' => '2026-00003',
            'first_name' => 'IDP',
            'last_name' => 'Student',
            'email' => 'student.idp@example.com',
            'idp_subject' => null,
            'idp_email' => null,
            'profile_completed_at' => null,
            'status_id' => 1,
            'roles_id' => 2,
            'password_hash' => 'x',
        ], $overrides));

        if (Schema::hasTable('student_info_tbl')) {
            DB::table('student_info_tbl')->updateOrInsert(
                ['user_id' => $student->user_id],
                [
                    'program_id' => 1,
                    'year_id' => 1,
                    'section_id' => 1,
                    'student_status_id' => 1,
                ]
            );
        }

        return $student;
    }

    private function ensureTables(): void
    {
        if (! Schema::hasTable('users_tbl')) {
            Schema::create('users_tbl', function (Blueprint $t): void {
                $t->increments('user_id');
                $t->string('student_number')->nullable();
                $t->string('first_name')->nullable();
                $t->string('middle_name')->nullable();
                $t->string('last_name')->nullable();
                $t->string('name_suffix')->nullable();
                $t->string('email')->nullable();
                $t->string('idp_subject')->nullable()->unique();
                $t->string('idp_email')->nullable();
                $t->timestamp('idp_connected_at')->nullable();
                $t->timestamp('idp_last_login_at')->nullable();
                $t->timestamp('profile_completed_at')->nullable();
                $t->unsignedBigInteger('gender_id')->nullable();
                $t->unsignedBigInteger('status_id')->nullable();
                $t->unsignedBigInteger('roles_id')->nullable();
                $t->string('password_hash')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('student_info_tbl')) {
            Schema::create('student_info_tbl', function (Blueprint $t): void {
                $t->unsignedBigInteger('user_id')->primary();
                $t->unsignedBigInteger('program_id')->nullable();
                $t->unsignedBigInteger('year_id')->nullable();
                $t->unsignedBigInteger('section_id')->nullable();
                $t->unsignedBigInteger('student_status_id')->nullable();
                $t->unsignedTinyInteger('ladderized')->default(0);
            });
        }

        foreach ([
            'roles_tbl' => ['roles_id', 'roles_name'],
            'status_tbl' => ['status_id', 'status_name'],
            'program_tbl' => ['program_id', 'program_name'],
            'year_tbl' => ['year_id', 'year'],
            'section_tbl' => ['section_id', 'section_name'],
            'gender_tbl' => ['gender_id', 'gender_name'],
            'student_status_tbl' => ['student_status_id', 'status_name'],
            'admins' => ['id', 'email'],
            'violation_tbl' => ['violation_id', 'status'],
            'sanction_requests_tbl' => ['sanction_request_id', 'status'],
            'student_sanction_records_tbl' => ['record_id', 'status'],
            'notifications_tbl' => ['notification_id', 'title'],
            'audit_logs' => ['id', 'action'],
            // Referenced by the dashboard / record views the gate lets through.
            'violation_category_tbl' => ['violation_category_id', 'category_name'],
            'violation_type_tbl' => ['violation_type_id', 'violation_type'],
            'disciplinary_sanctions' => ['disciplinary_sanction_id', 'offense_level'],
        ] as $table => [$pk, $extraCol]) {
            if (Schema::hasTable($table)) {
                continue;
            }
            Schema::create($table, function (Blueprint $b) use ($pk, $extraCol): void {
                $b->increments($pk);
                // `student_number` is the shared lookup column across these
                // tables; `$extraCol` avoids adding it twice.
                $b->string('student_number')->nullable();
                $b->unsignedBigInteger('recipient_id')->nullable();
                if ($extraCol !== 'student_number') {
                    $b->string($extraCol)->nullable();
                }
                $b->timestamps();
            });
        }

        // Program / year / section / gender dropdowns supply the student's
        // choices, and the `exists:` validation rules need real rows.
        if (DB::table('program_tbl')->count() === 0) {
            DB::table('program_tbl')->insert([
                ['program_id' => 1, 'program_name' => 'BSIT'],
                ['program_id' => 2, 'program_name' => 'BSCS'],
            ]);
        }
        if (DB::table('year_tbl')->count() === 0) {
            DB::table('year_tbl')->insert([
                ['year_id' => 1, 'year' => '1st Year'],
                ['year_id' => 2, 'year' => '4th Year'],
            ]);
        }
        if (DB::table('section_tbl')->count() === 0) {
            DB::table('section_tbl')->insert([
                ['section_id' => 1, 'section_name' => 'BSIT 1-A'],
                ['section_id' => 2, 'section_name' => 'BSIT 4-A'],
            ]);
        }
        if (DB::table('gender_tbl')->count() === 0) {
            DB::table('gender_tbl')->insert([
                ['gender_id' => 1, 'gender_name' => 'Male'],
                ['gender_id' => 2, 'gender_name' => 'Female'],
            ]);
        }

        if (DB::table('roles_tbl')->count() === 0) {
            DB::table('roles_tbl')->insert([
                ['roles_id' => 1, 'roles_name' => 'Admin'],
                ['roles_id' => 2, 'roles_name' => 'Student'],
            ]);
        }
        if (DB::table('student_status_tbl')->count() === 0) {
            DB::table('student_status_tbl')->insert(['status_name' => 'Active']);
        }
        if (DB::table('status_tbl')->count() === 0) {
            DB::table('status_tbl')->insert(['status_name' => 'Active']);
        }
    }
}
