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
    // Secure self-linking flow (student never types their IDP id)
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

    public function test_unlinked_student_is_redirected_to_self_link_form(): void
    {
        // Even when the local email matches, an unlinked IDP user is NOT
        // auto-linked — they are always routed to the connect flow.
        $this->createStudent(['email' => 'student.idp@example.com']);
        $this->fakeIdp(idpSubject: 'user-new', email: 'student.idp@example.com');

        $response = $this->hitCallback();
        $response->assertRedirect(route('student.idp.link'));
        $this->assertGuest('student');
    }

    public function test_self_link_requires_pending_idp_session(): void
    {
        // No pending session -> redirect back to IDP login.
        $response = $this->get(route('student.idp.link'));
        $response->assertRedirect(route('student.idp.login'));
    }

    public function test_student_can_connect_with_correct_student_number(): void
    {
        $this->createStudent(['email' => 'student.idp@example.com']);
        $this->fakeIdp(idpSubject: 'user-new', email: 'student.idp@example.com');

        $this->hitCallback()->assertRedirect(route('student.idp.link'));

        // Step: number entry -> verified -> staged preview (no link yet).
        $response = $this->withSession($this->pendingProfileSession())
            ->post(route('student.idp.link.post'), [
                'student_number' => '2026-00003',
            ]);

        $response->assertRedirect(route('student.idp.link.preview'));
        $this->assertGuest('student');
        $this->assertSame(null, User::where('student_number', '2026-00003')->first()->fresh()->idp_subject);

        // Preview GET renders.
        $preview = $this->withSession($this->pendingProfileSession())
            ->withSession(['student.idp.pending_student_number' => '2026-00003'])
            ->get(route('student.idp.link.preview'));
        $preview->assertOk();

        // Final submit binds the IDP link and logs the student in.
        $submit = $this->withSession($this->pendingProfileSession())
            ->withSession(['student.idp.pending_student_number' => '2026-00003'])
            ->post(route('student.idp.link.submit'));

        $submit->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated('student');

        $this->assertDatabaseHas('users_tbl', [
            'user_id' => User::where('student_number', '2026-00003')->first()->user_id,
            'idp_subject' => 'user-new',
            'idp_email' => 'student.idp@example.com',
        ]);
    }

    public function test_self_link_rejects_unknown_student_number(): void
    {
        $this->createStudent(['email' => 'student.idp@example.com']);
        $this->fakeIdp(idpSubject: 'user-new', email: 'student.idp@example.com');

        $response = $this->withSession($this->pendingProfileSession())
            ->post(route('student.idp.link.post'), [
                'student_number' => 'NOPE-999',
            ]);

        $response->assertSessionHasErrors('student_number');
        $this->assertGuest('student');
    }

    public function test_self_link_rejects_if_idp_id_already_bound_to_another(): void
    {
        $this->createStudent([
            'student_number' => '2026-00001',
            'idp_subject' => 'user-bound',
            'email' => 'bound@example.com',
        ]);
        $other = $this->createStudent([
            'student_number' => '2026-00002',
            'email' => 'other@example.com',
        ]);

        $this->fakeIdp(idpSubject: 'user-bound', email: 'bound@example.com');

        $response = $this->withSession($this->pendingProfileSession('user-bound', 'bound@example.com'))
            ->post(route('student.idp.link.post'), [
                'student_number' => '2026-00002',
            ]);

        $response->assertSessionHasErrors('login');
        $this->assertSame(null, $other->fresh()->idp_subject);
        $this->assertGuest('student');
    }

    public function test_self_link_rejects_inactive_student(): void
    {
        $this->createStudent(['status_id' => 2]);
        $this->fakeIdp(idpSubject: 'user-new', email: 'student.idp@example.com');

        $response = $this->withSession($this->pendingProfileSession())
            ->post(route('student.idp.link.post'), [
                'student_number' => '2026-00003',
            ]);

        $response->assertSessionHasErrors('student_number');
        $this->assertGuest('student');
    }

    public function test_self_link_rejects_name_mismatch(): void
    {
        // Local name is "IDP Student" (from createStudent default); the IDP
        // profile in the pending session carries a DIFFERENT name.
        $student = $this->createStudent(['email' => 'student.idp@example.com']);
        $this->fakeIdp(idpSubject: 'user-new', email: 'student.idp@example.com');

        $session = $this->pendingProfileSession();
        $session['student.idp.pending_profile']['first_name'] = 'Juan';
        $session['student.idp.pending_profile']['last_name'] = 'Dela Cruz';

        $response = $this->withSession($session)
            ->post(route('student.idp.link.post'), [
                'student_number' => '2026-00003',
            ]);

        $response->assertSessionHasErrors('student_number');
        $this->assertSame(null, $student->fresh()->idp_subject);
        $this->assertGuest('student');
    }

    public function test_student_can_update_program_year_before_submit(): void
    {
        $this->createStudent(['email' => 'student.idp@example.com']);
        $this->fakeIdp(idpSubject: 'user-new', email: 'student.idp@example.com');

        $this->withSession($this->pendingProfileSession())
            ->post(route('student.idp.link.post'), ['student_number' => '2026-00003'])
            ->assertRedirect(route('student.idp.link.preview'));

        // Update program/year (staged only).
        $this->withSession($this->pendingProfileSession())
            ->withSession(['student.idp.pending_student_number' => '2026-00003'])
            ->post(route('student.idp.link.program-year'), [
                'program_id' => 2,
                'year_id' => 2,
            ])
            ->assertRedirect(route('student.idp.link.preview'));

        // Final submit applies the association.
        $this->withSession($this->pendingProfileSession())
            ->withSession([
                'student.idp.pending_student_number' => '2026-00003',
                'student.idp.pending_program_id' => 2,
                'student.idp.pending_year_id' => 2,
            ])
            ->post(route('student.idp.link.submit'))
            ->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticated('student');
        $this->assertDatabaseHas('users_tbl', [
            'student_number' => '2026-00003',
            'idp_subject' => 'user-new',
        ]);
    }

    public function test_preview_requires_staged_student_number(): void
    {
        $this->fakeIdp(idpSubject: 'user-new', email: 'student.idp@example.com');

        // Pending profile exists but no staged student number yet.
        $response = $this->withSession($this->pendingProfileSession())
            ->get(route('student.idp.link.preview'));

        $response->assertRedirect(route('student.idp.link'));
    }

    // ------------------------------------------------------------------
    // Corrected architecture: IDP is authoritative for identity/name.
    // Local name fields may be blank before first login and are populated
    // (and refreshed on later logins) from the authenticated IDP profile.
    // ------------------------------------------------------------------

    public function test_blank_name_student_can_link_and_names_populated_from_idp(): void
    {
        // Local record exists with NO name yet (the pre-first-login state).
        $this->createStudent([
            'email' => 'student.idp@example.com',
            'first_name' => null,
            'middle_name' => null,
            'last_name' => null,
            'name_suffix' => null,
        ]);
        $this->fakeIdp(
            idpSubject: 'user-new',
            email: 'student.idp@example.com',
            profileOverrides: [
                'first_name' => 'Juan',
                'middle_name' => 'Dela',
                'last_name' => 'Cruz',
                'name_suffix' => 'Jr.',
            ]
        );

        $session = $this->pendingProfileSession(profileOverrides: [
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz',
            'suffix' => 'Jr.',
        ]);

        // Step: number entry → verified (blank local name is allowed).
        $this->withSession($session)
            ->post(route('student.idp.link.post'), ['student_number' => '2026-00003'])
            ->assertRedirect(route('student.idp.link.preview'));

        // Final submit links + populates names from the authoritative IDP.
        $this->withSession($session)
            ->withSession(['student.idp.pending_student_number' => '2026-00003'])
            ->post(route('student.idp.link.submit'))
            ->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticated('student');

        $this->assertDatabaseHas('users_tbl', [
            'user_id' => User::where('student_number', '2026-00003')->first()->user_id,
            'idp_subject' => 'user-new',
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz',
            'name_suffix' => 'Jr.',
        ]);
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

    public function test_name_mismatch_still_rejects_when_local_name_is_present(): void
    {
        // Local record ALREADY has a name that conflicts with the IDP.
        $student = $this->createStudent(['email' => 'student.idp@example.com']);
        $this->fakeIdp(idpSubject: 'user-new', email: 'student.idp@example.com');

        $session = $this->pendingProfileSession(profileOverrides: [
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz',
            'suffix' => '',
        ]);

        $response = $this->withSession($session)
            ->post(route('student.idp.link.post'), [
                'student_number' => '2026-00003',
            ]);

        // Local record is "IDP Student" → conflict → rejected, nothing saved.
        $response->assertSessionHasErrors('student_number');
        $this->assertSame(null, $student->fresh()->idp_subject);
        $this->assertGuest('student');
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

    // ------------------------------------------------------------------
    // Schema
    // ------------------------------------------------------------------

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
