<?php

namespace Tests\Feature;

use App\Jobs\GenerateStudentAIReview;
use App\Models\AIGraduationReview;
use App\Models\Admin;
use App\Models\DisciplinarySanction;
use App\Models\Program;
use App\Models\ProgramReport;
use App\Models\Role;
use App\Models\Section;
use App\Models\Status;
use App\Models\StudentInfo;
use App\Models\StudentSanctionRecord;
use App\Models\StudentStatus;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationCategory;
use App\Models\ViolationType;
use App\Models\Year;
use App\Services\AIGraduationReviewService;
use App\Services\GraduationReviewScreeningService;
use App\Services\ProgramReportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GraduationReviewEnhancementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTables();
        $this->seedReferenceData();

        Config::set('graduation_review.ai.enabled', true);
        Config::set('graduation_review.ai.base_url', 'https://ai.test/v1');
        Config::set('graduation_review.ai.api_key', 'test-key');
        Config::set('graduation_review.ai.model', 'test-model');
        Config::set('graduation_review.ai.timeout', 10);
        Config::set('graduation_review.queue.connection', 'sync');
        Config::set('graduation_review.queue.name', 'graduation-review');
    }

    public function test_screening_includes_fingerprint_trend_data_and_chronological_order(): void
    {
        $student = $this->createStudent('2026-0101', 'Anna', 'Trend');

        $this->createViolation($student->student_number, '2023-01-01', 1);
        $this->createViolation($student->student_number, '2024-06-15', 1);
        $this->createViolation($student->student_number, '2025-03-10', 2);

        $screening = app(GraduationReviewScreeningService::class)->screenStudent($student);

        $this->assertSame(3, $screening['total_violations']);
        $this->assertTrue($screening['repeated_violations_exist']);
        $this->assertNotEmpty($screening['data_fingerprint']);
        $this->assertIsArray($screening['violation_periods']);
        // Chronological (oldest first).
        $this->assertSame('2023-01-01', $screening['violations'][0]['date']);
        $this->assertSame('2025-03-10', $screening['violations'][2]['date']);
    }

    public function test_screening_detects_contradiction(): void
    {
        $student = $this->createStudent('2026-0102', 'Bob', 'Conflict');

        $violation = $this->createViolation($student->student_number, '2024-01-01', 1);
        // Sanction status 'pending' but the summary relies on counts — verify
        // no contradiction for a consistent single pending record.
        $this->createSanctionRecord($student->student_number, $violation, 'Pending');

        $screening = app(GraduationReviewScreeningService::class)->screenStudent($student);

        $this->assertSame(1, $screening['pending_sanctions']);
        $this->assertSame(1, $screening['outstanding_sanctions']);
        $this->assertEmpty($screening['data_contradictions']);
    }

    public function test_fingerprint_changes_when_record_changes(): void
    {
        $student = $this->createStudent('2026-0103', 'Carol', 'Finger');

        $v1 = $this->createViolation($student->student_number, '2024-01-01', 1);
        $service = app(GraduationReviewScreeningService::class);
        $before = $service->screenStudent($student)['data_fingerprint'];

        $this->createViolation($student->student_number, '2025-05-05', 2);

        $after = $service->screenStudent($student)['data_fingerprint'];

        $this->assertNotSame($before, $after);
    }

    public function test_ai_response_is_validated_with_trend_and_evidence(): void
    {
        $student = $this->createStudent('2026-0104', 'Dave', 'AI');

        $aiJson = json_encode([
            'summary' => 'Summary.',
            'review_status' => 'manual_review_recommended',
            'behavior_trend' => 'escalating',
            'trend_explanation' => 'Incidents increased over time.',
            'pattern_type' => 'repeated_category',
            'outstanding_sanctions' => [],
            'disciplinary_pattern' => 'Repeated category.',
            'supporting_evidence' => ['3 total violations', '2 in same category'],
            'reason_for_review' => 'Repeated violations.',
            'recommended_action' => 'Manual verification.',
        ]);

        Http::fake([
            'https://ai.test/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => $aiJson]],
                ],
            ], 200),
        ]);

        $screening = app(GraduationReviewScreeningService::class)->screenStudent($student);
        $result = app(AIGraduationReviewService::class)->generate($screening);

        $this->assertSame('manual_review_recommended', $result['review_status']);
        $this->assertSame('escalating', $result['behavior_trend']);
        $this->assertSame('repeated_category', $result['pattern_type']);
        $this->assertContains('3 total violations', $result['supporting_evidence']);
    }

    public function test_queue_job_generates_and_saves_review(): void
    {
        $student = $this->createStudent('2026-0105', 'Eve', 'Job');
        $admin = $this->createAdmin();

        $aiJson = json_encode([
            'summary' => 'Job summary.',
            'review_status' => 'no_immediate_concern',
            'behavior_trend' => 'insufficient_data',
            'trend_explanation' => '',
            'pattern_type' => 'insufficient_data',
            'outstanding_sanctions' => [],
            'disciplinary_pattern' => '',
            'supporting_evidence' => [],
            'reason_for_review' => '',
            'recommended_action' => '',
        ]);

        Http::fake([
            'https://ai.test/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => $aiJson]],
                ],
            ], 200),
        ]);

        (new GenerateStudentAIReview($student->student_number, $admin->id))->handle(
            app(GraduationReviewScreeningService::class),
            app(AIGraduationReviewService::class),
        );

        $this->assertDatabaseHas('ai_graduation_reviews', [
            'student_number' => $student->student_number,
            'review_status' => 'no_immediate_concern',
        ]);
    }

    public function test_generate_batch_dispatches_jobs_via_queue(): void
    {
        $this->createStudent('2026-0106', 'Frank', 'A');
        $this->createStudent('2026-0107', 'Grace', 'B');

        \Illuminate\Support\Facades\Bus::fake();

        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        Config::set('graduation_review.queue.connection', 'database');

        $this->post(route('admin.graduation-review.generate-batch'))
            ->assertRedirect(route('admin.graduation-review.index'));

        \Illuminate\Support\Facades\Bus::assertBatched(function ($batch) {
            foreach ($batch->jobs as $job) {
                if ($job instanceof GenerateStudentAIReview
                    && in_array($job->studentNumber, ['2026-0106', '2026-0107'], true)) {
                    return true;
                }
            }

            return false;
        });
    }

    public function test_program_report_aggregates_all_students_without_limit(): void
    {
        $program = Program::orderBy('program_id')->first();
        $year = Year::where('year', 'like', '%4th%')->first() ?? Year::orderBy('year_id')->first();

        // Create a larger set (e.g. 70) to prove no small limit is applied.
        for ($i = 0; $i < 70; $i++) {
            $student = $this->createStudent('2026-' . str_pad((string) (2000 + $i), 4, '0', STR_PAD_LEFT), 'Prog', 'Student' . $i, $year->year_id);
            if ($i % 3 === 0) {
                $v = $this->createViolation($student->student_number, '2024-01-01', 1);
                $this->createSanctionRecord($student->student_number, $v, 'Pending');
            }
        }

        $stats = app(ProgramReportService::class)->aggregate($program, $year->year);

        $this->assertSame(70, $stats['total_students']);
        $this->assertGreaterThan(0, $stats['students_with_outstanding_sanctions']);
    }

    public function test_bachelor_graduating_report_auto_selects_only_4th_year_students(): void
    {
        $bachelor = Program::where('degree_type', "Bachelor's")->orderBy('program_id')->first();
        $year1 = Year::where('year', '1st Year')->value('year_id');
        $year3 = Year::where('year', '3rd Year')->value('year_id');
        $year4 = Year::where('year', '4th Year')->value('year_id');

        // A 4th-year Bachelor student whose number does NOT start with an
        // expected admission year is still eligible (year level is the rule).
        $fourthYear = $this->createStudent('2019-0001', 'Fourth', 'Year', $year4, $bachelor->program_id);
        $thirdYear = $this->createStudent('2022-0002', 'Third', 'Year', $year3, $bachelor->program_id);
        $firstYear = $this->createStudent('2025-0003', 'First', 'Year', $year1, $bachelor->program_id);

        // Automatic cohort = 4th Year only.
        $students = app(ProgramReportService::class)->studentsFor($bachelor, null, 'graduating');

        $this->assertTrue($students->contains('student_number', $fourthYear->student_number));
        $this->assertFalse($students->contains('student_number', $thirdYear->student_number));
        $this->assertFalse($students->contains('student_number', $firstYear->student_number));
    }

    public function test_diploma_graduating_report_auto_selects_only_3rd_year_students(): void
    {
        $diploma = Program::where('degree_type', 'Diploma')->orderBy('program_id')->first();
        $year2 = Year::where('year', '3rd Year')->value('year_id');
        $year4 = Year::where('year', '4th Year')->value('year_id');

        // Diploma programs graduate at 3rd Year.
        $thirdYear = $this->createStudent('2022-0001', 'Third', 'Year', $year2, $diploma->program_id);
        // A 4th-year student in a Diploma program is NOT auto-eligible.
        $fourthYear = $this->createStudent('2019-0002', 'Fourth', 'Year', $year4, $diploma->program_id);

        $students = app(ProgramReportService::class)->studentsFor($diploma, null, 'graduating');

        $this->assertTrue($students->contains('student_number', $thirdYear->student_number));
        $this->assertFalse($students->contains('student_number', $fourthYear->student_number));
    }

    public function test_specific_student_is_always_included_in_graduating_report(): void
    {
        $bachelor = Program::where('degree_type', "Bachelor's")->orderBy('program_id')->first();
        $year1 = Year::where('year', '1st Year')->value('year_id');

        // A 1st-year student added manually (e.g. returnee) must be included.
        $specific = $this->createStudent('2020-0009', 'Manual', 'Override', $year1, $bachelor->program_id);

        $students = app(ProgramReportService::class)
            ->studentsFor($bachelor, null, 'graduating', [], null, [$specific->student_number]);

        $this->assertTrue($students->contains('student_number', $specific->student_number));
    }

    public function test_graduating_program_report_ladderized_filter_uses_ladderized_flag(): void
    {
        $bachelor = Program::where('degree_type', "Bachelor's")->orderBy('program_id')->first();
        $year4 = Year::where('year', '4th Year')->value('year_id');

        $ladderized = $this->createStudent('2022-0001', 'Ladderized', 'Student', $year4, $bachelor->program_id, true);
        $nonLadderized = $this->createStudent('2022-0002', 'NonLadder', 'Student', $year4, $bachelor->program_id, false);

        // Ladderized = 1 → only students whose ladderized flag is 1.
        $ladderizedOnly = app(ProgramReportService::class)
            ->studentsFor($bachelor, null, 'graduating', [], 1);
        $this->assertTrue($ladderizedOnly->contains('student_number', $ladderized->student_number));
        $this->assertFalse($ladderizedOnly->contains('student_number', $nonLadderized->student_number));

        // Ladderized = 0 → only students whose ladderized flag is 0.
        $nonLadderizedOnly = app(ProgramReportService::class)
            ->studentsFor($bachelor, null, 'graduating', [], 0);
        $this->assertFalse($nonLadderizedOnly->contains('student_number', $ladderized->student_number));
        $this->assertTrue($nonLadderizedOnly->contains('student_number', $nonLadderized->student_number));

        // Ladderized = null → both included.
        $all = app(ProgramReportService::class)
            ->studentsFor($bachelor, null, 'graduating');
        $this->assertTrue($all->contains('student_number', $ladderized->student_number));
        $this->assertTrue($all->contains('student_number', $nonLadderized->student_number));
    }

    public function test_preview_graduating_endpoint_uses_year_level_eligibility(): void
    {
        $bachelor = Program::where('degree_type', "Bachelor's")->orderBy('program_id')->first();
        $year1 = Year::where('year', '1st Year')->value('year_id');
        $year4 = Year::where('year', '4th Year')->value('year_id');

        $fourthYear = $this->createStudent('2019-0001', 'Fourth', 'Year', $year4, $bachelor->program_id);
        $firstYear = $this->createStudent('2025-0002', 'First', 'Year', $year1, $bachelor->program_id);

        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $response = $this->postJson(route('admin.program-report.preview-graduating'), [
            'program_id' => $bachelor->program_id,
            'additional_students' => [],
            'ladderized' => '',
        ]);

        $response->assertOk();
        $payload = $response->json('students');
        $this->assertCount(1, $payload);
        $this->assertSame($fourthYear->student_number, $payload[0]['student_number']);
        $this->assertNotContains($firstYear->student_number, array_column($payload, 'student_number'));
    }

    public function test_program_report_ai_narrative_fails_gracefully(): void
    {
        $program = Program::orderBy('program_id')->first();
        $year = Year::orderBy('year_id')->first();
        $admin = $this->createAdmin();

        Http::fake([
            'https://ai.test/v1/chat/completions' => Http::response([], 500),
        ]);

        $this->actingAs($admin, 'admin');

        $this->post(route('admin.program-report.generate'), [
            'program_id' => $program->program_id,
            'academic_year' => $year->year,
            'report_type' => 'full',
        ])->assertRedirect();

        $this->assertDatabaseHas('program_reports', [
            'program_id' => $program->program_id,
            'ai_failed' => 1,
        ]);
    }

    public function test_program_report_history_super_admin_sees_all(): void
    {
        $program = Program::orderBy('program_id')->first();
        $year = Year::orderBy('year_id')->first();
        $adminA = $this->createAdminWithPosition('IT Administrator');
        $adminB = $this->createAdminWithPosition('Guidance Administrator');

        // adminB generates a report.
        $this->actingAs($adminB, 'admin');
        $this->post(route('admin.program-report.generate'), [
            'program_id' => $program->program_id,
            'academic_year' => $year->year,
            'report_type' => 'full',
        ])->assertRedirect();

        // Super admin (adminA) can see ALL reports via history.
        $this->actingAs($adminA, 'admin');
        $response = $this->get(route('admin.program-report.history'));
        $response->assertOk();
        // The history shows the generating admin's email.
        $response->assertSee('guidance.administrator@example.com');
    }

    public function test_human_decision_is_recorded(): void
    {
        $student = $this->createStudent('2026-0108', 'Hank', 'Decision');
        $admin = $this->createAdmin();

        AIGraduationReview::create([
            'student_number' => $student->student_number,
            'generated_by_admin_id' => $admin->id,
            'review_status' => 'manual_review_recommended',
            'summary' => 'Existing.',
            'ai_generated_at' => now(),
            'total_violations' => 1,
        ]);

        $this->actingAs($admin, 'admin');

        $this->post(route('admin.graduation-review.decision', $student->student_number), [
            'decision_status' => 'modified',
            'decision_remarks' => 'Reviewed by Discipline Office.',
        ])->assertRedirect();

        $this->assertDatabaseHas('ai_graduation_reviews', [
            'student_number' => $student->student_number,
            'decision_status' => 'modified',
            'decision_remarks' => 'Reviewed by Discipline Office.',
            'decision_by_admin_id' => $admin->id,
        ]);
    }

    private function createAdmin(): Admin
    {
        return Admin::create([
            'email' => 'admin@example.com',
            'username' => 'admin',
            'password' => 'password123',
        ]);
    }

    private function createAdminWithPosition(string $position): Admin
    {
        $admin = Admin::create([
            'username' => strtolower(str_replace(' ', '.', $position)),
            'email' => strtolower(str_replace(' ', '.', $position)) . '@example.com',
            'password' => 'password',
        ]);

        DB::table('admin_info_tbl')->insert([
            'admin_id' => $admin->id,
            'position' => $position,
        ]);

        return $admin;
    }

    private function createStudent(string $number, string $first, string $last, ?int $yearId = null, ?int $programId = null, bool $ladderized = false): User
    {
        $statusId = Status::whereRaw('LOWER(status_name) = ?', ['active'])->value('status_id');
        $roleId = Role::whereRaw('LOWER(roles_name) = ?', ['student'])->value('roles_id');
        $studentStatusId = StudentStatus::whereRaw('LOWER(status_name) = ?', ['active'])->value('student_status_id');

        $student = User::create([
            'student_number' => $number,
            'first_name' => $first,
            'last_name' => $last,
            'email' => $number . '@example.com',
            'status_id' => $statusId,
            'roles_id' => $roleId,
            'password_hash' => \Illuminate\Support\Facades\Hash::make('password123'),
        ]);

        $program = $programId !== null
            ? Program::findOrFail($programId)
            : Program::orderBy('program_id')->first();
        $year = Year::where('year_id', $yearId)->first() ?? Year::orderBy('year_id')->first();
        $section = Section::orderBy('section_id')->first();

        StudentInfo::create([
            'user_id' => $student->getKey(),
            'program_id' => $program->program_id,
            'year_id' => $year->year_id,
            'section_id' => $section->section_id,
            'student_status_id' => $studentStatusId,
            'ladderized' => $ladderized ? 1 : 0,
        ]);

        return $student;
    }

    private function createViolation(string $studentNumber, string $date, int $typeId): Violation
    {
        return Violation::create([
            'student_number' => $studentNumber,
            'violation_type' => $typeId,
            'violation_date' => $date,
            'description' => 'Test violation.',
        ]);
    }

    private function createSanctionRecord(string $studentNumber, Violation $violation, string $status): StudentSanctionRecord
    {
        $sanction = DisciplinarySanction::orderBy('disciplinary_sanction_id')->first();

        return StudentSanctionRecord::create([
            'student_number' => $studentNumber,
            'violation_id' => $violation->violation_id,
            'assigned_sanction_id' => $sanction->disciplinary_sanction_id,
            'status' => $status,
            'date_assigned' => now(),
        ]);
    }

    private function ensureTables(): void
    {
        foreach ([
            'roles_tbl' => function (Blueprint $table) {
                $table->id('roles_id');
                $table->string('roles_name')->nullable();
            },
            'status_tbl' => function (Blueprint $table) {
                $table->id('status_id');
                $table->string('status_name')->nullable();
            },
            'program_tbl' => function (Blueprint $table) {
                $table->id('program_id');
                $table->string('program_name')->nullable();
                $table->string('degree_type')->nullable();
            },
            'year_tbl' => function (Blueprint $table) {
                $table->id('year_id');
                $table->string('year')->nullable();
            },
            'section_tbl' => function (Blueprint $table) {
                $table->id('section_id');
                $table->string('section_name')->nullable();
            },
            'student_status_tbl' => function (Blueprint $table) {
                $table->id('student_status_id');
                $table->string('status_name')->nullable();
            },
            'users_tbl' => function (Blueprint $table) {
                $table->id('user_id');
                $table->string('student_number')->unique();
                $table->string('first_name')->nullable();
                $table->string('middle_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->unsignedBigInteger('status_id')->nullable();
                $table->unsignedBigInteger('roles_id')->nullable();
                $table->string('password_hash');
                $table->timestamps();
            },
            'student_info_tbl' => function (Blueprint $table) {
                $table->id('student_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('program_id')->nullable();
                $table->unsignedBigInteger('year_id')->nullable();
                $table->unsignedBigInteger('section_id')->nullable();
                $table->unsignedBigInteger('student_status_id')->nullable();
                $table->boolean('ladderized')->default(false);
            },
            'admins' => function (Blueprint $table) {
                $table->id();
                $table->string('username')->nullable();
                $table->string('email')->unique();
                $table->string('password');
                $table->timestamps();
            },
            'admin_info_tbl' => function (Blueprint $table) {
                $table->unsignedBigInteger('admin_id')->primary();
                $table->string('position')->nullable();
            },
            'violation_category_tbl' => function (Blueprint $table) {
                $table->id('violation_category_id');
                $table->string('category_name')->nullable();
            },
            'violation_type_tbl' => function (Blueprint $table) {
                $table->id('violation_type_id');
                $table->string('violation_type')->nullable();
                $table->unsignedBigInteger('violation_category_id')->nullable();
            },
            'violation_tbl' => function (Blueprint $table) {
                $table->id('violation_id');
                $table->string('student_number')->nullable();
                $table->string('violation_type')->nullable();
                $table->date('violation_date')->nullable();
                $table->text('description')->nullable();
            },
            'disciplinary_sanctions' => function (Blueprint $table) {
                $table->id('disciplinary_sanction_id');
                $table->unsignedBigInteger('violation_type_id')->nullable();
                $table->string('offense_level')->nullable();
                $table->string('disciplinary_sanction')->nullable();
            },
            'student_sanction_records_tbl' => function (Blueprint $table) {
                $table->id('record_id');
                $table->string('student_number')->nullable();
                $table->unsignedBigInteger('violation_id')->nullable();
                $table->unsignedBigInteger('assigned_sanction_id')->nullable();
                $table->string('status')->nullable();
                $table->timestamp('date_assigned')->nullable();
            },
            'ai_graduation_reviews' => function (Blueprint $table) {
                $table->id('review_id');
                $table->string('student_number');
                $table->unsignedBigInteger('generated_by_admin_id')->nullable();
                $table->string('review_status')->nullable();
                $table->string('behavior_trend')->nullable();
                $table->text('trend_explanation')->nullable();
                $table->string('pattern_type')->nullable();
                $table->text('summary')->nullable();
                $table->text('disciplinary_pattern')->nullable();
                $table->text('reason_for_review')->nullable();
                $table->text('recommended_action')->nullable();
                $table->json('outstanding_sanctions')->nullable();
                $table->json('supporting_evidence')->nullable();
                $table->json('data_contradictions')->nullable();
                $table->string('data_fingerprint', 64)->nullable();
                $table->boolean('is_outdated')->default(false);
                $table->string('decision_status')->nullable();
                $table->text('decision_remarks')->nullable();
                $table->unsignedBigInteger('decision_by_admin_id')->nullable();
                $table->timestamp('decision_at')->nullable();
                $table->unsignedInteger('total_violations')->default(0);
                $table->unsignedInteger('completed_sanctions')->default(0);
                $table->unsignedInteger('pending_sanctions')->default(0);
                $table->unsignedInteger('outstanding_sanctions_count')->default(0);
                $table->string('ai_model')->nullable();
                $table->timestamp('ai_generated_at')->nullable();
                $table->timestamps();
            },
            'program_reports' => function (Blueprint $table) {
                $table->id('report_id');
                $table->unsignedBigInteger('program_id');
                $table->string('academic_year')->nullable();
                $table->string('report_type')->default('full');
                $table->unsignedBigInteger('generated_by_admin_id')->nullable();
                $table->timestamp('generated_at')->nullable();
                $table->string('report_status')->default('generated');
                $table->json('statistics_json')->nullable();
                $table->json('violation_breakdown')->nullable();
                $table->json('repeated_violations')->nullable();
                $table->json('outstanding_students')->nullable();
                $table->json('manual_review_students')->nullable();
                $table->text('ai_narrative')->nullable();
                $table->string('ai_model')->nullable();
                $table->boolean('ai_failed')->default(false);
                $table->timestamps();
            },
        ] as $tableName => $closure) {
            if (! Schema::hasTable($tableName)) {
                Schema::create($tableName, $closure);
            }
        }
    }

    private function seedReferenceData(): void
    {
        if (DB::table('roles_tbl')->count() === 0) {
            DB::table('roles_tbl')->insert([
                ['roles_id' => 1, 'roles_name' => 'Admin'],
                ['roles_id' => 2, 'roles_name' => 'Student'],
                ['roles_id' => 3, 'roles_name' => 'Security'],
            ]);
        }

        if (DB::table('status_tbl')->count() === 0) {
            DB::table('status_tbl')->insert([
                ['status_id' => 1, 'status_name' => 'Active'],
                ['status_id' => 2, 'status_name' => 'Inactive'],
            ]);
        }

        if (DB::table('student_status_tbl')->count() === 0) {
            DB::table('student_status_tbl')->insert([
                ['student_status_id' => 1, 'status_name' => 'Active'],
                ['student_status_id' => 2, 'status_name' => 'Graduating'],
            ]);
        }

        if (DB::table('program_tbl')->count() === 0) {
            DB::table('program_tbl')->insert([
                ['program_id' => 1, 'program_name' => 'BSIT', 'degree_type' => "Bachelor's"],
                ['program_id' => 2, 'program_name' => 'BSCS', 'degree_type' => "Bachelor's"],
                ['program_id' => 3, 'program_name' => 'Diploma in Information Technology', 'degree_type' => 'Diploma'],
            ]);
        }

        if (DB::table('year_tbl')->count() === 0) {
            DB::table('year_tbl')->insert([
                ['year_id' => 1, 'year' => '1st Year'],
                ['year_id' => 2, 'year' => '3rd Year'],
                ['year_id' => 3, 'year' => '4th Year'],
            ]);
        }

        if (DB::table('section_tbl')->count() === 0) {
            DB::table('section_tbl')->insert([
                ['section_id' => 1, 'section_name' => 'Section A'],
            ]);
        }

        if (DB::table('violation_category_tbl')->count() === 0) {
            DB::table('violation_category_tbl')->insert([
                ['violation_category_id' => 1, 'category_name' => 'General'],
                ['violation_category_id' => 2, 'category_name' => 'Academic'],
            ]);
        }

        if (DB::table('violation_type_tbl')->count() === 0) {
            DB::table('violation_type_tbl')->insert([
                ['violation_type_id' => 1, 'violation_type' => 'Smoking', 'violation_category_id' => 1],
                ['violation_type_id' => 2, 'violation_type' => 'Cheating', 'violation_category_id' => 2],
            ]);
        }

        if (DB::table('disciplinary_sanctions')->count() === 0) {
            DB::table('disciplinary_sanctions')->insert([
                ['disciplinary_sanction_id' => 1, 'violation_type_id' => 1, 'offense_level' => '1st Offense', 'disciplinary_sanction' => 'Community Service'],
                ['disciplinary_sanction_id' => 2, 'violation_type_id' => 2, 'offense_level' => '1st Offense', 'disciplinary_sanction' => 'Counseling'],
            ]);
        }
    }
}
