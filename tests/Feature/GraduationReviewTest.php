<?php

namespace Tests\Feature;

use App\Models\AIGraduationReview;
use App\Models\Admin;
use App\Models\DisciplinarySanction;
use App\Models\Program;
use App\Models\Role;
use App\Models\Section;
use App\Models\Status;
use App\Models\StudentInfo;
use App\Models\StudentSanctionRecord;
use App\Models\StudentStatus;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationType;
use App\Models\Year;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GraduationReviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTables();
        $this->seedReferenceData();

        // Configure AI (mocked via Http::fake in each test).
        Config::set('graduation_review.ai.enabled', true);
        Config::set('graduation_review.ai.base_url', 'https://ai.test/v1');
        Config::set('graduation_review.ai.api_key', 'test-key');
        Config::set('graduation_review.ai.model', 'test-model');
        Config::set('graduation_review.ai.timeout', 10);
        Config::set('graduation_review.batch_limit', 5);
    }

    public function test_graduation_review_page_requires_admin_auth(): void
    {
        $this->get(route('admin.graduation-review.index'))->assertRedirect();
    }

    public function test_admin_can_view_graduation_review_page_and_screening(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent('2026-0001', 'John', 'Doe');

        // One completed violation/sanction.
        $violation = $this->createViolation($student->student_number, '2024-01-15');
        $this->createSanctionRecord($student->student_number, $violation, 'Completed');

        $this->actingAs($admin, 'admin');

        $this->get(route('admin.graduation-review.index'))
            ->assertOk()
            ->assertSee('AI-Assisted Graduation Disciplinary Review')
            ->assertSee($student->student_number)
            ->assertSee('John Doe');
    }

    public function test_admin_can_view_single_student_detail_with_supporting_records(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent('2026-0002', 'Jane', 'Smith');

        $violation = $this->createViolation($student->student_number, '2024-03-10');
        $this->createSanctionRecord($student->student_number, $violation, 'Pending');

        $this->actingAs($admin, 'admin');

        $this->get(route('admin.graduation-review.show', $student->student_number))
            ->assertOk()
            ->assertSee('System Screening')
            ->assertSee('AI Analysis')
            ->assertSee('Pending')
            ->assertSee('Supporting Disciplinary Records');
    }

    public function test_deterministic_screening_counts_are_correct(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent('2026-0003', 'Alice', 'Brown');

        $v1 = $this->createViolation($student->student_number, '2023-01-01');
        $v2 = $this->createViolation($student->student_number, '2024-02-02');
        $v3 = $this->createViolation($student->student_number, '2025-03-03');

        $this->createSanctionRecord($student->student_number, $v1, 'Completed');
        $this->createSanctionRecord($student->student_number, $v2, 'Completed');
        $this->createSanctionRecord($student->student_number, $v3, 'Pending');

        $this->actingAs($admin, 'admin');

        $this->get(route('admin.graduation-review.show', $student->student_number))
            ->assertOk()
            ->assertSee('3')      // total violations
            ->assertSee('2')      // completed
            ->assertSee('1');     // outstanding/pending
    }

    public function test_generate_ai_review_calls_api_and_stores_result(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent('2026-0004', 'Bob', 'Miller');

        $violation = $this->createViolation($student->student_number, '2024-05-05');
        $this->createSanctionRecord($student->student_number, $violation, 'Pending');

        Http::fake([
            'https://ai.test/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'summary' => 'The student has one recorded disciplinary incident with an outstanding sanction.',
                        'review_status' => 'outstanding_sanction_review',
                        'outstanding_sanctions' => ['Counseling sanction remains pending.'],
                        'disciplinary_pattern' => 'Single recorded incident.',
                        'reason_for_review' => 'An outstanding sanction remains unresolved.',
                        'recommended_action' => 'Verify the outstanding sanction before completing clearance review.',
                    ])]],
                ],
            ], 200),
        ]);

        $this->actingAs($admin, 'admin');

        $this->post(route('admin.graduation-review.generate', $student->student_number))
            ->assertRedirect(route('admin.graduation-review.show', $student->student_number));

        Http::assertSentCount(1);

        $this->assertDatabaseHas('ai_graduation_reviews', [
            'student_number' => $student->student_number,
            'review_status' => 'outstanding_sanction_review',
            'outstanding_sanctions_count' => 1,
            'total_violations' => 1,
        ]);
    }

    public function test_ai_failure_falls_back_to_deterministic_screening(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent('2026-0005', 'Carol', 'White');

        Http::fake([
            'https://ai.test/v1/chat/completions' => Http::response([], 500),
        ]);

        $this->actingAs($admin, 'admin');

        $this->post(route('admin.graduation-review.generate', $student->student_number))
            ->assertRedirect(route('admin.graduation-review.show', $student->student_number))
            ->assertSessionHasErrors('ai');

        $this->assertDatabaseMissing('ai_graduation_reviews', [
            'student_number' => $student->student_number,
        ]);
    }

    public function test_invalid_ai_json_is_rejected_gracefully(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent('2026-0006', 'Dave', 'Green');

        Http::fake([
            'https://ai.test/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'not json at all']]],
            ], 200),
        ]);

        $this->actingAs($admin, 'admin');

        $this->post(route('admin.graduation-review.generate', $student->student_number))
            ->assertRedirect(route('admin.graduation-review.show', $student->student_number))
            ->assertSessionHasErrors('ai');
    }

    public function test_ai_review_reuses_todays_existing_result(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent('2026-0007', 'Eve', 'Black');

        AIGraduationReview::create([
            'student_number' => $student->student_number,
            'generated_by_admin_id' => $admin->id,
            'review_status' => 'no_immediate_concern',
            'summary' => 'Existing summary.',
            'ai_generated_at' => now(),
            'total_violations' => 0,
        ]);

        Http::fake();

        $this->actingAs($admin, 'admin');

        $this->post(route('admin.graduation-review.generate', $student->student_number))
            ->assertRedirect(route('admin.graduation-review.show', $student->student_number))
            ->assertSessionHas('info');

        Http::assertNothingSent();
    }

    public function test_ai_api_key_is_not_exposed_in_views(): void
    {
        $admin = $this->createAdmin();

        Config::set('graduation_review.ai.api_key', 'super-secret-ai-key');

        $this->actingAs($admin, 'admin');

        $content = $this->get(route('admin.graduation-review.index'))->getContent();

        $this->assertStringNotContainsString('super-secret-ai-key', $content);
    }

    public function test_batch_generate_processes_multiple_students(): void
    {
        $admin = $this->createAdmin();

        $studentA = $this->createStudent('2026-0008', 'Frank', 'Blue');
        $studentB = $this->createStudent('2026-0009', 'Grace', 'Red');

        $vA = $this->createViolation($studentA->student_number, '2024-01-01');
        $this->createSanctionRecord($studentA->student_number, $vA, 'Pending');

        Http::fake([
            'https://ai.test/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'summary' => 'Summary.',
                    'review_status' => 'no_immediate_concern',
                    'outstanding_sanctions' => [],
                    'disciplinary_pattern' => '',
                    'reason_for_review' => '',
                    'recommended_action' => '',
                ])]]],
            ], 200),
        ]);

        $this->actingAs($admin, 'admin');

        $this->post(route('admin.graduation-review.generate-batch'))
            ->assertRedirect(route('admin.graduation-review.index'));

        $this->assertDatabaseHas('ai_graduation_reviews', ['student_number' => $studentA->student_number]);
        $this->assertDatabaseHas('ai_graduation_reviews', ['student_number' => $studentB->student_number]);
    }

    protected function createAdmin(): Admin
    {
        return Admin::create([
            'email' => 'admin@example.com',
            'username' => 'admin',
            'password' => 'password123',
            'mfa_totp_enabled' => 0,
        ]);
    }

    protected function createStudent(string $number, string $first, string $last): User
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

        $program = Program::orderBy('program_id')->first();
        $year = Year::orderBy('year_id')->first();
        $section = Section::orderBy('section_id')->first();

        StudentInfo::create([
            'user_id' => $student->getKey(),
            'program_id' => $program->program_id,
            'year_id' => $year->year_id,
            'section_id' => $section->section_id,
            'student_status_id' => $studentStatusId,
        ]);

        return $student;
    }

    protected function createViolation(string $studentNumber, string $date): Violation
    {
        $type = ViolationType::orderBy('violation_type_id')->first();

        return Violation::create([
            'student_number' => $studentNumber,
            'violation_type' => $type->violation_type_id,
            'violation_date' => $date,
            'description' => 'Test violation description.',
        ]);
    }

    protected function createSanctionRecord(string $studentNumber, Violation $violation, string $status): StudentSanctionRecord
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

    protected function ensureTables(): void
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
                $table->unsignedBigInteger('gender_id')->nullable();
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
                $table->tinyInteger('ladderized')->default(0);
            },
            'admins' => function (Blueprint $table) {
                $table->id();
                $table->string('username')->nullable();
                $table->string('email')->unique();
                $table->string('password');
                $table->text('mfa_totp_secret')->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
                $table->timestamps();
            },
            'violation_type_tbl' => function (Blueprint $table) {
                $table->id('violation_type_id');
                $table->string('violation_type')->nullable();
                $table->unsignedBigInteger('violation_category_id')->nullable();
                $table->string('severity_level')->nullable();
            },
            'violation_category_tbl' => function (Blueprint $table) {
                $table->id('violation_category_id');
                $table->string('category_name')->nullable();
            },
            'violation_tbl' => function (Blueprint $table) {
                $table->id('violation_id');
                $table->string('student_number')->nullable();
                $table->string('violation_type')->nullable();
                $table->date('violation_date')->nullable();
                $table->text('description')->nullable();
                $table->string('recorder_type', 20)->nullable();
                $table->string('recorder_name', 150)->nullable();
                $table->timestamps();
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
                $table->unsignedBigInteger('assigned_by_admin_id')->nullable();
                $table->string('status')->nullable();
                $table->date('deadline_date')->nullable();
                $table->timestamp('date_assigned')->nullable();
                $table->timestamp('date_completed')->nullable();
            },
            'ai_graduation_reviews' => function (Blueprint $table) {
                $table->id('review_id');
                $table->string('student_number');
                $table->unsignedBigInteger('generated_by_admin_id')->nullable();
                $table->string('review_status')->nullable();
                $table->text('summary')->nullable();
                $table->text('disciplinary_pattern')->nullable();
                $table->text('reason_for_review')->nullable();
                $table->text('recommended_action')->nullable();
                $table->json('outstanding_sanctions')->nullable();
                $table->unsignedInteger('total_violations')->default(0);
                $table->unsignedInteger('completed_sanctions')->default(0);
                $table->unsignedInteger('pending_sanctions')->default(0);
                $table->unsignedInteger('outstanding_sanctions_count')->default(0);
                $table->string('ai_model')->nullable();
                $table->timestamp('ai_generated_at')->nullable();
                $table->timestamps();
            },
        ] as $tableName => $closure) {
            if (! Schema::hasTable($tableName)) {
                Schema::create($tableName, $closure);
            }
        }
    }

    protected function seedReferenceData(): void
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
                ['section_id' => 1, 'section_name' => 'Section A'],
            ]);
        }

        if (DB::table('violation_category_tbl')->count() === 0) {
            DB::table('violation_category_tbl')->insert([
                ['violation_category_id' => 1, 'category_name' => 'General'],
            ]);
        }

        if (DB::table('violation_type_tbl')->count() === 0) {
            DB::table('violation_type_tbl')->insert([
                ['violation_type_id' => 1, 'violation_type' => 'Smoking', 'violation_category_id' => 1],
                ['violation_type_id' => 2, 'violation_type' => 'Bullying', 'violation_category_id' => 1],
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
