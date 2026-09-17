<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\SanctionRequest;
use App\Models\Security;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationCategory;
use App\Models\ViolationType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The OSS Staff module (formerly the "Admin Assistant" role).
 *
 * The role was renamed in the database to 'OSS Staff' and is an ADMIN account:
 * it authenticates on the `admin` guard and its own module lives under
 * `/oss-staff/...`.
 *
 * The important cases here are the BOUNDARIES:
 *   - only an OSS Staff admin may reach /oss-staff
 *   - a plain Admin, an IT Administrator and a Security officer may NOT
 *   - OSS Staff activity is recorded as an ADMIN actor, never as Security
 *     (recorder_type/uploader_type = 'admin', recorded_by_security_id NULL)
 *   - the Security module is untouched
 */
class OssStaffModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
        $this->seedReferenceData();
    }

    // ==================================================================
    // Access control
    // ==================================================================

    /** The three required middlewares are attached to every oss-staff route. */
    public function test_oss_staff_routes_carry_all_three_required_middlewares(): void
    {
        $this->app->make(\Illuminate\Contracts\Http\Kernel::class)->bootstrap();

        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->getName() ?? '', 'oss-staff.')) {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            $checked++;

            $this->assertContains('auth:admin', $middleware, "{$route->getName()} must require auth:admin.");
            $this->assertContains('oss.staff', $middleware, "{$route->getName()} must require oss.staff.");
            $this->assertContains('session.inactivity', $middleware, "{$route->getName()} must require session.inactivity.");
        }

        $this->assertGreaterThanOrEqual(20, $checked, 'The oss-staff module must expose its operational routes.');
    }

    /** An OSS Staff admin reaches the module. */
    public function test_oss_staff_admin_can_reach_the_dashboard(): void
    {
        $ossStaff = $this->createAdmin('oss@example.com', 'OSS Staff');

        $this->actingAs($ossStaff, 'admin')
            ->get(route('oss-staff.dashboard'))
            ->assertOk()
            ->assertSee('OSS Staff Dashboard', false);
    }

    /** A plain Administrator is refused (fail closed). */
    public function test_plain_admin_is_forbidden(): void
    {
        $plain = $this->createAdmin('plain@example.com', 'Admin');

        $this->actingAs($plain, 'admin')
            ->get(route('oss-staff.dashboard'))
            ->assertForbidden();
    }

    /** An IT Administrator is refused. */
    public function test_it_administrator_is_forbidden(): void
    {
        $it = $this->createAdmin('it@example.com', 'IT Administrator');

        $this->actingAs($it, 'admin')
            ->get(route('oss-staff.dashboard'))
            ->assertForbidden();
    }

    /** An admin with no resolvable role is refused. */
    public function test_admin_with_unknown_role_is_forbidden(): void
    {
        $admin = $this->createAdmin('unknown@example.com', 'Some Fabricated Role');

        $this->actingAs($admin, 'admin')
            ->get(route('oss-staff.dashboard'))
            ->assertForbidden();
    }

    /**
     * A Security officer has no access to the OSS Staff module.
     *
     * A Security account is not an `admins` row, so `auth:admin` does not
     * authenticate it and the request is bounced by the auth middleware (a
     * redirect), never reaching the OSS Staff controller. Either way the
     * officer cannot enter the module.
     */
    public function test_security_officer_cannot_enter_the_module(): void
    {
        $security = Security::create([
            'email' => 'officer@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->actingAs($security, 'security')
            ->get(route('oss-staff.dashboard'));

        $this->assertContains(
            $response->getStatusCode(),
            [302, 403],
            'A Security officer must not reach the OSS Staff module.'
        );

        $response->assertRedirect();
    }

    /** Every oss-staff page requires authentication (a guest never reaches it). */
    public function test_guest_is_not_served_an_oss_staff_page(): void
    {
        $this->get(route('oss-staff.dashboard'))->assertRedirect();
    }

    // ==================================================================
    // STRICT MODULE ISOLATION — the full access matrix
    // ==================================================================

    /**
     * The complete two-way access matrix.
     *
     * Each account type may reach EXACTLY ONE operational module:
     *
     *   Security         -> /security  YES | /oss-staff NO
     *   OSS Staff        -> /security  NO  | /oss-staff YES
     *   Regular Admin    -> /security  NO  | /oss-staff NO   (owns /admin)
     *   IT Administrator -> /security  NO  | /oss-staff NO   (owns /admin/IT)
     *
     * A denied account is refused HERE rather than being bounced to a login
     * page, so the assertion is an explicit 403 wherever the account is
     * authenticated on a guard the module accepts.
     */
    public function test_strict_module_isolation_access_matrix(): void
    {
        $security = Security::create([
            'email' => 'officer@example.com',
            'password' => Hash::make('password'),
        ]);
        $ossStaff = $this->createAdmin('oss@example.com', 'OSS Staff');
        $plain = $this->createAdmin('plain@example.com', 'Admin');
        $it = $this->createAdmin('it@example.com', 'IT Administrator');

        // ---- Security account: Security only -------------------------------
        $this->actAsOnly($security, 'security');

        $this->get(route('security.dashboard'))->assertOk();
        // Not an `admins` row, so `auth:admin` rejects it before any module
        // authorization runs.
        $this->get(route('oss-staff.dashboard'))->assertRedirect();

        // ---- OSS Staff account: OSS Staff only ------------------------------
        $this->actAsOnly($ossStaff, 'admin');

        $this->get(route('oss-staff.dashboard'))->assertOk();
        $this->get(route('security.dashboard'))->assertForbidden();

        // ---- Regular Admin: neither -----------------------------------------
        $this->actAsOnly($plain, 'admin');

        $this->get(route('oss-staff.dashboard'))->assertForbidden();
        $this->get(route('security.dashboard'))->assertForbidden();

        // ---- IT Administrator: neither --------------------------------------
        $this->actAsOnly($it, 'admin');

        $this->get(route('oss-staff.dashboard'))->assertForbidden();
        $this->get(route('security.dashboard'))->assertForbidden();
    }

    /**
     * Authenticate as exactly ONE account.
     *
     * `actingAs()` sets a user on the requested guard but leaves any guard
     * authenticated by an earlier call in place. Both guards cannot realistically
     * be signed in at once (the shared login page is `guest:security`, so a
     * signed-in Security officer is never offered the OSS Staff credential
     * form), so the guards are cleared here to reflect a single signing.
     */
    private function actAsOnly(\Illuminate\Contracts\Auth\Authenticatable $user, string $guard): void
    {
        $this->app['auth']->forgetGuards();

        $this->actingAs($user, $guard);
    }

    /** The isolation holds across the OPERATIONAL security pages too. */
    public function test_oss_staff_is_denied_every_security_operational_page(): void
    {
        $ossStaff = $this->createAdmin('oss@example.com', 'OSS Staff');

        $this->actAsOnly($ossStaff, 'admin');

        foreach ([
            'security.dashboard',
            'security.violations.students',
            'security.violations.submitted',
            'security.sanction-requests',
            'security.announcements',
            'security.notifications',
            'security.profile',
        ] as $routeName) {
            $this->get(route($routeName))->assertForbidden();
        }
    }

    /** A Security officer is denied every OSS Staff page. */
    public function test_security_officer_is_denied_every_oss_staff_page(): void
    {
        $security = Security::create([
            'email' => 'officer@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->actAsOnly($security, 'security');

        foreach ([
            'oss-staff.dashboard',
            'oss-staff.violations.students',
            'oss-staff.sanction-requests',
            'oss-staff.announcements',
            'oss-staff.notifications',
            'oss-staff.profile',
        ] as $routeName) {
            $this->get(route($routeName))->assertRedirect();
        }
    }

    // ==================================================================
    // Operational pages render
    // ==================================================================

    public function test_oss_staff_operational_pages_render(): void
    {
        $ossStaff = $this->createAdmin('oss@example.com', 'OSS Staff');

        foreach ([
            'oss-staff.dashboard',
            'oss-staff.violations.students',
            'oss-staff.violations.submitted',
            'oss-staff.sanction-requests',
            'oss-staff.announcements',
            'oss-staff.notifications',
            'oss-staff.profile',
        ] as $name) {
            $this->actingAs($ossStaff, 'admin')
                ->get(route($name))
                ->assertOk();
        }
    }

    /** The dashboard chart endpoint answers for an OSS Staff account. */
    public function test_dashboard_chart_data_returns_json(): void
    {
        $ossStaff = $this->createAdmin('oss@example.com', 'OSS Staff');

        $this->actingAs($ossStaff, 'admin')
            ->getJson(route('oss-staff.dashboard.chart-data') . '?period=week')
            ->assertOk()
            ->assertJsonStructure(['labels', 'values', 'total', 'filters']);
    }

    // ==================================================================
    // The module must NOT be Security
    // ==================================================================

    /** The rendered module never presents itself as the Security module. */
    public function test_module_branding_is_oss_staff_not_security(): void
    {
        $ossStaff = $this->createAdmin('oss@example.com', 'OSS Staff');

        $html = $this->actingAs($ossStaff, 'admin')
            ->get(route('oss-staff.dashboard'))
            ->getContent();

        $this->assertStringContainsString('OSS Staff', $html);
        $this->assertStringNotContainsString('Security Portal', $html);
        $this->assertStringNotContainsString('Security Module', $html);
        $this->assertStringNotContainsString('Security Dashboard', $html);
        $this->assertStringNotContainsString('Security Officer Dashboard', $html);
        $this->assertStringNotContainsString('Admin Assistant', $html);

        // The OSS Staff logout is the ADMIN logout, not the Security one.
        $this->assertStringContainsString(route('admin.logout'), $html);
        $this->assertStringNotContainsString(route('security.logout'), $html);
    }

    /** The OSS Staff views never reference the security guard. */
    public function test_views_do_not_use_the_security_guard(): void
    {
        $files = glob(resource_path('views/oss-staff/**/*.blade.php'));
        $files = array_merge($files, glob(resource_path('views/oss-staff/*.blade.php')));

        $this->assertNotEmpty($files, 'The OSS Staff views must exist.');

        foreach ($files as $file) {
            $contents = file_get_contents($file);
            $name = str_replace(resource_path('views/') . '', '', $file);

            $this->assertStringNotContainsString("auth('security')", $contents, "{$name} must not use the security guard.");
            $this->assertStringNotContainsString("guard('security')", $contents, "{$name} must not use the security guard.");
            $this->assertStringNotContainsString('layouts.security', $contents, "{$name} must not extend the Security layout.");
            $this->assertStringNotContainsString('Security Module', $contents, "{$name} must not carry Security branding.");
            $this->assertStringNotContainsString('Security Portal', $contents, "{$name} must not carry Security branding.");
        }
    }

    /**
     * The OSS Staff controllers never identify the actor as Security.
     *
     * Scanning the code is the point: a hard-coded security actor is easy to
     * introduce by copy-paste and would silently misattribute audit records.
     * Docblock prose is stripped first so a legitimate mention of the Security
     * module in a comment cannot produce a false positive.
     */
    public function test_controllers_do_not_hard_code_a_security_actor(): void
    {
        $files = glob(app_path('Http/Controllers/OssStaff/**/*.php'));
        $files = array_merge($files, glob(app_path('Http/Controllers/OssStaff/*.php')));

        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $code = $this->stripComments((string) file_get_contents($file));
            $name = basename($file);

            $this->assertStringNotContainsString("auth('security')", $code, "{$name} must not use the security guard.");
            $this->assertStringNotContainsString("guard('security')", $code, "{$name} must not use the security guard.");
            $this->assertStringNotContainsString("'security'", $code, "{$name} must not record a security actor.");
            $this->assertStringNotContainsString('recorded_by_security_id', $code, "{$name} must not write a security recorder id.");
        }
    }

    /** Remove PHP comments so prose cannot affect the assertions above. */
    private function stripComments(string $code): string
    {
        $tokens = token_get_all($code);
        $out = '';

        foreach ($tokens as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                $out .= $token[1];

                continue;
            }

            $out .= $token;
        }

        return $out;
    }

    // ==================================================================
    // Violation recording records an ADMIN actor
    // ==================================================================

    public function test_oss_staff_records_a_violation_as_an_admin_actor(): void
    {
        $ossStaff = $this->createAdmin('oss@example.com', 'OSS Staff');
        $this->createStudent('2024-0001');

        $response = $this->actingAs($ossStaff, 'admin')
            ->postJson(route('oss-staff.violations.store'), [
                'student_number' => '2024-0001',
                'violation_type_id' => 1,
                'violation_date' => now()->toDateString(),
                'description' => 'Recorded by OSS Staff.',
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $violation = Violation::where('student_number', '2024-0001')->firstOrFail();

        // The actor is the OSS Staff ADMIN account...
        $this->assertSame('admin', $violation->recorder_type);
        $this->assertSame($ossStaff->id, (int) $violation->recorder_id);
        $this->assertSame('Oss Staff', $violation->recorder_name);

        // ...and NOT a Security account.
        $this->assertNull(
            $violation->recorded_by_security_id,
            'An OSS Staff violation must never be recorded against a security id.'
        );

        // OSS Staff recordings go through the same admin confirmation queue.
        $this->assertSame(Violation::STATUS_PENDING, $violation->status);
    }

    /** The OSS Staff member sees only their OWN recordings on the tracker. */
    public function test_submitted_tracker_is_scoped_to_the_oss_staff_account(): void
    {
        $ossA = $this->createAdmin('ossA@example.com', 'OSS Staff');
        $ossB = $this->createAdmin('ossB@example.com', 'OSS Staff');
        $this->createStudent('2024-0001');

        $mine = Violation::create([
            'student_number' => '2024-0001',
            'violation_type' => 1,
            'violation_date' => now(),
            'description' => 'Recorded by OSS Staff A.',
            'recorder_id' => $ossA->id,
            'recorder_type' => 'admin',
            'recorder_name' => 'Oss Staff',
            'status' => Violation::STATUS_PENDING,
        ]);

        $theirs = Violation::create([
            'student_number' => '2024-0001',
            'violation_type' => 1,
            'violation_date' => now(),
            'description' => 'Recorded by OSS Staff B.',
            'recorder_id' => $ossB->id,
            'recorder_type' => 'admin',
            'recorder_name' => 'Oss Staff',
            'status' => Violation::STATUS_PENDING,
        ]);

        $html = $this->actingAs($ossA, 'admin')
            ->get(route('oss-staff.violations.students', ['tab' => 'submitted']))
            ->assertOk()
            ->getContent();

        // Both rows share a recorder_name, so the recorder_id is what separates
        // them - and the tracker must show only the signed-in account's row.
        $this->assertStringContainsString('Recorded by OSS Staff A.', $html);
        $this->assertStringNotContainsString('Recorded by OSS Staff B.', $html);
    }

    /** A Security-recorded violation is never attributed to OSS Staff. */
    public function test_security_recorded_rows_are_not_shown_as_oss_staff_records(): void
    {
        $ossStaff = $this->createAdmin('oss@example.com', 'OSS Staff');
        $this->createStudent('2024-0001');

        Violation::create([
            'student_number' => '2024-0001',
            'violation_type' => 1,
            'violation_date' => now(),
            'description' => 'Recorded by a Security officer.',
            'recorder_type' => 'security',
            'recorder_name' => 'Security Officer',
            'status' => Violation::STATUS_PENDING,
        ]);

        $html = $this->actingAs($ossStaff, 'admin')
            ->get(route('oss-staff.violations.students', ['tab' => 'submitted']))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Recorded by a Security officer.', $html);
    }

    // ==================================================================
    // Sanction requests record an ADMIN actor
    // ==================================================================

    public function test_oss_staff_sanction_decision_is_audited_as_admin(): void
    {
        $ossStaff = $this->createAdmin('oss@example.com', 'OSS Staff');
        $student = $this->createStudent('2024-0001');

        Violation::create([
            'student_number' => '2024-0001',
            'violation_type' => 1,
            'violation_date' => now(),
            'description' => 'Existing violation.',
            'recorder_type' => 'security',
            'recorder_name' => 'Officer',
            'status' => Violation::STATUS_CONFIRMED,
        ]);

        $request = SanctionRequest::create([
            'student_number' => '2024-0001',
            'violation_type_id' => 1,
            'request_date' => now(),
            'is_active' => true,
        ]);

        $this->actingAs($ossStaff, 'admin')
            ->post(route('oss-staff.sanction-requests.decline', $request))
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'admin',
            'actor_id' => $ossStaff->id,
            'module' => 'Sanction Requests',
        ]);

        $this->assertFalse((bool) $request->fresh()->is_active);
    }

    // ==================================================================
    // Security module compatibility
    // ==================================================================

    /** Security keeps its own module, untouched by the OSS Staff module. */
    public function test_security_module_still_works(): void
    {
        $security = Security::create([
            'email' => 'officer@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($security, 'security')
            ->get(route('security.dashboard'))
            ->assertOk();

        // ...and Security cannot enter the OSS Staff module (it is not an
        // admin-guard account, so the auth middleware bounces it).
        $this->actingAs($security, 'security')
            ->get(route('oss-staff.profile'))
            ->assertRedirect();
    }

    /** A plain admin still reaches the Admin module. */
    public function test_admin_module_is_unaffected(): void
    {
        $plain = $this->createAdmin('plain@example.com', 'Admin');

        $this->actingAs($plain, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    private function createAdmin(string $email, string $roleName): Admin
    {
        $admin = Admin::create([
            'email' => $email,
            'password' => Hash::make('password'),
            'mfa_totp_enabled' => false,
        ]);

        DB::table('admin_info_tbl')->insert([
            'admin_id' => $admin->id,
            'role_id' => DB::table('roles_tbl')->where('roles_name', $roleName)->value('roles_id'),
            'Position' => $roleName,
            'firstname' => 'Oss',
            'lastname' => 'Staff',
        ]);

        return $admin->fresh();
    }

    private function createStudent(string $studentNumber): User
    {
        $student = User::create([
            'student_number' => $studentNumber,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'roles_id' => DB::table('roles_tbl')->where('roles_name', 'Student')->value('roles_id'),
            'status_id' => DB::table('status_tbl')->where('status_name', 'Active')->value('status_id'),
        ]);

        DB::table('student_info_tbl')->insert([
            'user_id' => $student->user_id,
            'program_id' => 1,
            'year_id' => 1,
        ]);

        return $student;
    }

    private function seedReferenceData(): void
    {
        DB::table('roles_tbl')->insertOrIgnore([
            ['roles_id' => 1, 'roles_name' => 'Admin'],
            ['roles_id' => 2, 'roles_name' => 'IT Administrator'],
            ['roles_id' => 3, 'roles_name' => 'Student'],
            ['roles_id' => 4, 'roles_name' => 'OSS Staff'],
        ]);

        DB::table('status_tbl')->insertOrIgnore([
            ['status_id' => 1, 'status_name' => 'Active'],
            ['status_id' => 2, 'status_name' => 'Inactive'],
        ]);

        DB::table('program_tbl')->insertOrIgnore([['program_id' => 1, 'program_name' => 'BSIT']]);
        DB::table('year_tbl')->insertOrIgnore([['year_id' => 1, 'year' => '1']]);

        DB::table('violation_category_tbl')->insertOrIgnore([
            ['violation_category_id' => 1, 'category_name' => 'Student Conduct'],
        ]);

        DB::table('violation_type_tbl')->insertOrIgnore([
            [
                'violation_type_id' => 1,
                'violation_type' => 'Cheating',
                'violation_category_id' => 1,
                'severity_level' => 1,
            ],
        ]);

        DB::table('disciplinary_sanctions')->insertOrIgnore([
            [
                'disciplinary_sanction_id' => 1,
                'violation_type_id' => 1,
                'offense_level' => '1st Offense',
                'disciplinary_sanction' => 'Community Service',
            ],
        ]);
    }

    private function createTables(): void
    {
        $tables = [
            'admins' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('username')->nullable();
                $table->string('email')->unique();
                $table->string('password');
                $table->string('remember_token', 100)->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
            },
            'admin_info_tbl' => function (Blueprint $table): void {
                $table->unsignedBigInteger('admin_id')->primary();
                $table->unsignedBigInteger('role_id')->nullable();
                $table->string('firstname')->nullable();
                $table->string('middlename')->nullable();
                $table->string('lastname')->nullable();
                // SQLite treats `Position` and `position` as the SAME column, so
                // only one casing can exist in this fixture.
                $table->string('Position')->nullable();
                $table->string('designation')->nullable();
                $table->string('role')->nullable();
                $table->string('title')->nullable();
            },
            'security' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('email')->unique();
                $table->string('password');
                $table->string('remember_token', 100)->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
            },
            'users_tbl' => function (Blueprint $table): void {
                $table->increments('user_id');
                $table->string('student_number')->nullable();
                $table->string('first_name')->nullable();
                $table->string('middle_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->unsignedInteger('roles_id')->nullable();
                $table->unsignedInteger('status_id')->nullable();
                $table->timestamps();
            },
            'roles_tbl' => function (Blueprint $table): void {
                $table->increments('roles_id');
                $table->string('roles_name');
            },
            'status_tbl' => function (Blueprint $table): void {
                $table->increments('status_id');
                $table->string('status_name');
            },
            'student_info_tbl' => function (Blueprint $table): void {
                $table->unsignedBigInteger('user_id')->primary();
                $table->unsignedBigInteger('program_id')->nullable();
                $table->unsignedBigInteger('year_id')->nullable();
                $table->unsignedBigInteger('section_id')->nullable();
                $table->unsignedBigInteger('student_status_id')->nullable();
            },
            'program_tbl' => function (Blueprint $table): void {
                $table->increments('program_id');
                $table->string('program_name')->nullable();
            },
            'year_tbl' => function (Blueprint $table): void {
                $table->increments('year_id');
                $table->string('year')->nullable();
            },
            'section_tbl' => function (Blueprint $table): void {
                $table->increments('section_id');
                $table->string('section_name')->nullable();
            },
            'student_status_tbl' => function (Blueprint $table): void {
                $table->increments('student_status_id');
                $table->string('status_name')->nullable();
            },
            'violation_category_tbl' => function (Blueprint $table): void {
                $table->increments('violation_category_id');
                $table->string('category_name');
            },
            'violation_type_tbl' => function (Blueprint $table): void {
                $table->increments('violation_type_id');
                $table->string('violation_type');
                $table->text('violation_description')->nullable();
                $table->unsignedBigInteger('violation_category_id')->nullable();
                $table->string('severity_level')->nullable();
            },
            'violation_tbl' => function (Blueprint $table): void {
                $table->increments('violation_id');
                $table->string('student_number')->nullable();
                $table->string('violation_type')->nullable();
                $table->dateTime('violation_date')->nullable();
                $table->text('description')->nullable();
                $table->unsignedInteger('recorder_id')->nullable();
                $table->string('recorder_type')->nullable();
                $table->string('recorder_name')->nullable();
                $table->string('status')->nullable();
                $table->unsignedBigInteger('recorded_by_security_id')->nullable();
                $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
                $table->dateTime('reviewed_at')->nullable();
                $table->text('review_remarks')->nullable();
            },
            'disciplinary_sanctions' => function (Blueprint $table): void {
                $table->increments('disciplinary_sanction_id');
                $table->unsignedBigInteger('violation_type_id')->nullable();
                $table->string('offense_level')->nullable();
                $table->string('disciplinary_sanction')->nullable();
            },
            'violation_evidence_tbl' => function (Blueprint $table): void {
                $table->increments('evidence_id');
                $table->string('evidence_code', 32)->nullable();
                $table->string('capture_session_id')->nullable();
                $table->unsignedBigInteger('violation_id')->nullable();
                $table->string('disk_path', 500)->nullable();
                $table->string('original_name', 255)->nullable();
                $table->string('mime_type', 120)->nullable();
                $table->string('capture_method')->nullable();
                $table->boolean('watermarked')->default(false);
                $table->boolean('server_watermarked')->default(false);
                $table->timestamp('captured_at')->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->string('ip_address')->nullable();
                $table->string('user_agent')->nullable();
                $table->string('uploader_type')->nullable();
                $table->unsignedBigInteger('uploader_id')->nullable();
                $table->char('sha256_hash', 64)->nullable();
                $table->timestamp('created_at')->nullable();
            },
            'evidence_security_events_tbl' => function (Blueprint $table): void {
                $table->increments('event_id');
                $table->string('capture_session_id')->nullable();
                $table->unsignedBigInteger('violation_id')->nullable();
                $table->unsignedBigInteger('evidence_id')->nullable();
                $table->string('event_type')->nullable();
                $table->string('severity')->nullable();
                $table->string('actor_type')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('ip_address')->nullable();
                $table->string('user_agent')->nullable();
                $table->text('meta')->nullable();
                $table->timestamp('created_at')->nullable();
            },
            'sanction_requests_tbl' => function (Blueprint $table): void {
                $table->increments('request_id');
                $table->string('student_number');
                $table->unsignedInteger('violation_type_id');
                $table->dateTime('request_date')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('status')->nullable();
            },
            'student_sanction_records_tbl' => function (Blueprint $table): void {
                $table->increments('record_id');
                $table->string('student_number')->nullable();
                $table->unsignedBigInteger('violation_id')->nullable();
                $table->unsignedBigInteger('assigned_sanction_id')->nullable();
                $table->unsignedBigInteger('assigned_by_admin_id')->nullable();
                $table->string('status')->nullable();
                $table->timestamp('date_assigned')->nullable();
            },
            'audit_logs' => function (Blueprint $table): void {
                $table->increments('audit_log_id');
                $table->string('actor_type')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('action');
                $table->string('module')->nullable();
                $table->text('description')->nullable();
                $table->timestamp('created_at')->nullable();
            },
            'notifications_tbl' => function (Blueprint $table): void {
                $table->increments('notification_id');
                $table->string('student_number')->nullable();
                $table->string('recipient_type')->nullable();
                $table->unsignedBigInteger('recipient_id')->nullable();
                $table->string('notification_type')->nullable();
                $table->string('title')->nullable();
                $table->text('message')->nullable();
                $table->string('target_type')->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->string('link')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('created_at')->nullable();
            },
            'admin_notifications_tbl' => function (Blueprint $table): void {
                $table->increments('admin_notification_id');
                $table->text('message')->nullable();
                $table->string('title')->nullable();
                $table->string('notification_type')->nullable();
                $table->string('priority')->nullable();
                $table->string('target_type')->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->string('recipient_type')->nullable();
                $table->unsignedBigInteger('recipient_id')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('created_at')->nullable();
            },
            'announcements_tbl' => function (Blueprint $table): void {
                $table->increments('announcement_id');
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->string('title')->nullable();
                $table->longText('content')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            },
        ];

        foreach ($tables as $name => $definition) {
            if (! Schema::hasTable($name)) {
                Schema::create($name, $definition);
            }
        }
    }
}
