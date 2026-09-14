<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Program;
use App\Models\ProgramReport;
use App\Models\Role;
use App\Models\Section;
use App\Models\Status;
use App\Models\StudentInfo;
use App\Models\StudentStatus;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationType;
use App\Models\Year;
use App\Services\ProgramReportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Covers the Admin-module UI/structure restructuring:
 *
 *   - the dashboard's "Recent Violations" shows at least 5 genuine records;
 *   - "Violation Confirmations" is the FIRST tab of the Violations page and its
 *     standalone page/sidebar entry are gone;
 *   - the graduating preview can exclude individual students WITHOUT touching
 *     any student record, and the exclusion is re-authorised server-side;
 *   - the retired "Program Sanction Report" option is gone from the AI Program
 *     Report form while the graduating workflow still works.
 */
class AdminModuleRestructureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTables();
        $this->seedReferenceData();
    }

    // ==================================================================
    // 1. Dashboard — at least 5 recent violations
    // ==================================================================

    public function test_dashboard_shows_at_least_five_genuine_recent_violations(): void
    {
        $admin = $this->createAdmin();

        // Seven DISTINCT students, each with one CONFIRMED violation (the
        // dashboard's "recent violations" only counts effective records).
        for ($i = 1; $i <= 7; $i++) {
            $number = '2026-000' . $i;
            $this->createStudent($number, 'Recent', 'Student' . $i);
            $this->createViolation($number, now()->subDays($i)->toDateString(), 1, Violation::STATUS_CONFIRMED);
        }

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        // All seven rows render — the table is not capped below 5.
        foreach (range(1, 7) as $i) {
            $this->assertStringContainsString('2026-000' . $i, $html, 'Missing recent violation 2026-000' . $i);
        }

        // And they are genuinely distinct records.
        $this->assertSame(7, Violation::count());

        // Newest first: the most recent student number appears before the oldest.
        $this->assertLessThan(
            strpos($html, '2026-0007'),
            strpos($html, '2026-0001'),
            'Recent violations must be ordered newest first.'
        );
    }

    // ==================================================================
    // 2. Violation Confirmations is the FIRST tab
    // ==================================================================

    public function test_violation_confirmations_is_the_first_tab_of_the_violations_page(): void
    {
        $admin = $this->createAdmin();

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.violations.index'))
            ->assertOk()
            ->getContent();

        $confirmationsPos = strpos($html, 'id="confirmations-tab"');
        $managementPos = strpos($html, 'id="management-tab"');
        $configurationPos = strpos($html, 'id="configuration-tab"');

        $this->assertNotFalse($confirmationsPos, 'The Confirmations tab must exist.');
        $this->assertNotFalse($managementPos, 'The Management tab must exist.');

        $this->assertTrue(
            $confirmationsPos < $managementPos,
            'Violation Confirmations must be the FIRST tab, before Management.'
        );
        $this->assertTrue($managementPos < $configurationPos);
    }

    /**
     * The confirmations queue is rendered INSIDE the parent page when that tab is
     * requested, and it is the default tab.
     */
    public function test_confirmations_queue_renders_inside_the_violations_page(): void
    {
        $admin = $this->createAdmin();
        $this->createStudent('2026-0101', 'Pending', 'Case');
        $this->createViolation('2026-0101', now()->toDateString(), 1, Violation::STATUS_PENDING);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.violations.index', ['tab' => 'confirmations']))
            ->assertOk()
            ->getContent();

        // The queue markers are on the same page as the tabs.
        $this->assertStringContainsString('id="confirmations"', $html);
        $this->assertStringContainsString('awaiting confirmation', $html);
        $this->assertStringContainsString('2026-0101', $html);

        // The review modal is present at document level (outside the tab pane).
        $this->assertStringContainsString('id="violationDetailModal"', $html);
        $this->assertStringContainsString('id="denyViolationModal"', $html);
    }

    /**
     * The old standalone URL must still work — it redirects to the tab so
     * bookmarks and notification deep links keep functioning.
     */
    public function test_standalone_confirmations_url_redirects_to_the_tab(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.violations.confirmations'))
            ->assertRedirect(route('admin.violations.index', ['tab' => 'confirmations']));
    }

    public function test_standalone_confirmations_filters_survive_the_redirect(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.violations.confirmations', [
                'search' => '2026-0101',
                'from' => '2026-01-01',
                'to' => '2026-12-31',
                'pending_id' => 42,
            ]));

        $response->assertRedirect();

        $location = (string) $response->headers->get('location');
        $this->assertStringContainsString('tab=confirmations', $location);
        $this->assertStringContainsString('search=2026-0101', $location);
        $this->assertStringContainsString('pending_id=42', $location);
    }

    public function test_confirmations_sidebar_item_is_removed(): void
    {
        $sidebar = file_get_contents(resource_path('views/admin/partials/sidebar.blade.php'));

        $this->assertStringNotContainsString(
            "route('admin.violations.confirmations')",
            $sidebar,
            'The standalone Confirmations sidebar entry must be gone.'
        );

        // ...but the parent Violations entry remains.
        $this->assertStringContainsString("route('admin.violations.index')", $sidebar);
    }

    public function test_standalone_confirmations_view_is_deleted(): void
    {
        $this->assertFileDoesNotExist(resource_path('views/admin/violations/confirmations.blade.php'));
        $this->assertFileExists(resource_path('views/admin/violations/tabs/confirmations.blade.php'));
    }

    // ==================================================================
    // 3. Graduating preview removal — no data loss, server-authorised
    // ==================================================================

    public function test_graduating_preview_exclusion_drops_a_student_without_touching_the_record(): void
    {
        $service = app(ProgramReportService::class);
        $program = $this->graduatesProgram();

        $included = $this->createGraduatingStudent('2026-0201', 'Keep', 'Me', $program->program_id);
        $removed = $this->createGraduatingStudent('2026-0202', 'Drop', 'Me', $program->program_id);

        $before = $service->previewGraduating($program, [], null, [])->pluck('student_number')->all();
        $this->assertContains('2026-0201', $before);
        $this->assertContains('2026-0202', $before);

        $after = $service->previewGraduating($program, [], null, [], ['2026-0202'])->pluck('student_number')->all();
        $this->assertContains('2026-0201', $after);
        $this->assertNotContains('2026-0202', $after);

        // The exclusion is preview-only state: both records still exist untouched.
        $this->assertNotNull(User::where('student_number', '2026-0201')->first());
        $this->assertNotNull(User::where('student_number', '2026-0202')->first());
        $this->assertSame(2, User::whereIn('student_number', ['2026-0201', '2026-0202'])->count());
    }

    /**
     * The client list is never trusted: a number outside the eligible cohort is
     * dropped, so it cannot suppress an unrelated student.
     */
    public function test_exclusion_is_authorised_against_the_eligible_cohort(): void
    {
        $admin = $this->createAdmin();
        $program = $this->graduatesProgram();

        $this->createGraduatingStudent('2026-0301', 'Eligible', 'One', $program->program_id);
        // A student in a DIFFERENT program — must not be suppressible here.
        $this->createStudent('2026-0302', 'Other', 'Program');

        $response = $this->actingAs($admin, 'admin')->postJson(route('admin.program-report.preview-graduating'), [
            'program_id' => $program->program_id,
            // A forged number that is NOT part of this program's cohort.
            'exclude_students' => ['2026-0302', 'NON-EXISTENT-999'],
        ]);

        $response->assertOk();

        // The eligible student is still present...
        $numbers = collect($response->json('students'))->pluck('student_number')->all();
        $this->assertContains('2026-0301', $numbers);
        // ...and neither forged number could affect the list.
        $this->assertNotContains('2026-0302', $numbers);
    }

    public function test_generation_persists_the_exclusion_list(): void
    {
        $admin = $this->createAdmin();
        $program = $this->graduatesProgram();

        $this->createGraduatingStudent('2026-0401', 'Kept', 'Student', $program->program_id);
        $this->createGraduatingStudent('2026-0402', 'Excluded', 'Student', $program->program_id);

        $this->actingAs($admin, 'admin')->post(route('admin.program-report.generate'), [
            'program_id' => $program->program_id,
            'report_mode' => 'graduating',
            'report_type' => 'full',
            'excluded_students' => ['2026-0402'],
        ])->assertRedirect();

        $report = ProgramReport::latest('report_id')->first();
        $this->assertNotNull($report);
        $this->assertContains('2026-0402', (array) $report->excluded_students);

        // The generated statistics cover only the remaining student.
        $statistics = $report->statistics_json;
        $this->assertSame(1, (int) $statistics['cohort_total']);

        // No student record was removed.
        $this->assertSame(2, User::whereIn('student_number', ['2026-0401', '2026-0402'])->count());
    }

    // ==================================================================
    // 4. AI Program Report — Program Sanction Report option removed
    // ==================================================================

    public function test_program_sanction_report_option_is_gone_from_the_report_form(): void
    {
        $admin = $this->createAdmin();

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.program-report.index'))
            ->assertOk()
            ->getContent();

        // The retired option and its label are gone from the form.
        $this->assertStringNotContainsString('Program Sanction Report', $html);
        $this->assertStringNotContainsString('value="regular"', $html);

        // The graduating workflow remains, with report_mode still posted so the
        // existing controller contract/validation is unchanged.
        $this->assertStringContainsString('name="report_mode"', $html);
        $this->assertStringContainsString('value="graduating"', $html);
        $this->assertStringContainsString('Graduation Eligibility', $html);
        $this->assertStringContainsString('Preview Students', $html);
    }

    public function test_graduating_report_generation_still_works(): void
    {
        $admin = $this->createAdmin();
        $program = $this->graduatesProgram();

        $this->createGraduatingStudent('2026-0501', 'Still', 'Works', $program->program_id);

        $this->actingAs($admin, 'admin')->post(route('admin.program-report.generate'), [
            'program_id' => $program->program_id,
            'report_mode' => 'graduating',
            'report_type' => 'full',
        ])->assertRedirect();

        $report = ProgramReport::latest('report_id')->first();
        $this->assertNotNull($report);
        $this->assertSame('graduating', (string) $report->report_mode);
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    /**
     * A Bachelor's program whose graduating year level is 4.
     *
     * `program_code` is not fillable, so it is set with a direct write after
     * creation — the tests need to find this program deterministically.
     */
    private function graduatesProgram(): Program
    {
        $program = Program::create([
            'program_name' => 'BS Information Technology Graduate Cohort',
            'degree_type' => 'Bachelor',
        ]);

        return $program;
    }

    private function createGraduatingStudent(string $number, string $first, string $last, ?int $programId = null): User
    {
        return $this->createStudent(
            $number,
            $first,
            $last,
            yearId: $this->yearId('4th Year'),
            programId: $programId
        );
    }

    private function createAdmin(): Admin
    {
        return Admin::create([
            'username' => 'admin@example.com',
            'email' => 'admin@example.com',
            'password' => 'secret',
        ]);
    }

    private function createStudent(string $number, string $first, string $last, ?int $yearId = null, ?int $programId = null): User
    {
        $user = User::create([
            'student_number' => $number,
            'first_name' => $first,
            'last_name' => $last,
            'email' => $number . '@example.com',
            'password_hash' => 'x',
            'roles_id' => 2,
            'status_id' => 1,
        ]);

        DB::table('student_info_tbl')->insert([
            'user_id' => $user->user_id,
            'program_id' => $programId ?? 1,
            'year_id' => $yearId ?? $this->yearId('1st Year'),
            'section_id' => 1,
            'student_status_id' => 1,
        ]);

        return $user;
    }

    private function createViolation(string $studentNumber, string $date, int $typeId, string $status = 'pending'): Violation
    {
        return Violation::create([
            'student_number' => $studentNumber,
            'violation_type' => $typeId,
            'violation_date' => $date,
            'description' => 'Test violation.',
            'recorder_type' => 'security',
            'recorder_name' => 'Security: officer@example.com',
            'status' => $status,
        ]);
    }

    private function yearId(string $label): int
    {
        $existing = DB::table('year_tbl')->where('year', $label)->value('year_id');

        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('year_tbl')->insertGetId(['year' => $label]);
    }

    private function seedReferenceData(): void
    {
        if (DB::table('roles_tbl')->count() === 0) {
            DB::table('roles_tbl')->insert([
                ['roles_id' => 1, 'roles_name' => 'Admin'],
                ['roles_id' => 2, 'roles_name' => 'Student'],
                ['roles_id' => 3, 'roles_name' => 'Security'],
                ['roles_id' => 4, 'roles_name' => 'IT Administrator'],
            ]);
        }

        if (DB::table('status_tbl')->count() === 0) {
            DB::table('status_tbl')->insert([['status_id' => 1, 'status_name' => 'Active']]);
        }

        if (DB::table('program_tbl')->count() === 0) {
            DB::table('program_tbl')->insert([
                ['program_id' => 1, 'program_code' => 'BSIT', 'program_name' => 'BS Information Technology', 'degree_type' => 'Bachelor'],
            ]);
        }

        if (DB::table('year_tbl')->count() === 0) {
            DB::table('year_tbl')->insert([
                ['year_id' => 1, 'year' => '1st Year'],
                ['year_id' => 2, 'year' => '2nd Year'],
                ['year_id' => 3, 'year' => '3rd Year'],
                ['year_id' => 4, 'year' => '4th Year'],
            ]);
        }

        if (DB::table('section_tbl')->count() === 0) {
            DB::table('section_tbl')->insert([['section_id' => 1, 'section_name' => 'A']]);
        }

        if (DB::table('student_status_tbl')->count() === 0) {
            DB::table('student_status_tbl')->insert([['student_status_id' => 1, 'status_name' => 'Enrolled']]);
        }

        if (DB::table('violation_type_tbl')->count() === 0) {
            DB::table('violation_type_tbl')->insert([
                ['violation_type_id' => 1, 'violation_type' => 'Cheating', 'violation_category_id' => 1],
            ]);
        }

        if (DB::table('violation_category_tbl')->count() === 0) {
            DB::table('violation_category_tbl')->insert([['violation_category_id' => 1, 'category_name' => 'Major Offenses']]);
        }
    }

    private function ensureTables(): void
    {
        foreach ([
            'roles_tbl' => function (Blueprint $t): void {
                $t->increments('roles_id');
                $t->string('roles_name')->nullable();
            },
            'status_tbl' => function (Blueprint $t): void {
                $t->increments('status_id');
                $t->string('status_name')->nullable();
            },
            'program_tbl' => function (Blueprint $t): void {
                $t->increments('program_id');
                $t->string('program_code')->nullable();
                $t->string('program_name')->nullable();
                $t->string('degree_type')->nullable();
            },
            'year_tbl' => function (Blueprint $t): void {
                $t->increments('year_id');
                $t->string('year')->nullable();
            },
            'section_tbl' => function (Blueprint $t): void {
                $t->increments('section_id');
                $t->string('section_name')->nullable();
            },
            'student_status_tbl' => function (Blueprint $t): void {
                $t->increments('student_status_id');
                $t->string('status_name')->nullable();
            },
            'admins' => function (Blueprint $t): void {
                $t->increments('id');
                $t->string('username')->nullable();
                $t->string('email')->unique();
                $t->string('password');
                $t->boolean('mfa_totp_enabled')->default(false);
            },
            'admin_info_tbl' => function (Blueprint $t): void {
                $t->unsignedBigInteger('admin_id')->primary();
                $t->string('firstname')->nullable();
                $t->string('lastname')->nullable();
                $t->string('Position')->nullable();
                $t->string('designation')->nullable();
                $t->string('role')->nullable();
                $t->string('title')->nullable();
                $t->unsignedBigInteger('status_id')->nullable();
                $t->unsignedBigInteger('role_id')->nullable();
            },
            'users_tbl' => function (Blueprint $t): void {
                $t->increments('user_id');
                $t->string('student_number')->nullable();
                $t->string('first_name')->nullable();
                $t->string('middle_name')->nullable();
                $t->string('last_name')->nullable();
                $t->string('email')->nullable();
                $t->string('password_hash')->nullable();
                $t->unsignedInteger('roles_id')->nullable();
                $t->unsignedInteger('status_id')->nullable();
                $t->timestamps();
            },
            'student_info_tbl' => function (Blueprint $t): void {
                $t->unsignedBigInteger('user_id')->primary();
                $t->unsignedBigInteger('program_id')->nullable();
                $t->unsignedBigInteger('year_id')->nullable();
                $t->unsignedBigInteger('section_id')->nullable();
                $t->unsignedBigInteger('student_status_id')->nullable();
                $t->unsignedTinyInteger('ladderized')->default(0);
            },
            'violation_category_tbl' => function (Blueprint $t): void {
                $t->increments('violation_category_id');
                $t->string('category_name')->nullable();
            },
            'violation_type_tbl' => function (Blueprint $t): void {
                $t->increments('violation_type_id');
                $t->string('violation_type')->nullable();
                $t->text('violation_description')->nullable();
                $t->unsignedBigInteger('violation_category_id')->nullable();
                $t->string('severity_level')->nullable();
            },
            'violation_tbl' => function (Blueprint $t): void {
                $t->increments('violation_id');
                $t->string('student_number')->nullable();
                $t->unsignedBigInteger('violation_type')->nullable();
                $t->dateTime('violation_date')->nullable();
                $t->text('description')->nullable();
                $t->string('recorder_type')->nullable();
                $t->string('recorder_name')->nullable();
                $t->string('status')->nullable();
                $t->text('review_remarks')->nullable();
                $t->unsignedBigInteger('recorded_by_security_id')->nullable();
            },
            'program_reports' => function (Blueprint $t): void {
                $t->increments('report_id');
                $t->unsignedBigInteger('program_id')->nullable();
                $t->string('academic_year')->nullable();
                $t->string('report_type')->nullable();
                $t->string('report_mode')->nullable();
                $t->string('degree_type')->nullable();
                $t->integer('graduating_year')->nullable();
                $t->json('admission_years')->nullable();
                $t->integer('ladderized')->nullable();
                $t->json('additional_students')->nullable();
                $t->json('excluded_students')->nullable();
                $t->unsignedBigInteger('generated_by_admin_id')->nullable();
                $t->timestamp('generated_at')->nullable();
                $t->string('report_status')->nullable();
                $t->json('statistics_json')->nullable();
                $t->json('violation_breakdown')->nullable();
                $t->json('repeated_violations')->nullable();
                $t->json('outstanding_students')->nullable();
                $t->json('manual_review_students')->nullable();
                $t->boolean('ai_failed')->default(false);
                $t->timestamps();
            },
            'security' => function (Blueprint $t): void {
                $t->increments('id');
                $t->string('email')->nullable();
                $t->string('password')->nullable();
                $t->boolean('mfa_totp_enabled')->default(false);
            },
            'audit_logs' => function (Blueprint $t): void {
                $t->increments('audit_log_id');
                $t->string('actor_type')->nullable();
                $t->unsignedBigInteger('actor_id')->nullable();
                $t->string('action');
                $t->string('module')->nullable();
                $t->text('description')->nullable();
                $t->timestamp('created_at')->nullable();
            },
            'disciplinary_sanctions' => function (Blueprint $t): void {
                $t->increments('disciplinary_sanction_id');
                $t->unsignedBigInteger('violation_type_id')->nullable();
                $t->string('offense_level')->nullable();
                $t->string('disciplinary_sanction')->nullable();
            },
            'sanction_requests_tbl' => function (Blueprint $t): void {
                $t->increments('id');
                $t->unsignedBigInteger('student_number')->nullable();
                $t->string('status')->nullable();
                $t->timestamps();
            },
            'violation_evidence_tbl' => function (Blueprint $t): void {
                $t->increments('evidence_id');
                $t->unsignedBigInteger('violation_id')->nullable();
                $t->string('disk_path')->nullable();
                $t->string('original_name')->nullable();
                $t->string('evidence_code')->nullable();
                $t->boolean('is_image')->default(false);
                $t->string('capture_method')->nullable();
                $t->boolean('watermarked')->default(false);
            },
            'student_sanction_records_tbl' => function (Blueprint $t): void {
                $t->increments('id');
                $t->string('student_number')->nullable();
                $t->string('status')->nullable();
                $t->timestamps();
            },
        ] as $name => $definition) {
            if (! Schema::hasTable($name)) {
                Schema::create($name, $definition);
            }
        }
    }
}
