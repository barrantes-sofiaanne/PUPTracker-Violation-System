<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ViolationController;
use App\Models\Admin;
use App\Models\StudentSanctionRecord;
use App\Models\User;
use App\Models\Violation;
use App\Services\OffenseNumberService;
use App\Services\StudentProfileSyncService;
use App\Services\ViolationRecordingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Focused coverage for the six enhancement tasks:
 *
 *  1. Centralized offense numbering (pending reservations, rejection shifts,
 *     independent sequences, user_id identity).
 *  2. Record-violation loading state.
 *  3. Admin student-number editing + snapshot synchronization.
 *  4. Violation-type severity removal / server publication / audit logging.
 *  5. AI Graduation Report no longer exposes Major/Minor counts.
 *  6. Profile synchronization across modules.
 */
class EnhancementTasksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        $this->seedReferenceData();
    }

    // ==================================================================
    // TASK 1 — OFFENSE NUMBERING
    // ==================================================================

    public function test_pending_violations_reserve_sequential_offense_numbers(): void
    {
        $student = $this->createStudent('2026-0001');
        $this->createViolationType(1, 1, 'Dress Code');

        $first = $this->createViolation($student, 1, 'pending', '2026-01-01 09:00:00');
        $second = $this->createViolation($student, 1, 'pending', '2026-01-02 09:00:00');

        $this->assertSame('1st Offense', $first->fresh()->offense_level);
        $this->assertSame('2nd Offense', $second->fresh()->offense_level);

        $service = app(OffenseNumberService::class);
        $this->assertSame(2, $service->currentOffenseNumber((int) $student->user_id, 1));
        $this->assertSame('3rd Offense', $service->nextOffenseLevel((int) $student->user_id, 1));
    }

    public function test_rejecting_the_first_violation_moves_the_second_to_first_offense(): void
    {
        $student = $this->createStudent('2026-0001');
        $this->createViolationType(1, 1, 'Dress Code');

        $first = $this->createViolation($student, 1, 'pending', '2026-01-01 09:00:00');
        $second = $this->createViolation($student, 1, 'pending', '2026-01-02 09:00:00');

        $before = app(OffenseNumberService::class)
            ->offenseNumbersFor((int) $student->user_id, 1);

        DB::table('violation_tbl')->where('violation_id', $first->violation_id)->update([
            'status' => Violation::STATUS_DENIED,
        ]);

        $after = app(OffenseNumberService::class)
            ->offenseNumbersFor((int) $student->user_id, 1);

        $this->assertSame('', $first->fresh()->offense_level, 'A rejected row occupies no slot.');
        $this->assertSame('1st Offense', $second->fresh()->offense_level);
        $this->assertSame(2, $before[(int) $second->violation_id]);
        $this->assertSame(1, $after[(int) $second->violation_id]);
        $this->assertArrayNotHasKey((int) $first->violation_id, $after);
    }

    public function test_rejecting_a_middle_violation_shifts_later_rows_down_one(): void
    {
        $student = $this->createStudent('2026-0001');
        $this->createViolationType(1, 1, 'Dress Code');

        $first = $this->createViolation($student, 1, 'confirmed', '2026-01-01 09:00:00');
        $middle = $this->createViolation($student, 1, 'pending', '2026-01-02 09:00:00');
        $last = $this->createViolation($student, 1, 'confirmed', '2026-01-03 09:00:00');

        $this->assertSame('2nd Offense', $middle->fresh()->offense_level);
        $this->assertSame('3rd Offense', $last->fresh()->offense_level);

        DB::table('violation_tbl')->where('violation_id', $middle->violation_id)->update([
            'status' => Violation::STATUS_DENIED,
        ]);

        $this->assertSame('1st Offense', $first->fresh()->offense_level);
        $this->assertSame('2nd Offense', $last->fresh()->offense_level);
    }

    public function test_denied_and_deleted_rows_never_count(): void
    {
        $student = $this->createStudent('2026-0001');
        $this->createViolationType(1, 1, 'Dress Code');

        $this->createViolation($student, 1, 'denied', '2026-01-01 09:00:00');
        $this->createViolation($student, 1, 'deleted', '2026-01-02 09:00:00');
        $counted = $this->createViolation($student, 1, 'confirmed', '2026-01-03 09:00:00');

        $this->assertSame('1st Offense', $counted->fresh()->offense_level);
    }

    public function test_different_violation_types_have_independent_sequences(): void
    {
        $student = $this->createStudent('2026-0001');
        $this->createViolationType(1, 1, 'Dress Code');
        $this->createViolationType(2, 1, 'Curfew');

        $this->createViolation($student, 1, 'pending', '2026-01-01 09:00:00');
        $otherType = $this->createViolation($student, 2, 'pending', '2026-01-02 09:00:00');

        $this->assertSame('1st Offense', $otherType->fresh()->offense_level);
    }

    public function test_different_categories_have_independent_sequences(): void
    {
        $student = $this->createStudent('2026-0001');
        $this->createViolationType(1, 1, 'Dress Code');
        $this->createViolationType(2, 2, 'Academic Dishonesty');

        $this->createViolation($student, 1, 'pending', '2026-01-01 09:00:00');
        $otherCategory = $this->createViolation($student, 2, 'pending', '2026-01-02 09:00:00');

        $this->assertSame('1st Offense', $otherCategory->fresh()->offense_level);
    }

    public function test_different_users_never_share_offense_counts_including_null_numbers(): void
    {
        $this->createViolationType(1, 1, 'Dress Code');

        $firstStudent = $this->createStudent(null);
        $secondStudent = $this->createStudent(null);

        $first = $this->createViolation($firstStudent, 1, 'pending', '2026-01-01 09:00:00');
        $second = $this->createViolation($secondStudent, 1, 'pending', '2026-01-01 09:00:00');

        $this->assertSame('1st Offense', $first->fresh()->offense_level);
        $this->assertSame('1st Offense', $second->fresh()->offense_level);
    }

    public function test_security_and_oss_staff_share_the_same_offense_calculation(): void
    {
        $student = $this->createStudent('2026-0001');
        $this->createViolationType(1, 1, 'Dress Code');
        $this->createViolation($student, 1, 'pending', '2026-01-01 09:00:00');

        $preview = app(ViolationRecordingService::class)
            ->previewViolationData((int) $student->user_id, 1);

        $this->assertSame(
            app(OffenseNumberService::class)->nextOffenseLevel((int) $student->user_id, 1),
            $preview['offense_level']
        );
        $this->assertSame(
            '2nd Offense',
            ViolationController::formatOffenseLevelStatic(2)
        );
    }

    public function test_recalculation_preserves_an_existing_sanction_record(): void
    {
        $student = $this->createStudent('2026-0001');
        $this->createViolationType(1, 1, 'Dress Code');

        $first = $this->createViolation($student, 1, 'pending', '2026-01-01 09:00:00');
        $second = $this->createViolation($student, 1, 'confirmed', '2026-01-02 09:00:00');

        DB::table('student_sanction_records_tbl')->insert([
            'student_number' => $student->student_number,
            'violation_id' => $second->violation_id,
            'assigned_sanction_id' => 1,
            'assigned_by_admin_id' => 1,
            'status' => StudentSanctionRecord::STATUS_IN_PROGRESS,
        ]);

        DB::table('violation_tbl')->where('violation_id', $first->violation_id)->update([
            'status' => Violation::STATUS_DENIED,
        ]);

        $record = StudentSanctionRecord::where('violation_id', $second->violation_id)->firstOrFail();

        $this->assertSame('1st Offense', $second->fresh()->offense_level);
        $this->assertSame(StudentSanctionRecord::STATUS_IN_PROGRESS, $record->status);
    }

    public function test_student_number_snapshots_in_violations_are_not_used_for_identity(): void
    {
        $student = $this->createStudent('2026-0001');
        $other = $this->createStudent('2026-9999');
        $this->createViolationType(1, 1, 'Dress Code');

        $violation = $this->createViolation($student, 1, 'pending', '2026-01-01 09:00:00');

        // An unrelated row that happens to share a stale number must not affect
        // this student's sequence.
        DB::table('violation_tbl')->insert([
            'user_id' => $other->user_id,
            'student_number' => '2026-0001',
            'violation_type_id' => 1,
            'violation_date' => '2026-01-02 09:00:00',
            'status' => 'pending',
        ]);

        $this->assertSame('1st Offense', $violation->fresh()->offense_level);
    }

    public function test_concurrent_submissions_do_not_share_an_offense_position(): void
    {
        $student = $this->createStudent('2026-0001');
        $this->createViolationType(1, 1, 'Dress Code');

        // Both submissions serialize on the canonical student row inside their
        // own transaction before writing, exactly like the controllers do.
        $service = app(OffenseNumberService::class);

        DB::transaction(function () use ($service, $student): void {
            $service->lockStudent((int) $student->user_id);
            $service->nextOffenseLevel((int) $student->user_id, 1);
            $this->createViolation($student, 1, 'pending', '2026-01-01 09:00:00');
        });

        DB::transaction(function () use ($service, $student): void {
            $service->lockStudent((int) $student->user_id);
            $service->nextOffenseLevel((int) $student->user_id, 1);
            $this->createViolation($student, 1, 'pending', '2026-01-01 09:00:00');
        });

        $positions = array_values($service->offenseNumbersFor((int) $student->user_id, 1));

        $this->assertSame([1, 2], $positions);
        $this->assertCount(2, array_unique($positions), 'No two sibling submissions may share a position.');
    }

    public function test_admin_security_and_oss_staff_read_the_same_recalculated_number(): void
    {
        $student = $this->createStudent('2026-0001');
        $this->createViolationType(1, 1, 'Dress Code');

        $first = $this->createViolation($student, 1, 'pending', '2026-01-01 09:00:00');
        $second = $this->createViolation($student, 1, 'confirmed', '2026-01-02 09:00:00');

        DB::table('violation_tbl')->where('violation_id', $first->violation_id)->update([
            'status' => Violation::STATUS_DENIED,
        ]);

        $adminView = ViolationController::formatOffenseLevelStatic(1);
        $accessor = $second->fresh()->offense_level;
        $recordingPreview = app(ViolationRecordingService::class)
            ->previewViolationData((int) $student->user_id, 1);

        $this->assertSame('1st Offense', $adminView);
        $this->assertSame($adminView, $accessor);
        $this->assertSame(
            '2nd Offense',
            $recordingPreview['offense_level'],
            'The preview shows the NEXT slot, using the same sequence.'
        );
    }

    // ==================================================================
    // TASK 2 — LOADING STATE
    // ==================================================================

    public function test_record_violation_scripts_show_loading_and_restore_the_button(): void
    {
        foreach ([
            'public/js/security/record-violation.js',
            'public/js/oss-staff/record-violation.js',
        ] as $path) {
            $script = file_get_contents(base_path($path));

            $this->assertStringContainsString('if (saveBtn.disabled)', $script);
            $this->assertStringContainsString('Recording Violation...', $script);
            $this->assertStringContainsString('const originalSaveLabel = saveBtn.innerHTML;', $script);
            $this->assertStringContainsString('saveBtn.innerHTML = originalSaveLabel;', $script);
            $this->assertStringContainsString('saveBtn.disabled = false;', $script);
        }
    }

    // ==================================================================
    // TASK 3 — ADMIN STUDENT NUMBER EDITING + SNAPSHOTS
    // ==================================================================

    public function test_admin_can_edit_student_number_and_all_snapshots_follow_the_same_account(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent('OLD-0001');
        $this->createStudentInfo($student);
        $this->createViolationType(1, 1, 'Dress Code');
        $this->createViolation($student, 1, 'confirmed', '2026-01-01 09:00:00');

        $other = $this->createStudent('OTHER-0001');
        $this->createViolation($other, 1, 'confirmed', '2026-01-01 09:00:00');

        DB::table('sanction_requests_tbl')->insert([
            'student_number' => 'OLD-0001',
            'violation_type_id' => 1,
            'status' => 'Pending',
        ]);
        DB::table('notifications_tbl')->insert([
            'student_number' => 'OLD-0001',
            'recipient_id' => $student->user_id,
            'message' => 'Old notification',
        ]);
        DB::table('ai_graduation_reviews')->insert([
            'student_number' => 'OLD-0001',
            'review_status' => 'no_immediate_concern',
        ]);

        $violation = Violation::where('user_id', $student->user_id)->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->put(route('admin.students.update', $student->user_id), [
                'student_number' => 'NEW-0001',
                'program_id' => 1,
                'year_id' => 1,
                'section_id' => 1,
                'gender_id' => 1,
                'status_id' => 1,
                'ladderized' => 0,
            ])
            ->assertRedirect(route('admin.students', ['tab' => 'students']));

        $fresh = $student->fresh();
        $this->assertSame((int) $student->user_id, (int) $fresh->user_id, 'user_id must never change.');
        $this->assertSame('NEW-0001', $fresh->student_number);

        $this->assertSame(
            (int) $student->user_id,
            (int) $violation->fresh()->user_id,
            'Historical violations stay linked by user_id.'
        );
        $this->assertSame('NEW-0001', $violation->fresh()->student_number);
        $this->assertSame('NEW-0001', $violation->fresh()->student->student_number);
        $this->assertSame('NEW-0001', DB::table('sanction_requests_tbl')->value('student_number'));
        $this->assertSame('NEW-0001', DB::table('notifications_tbl')->value('student_number'));
        $this->assertSame('NEW-0001', DB::table('ai_graduation_reviews')->value('student_number'));

        // Another student's records are untouched.
        $otherViolation = Violation::where('user_id', $other->user_id)->firstOrFail();
        $this->assertSame('OTHER-0001', $otherViolation->student_number);
    }

    public function test_admin_student_number_edit_rejects_a_number_owned_by_another_student(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent('OLD-0001');
        $this->createStudentInfo($student);
        $this->createStudent('TAKEN-0001');

        $this->actingAs($admin, 'admin')
            ->put(route('admin.students.update', $student->user_id), [
                'student_number' => 'TAKEN-0001',
                'program_id' => 1,
                'year_id' => 1,
                'section_id' => 1,
                'gender_id' => 1,
                'status_id' => 1,
            ])
            ->assertSessionHasErrors('student_number');

        $this->assertSame('OLD-0001', $student->fresh()->student_number);
    }

    public function test_empty_student_number_normalizes_to_null_without_touching_user_id(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent('OLD-0001');
        $this->createStudentInfo($student);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.students.update', $student->user_id), [
                'student_number' => '   ',
                'program_id' => 1,
                'year_id' => 1,
                'section_id' => 1,
                'gender_id' => 1,
                'status_id' => 1,
            ])
            ->assertRedirect(route('admin.students', ['tab' => 'students']));

        $fresh = $student->fresh();
        $this->assertNull($fresh->student_number);
        $this->assertSame((int) $student->user_id, (int) $fresh->user_id);
    }

    // ==================================================================
    // TASK 4 — VIOLATION TYPE CONFIGURATION
    // ==================================================================

    public function test_violation_type_create_no_longer_requires_severity_and_sets_publication_time(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'admin')
            ->postJson(route('admin.violation-types.store'), [
                'violation_category_id' => 1,
                'violation_type' => 'New Type Without Severity',
                'violation_description' => 'Created without a severity field.',
                'resolution_number' => 'RES-001',
            ]);

        $response->assertOk()->assertJsonPath('success', true);

        $type = DB::table('violation_type_tbl')
            ->where('violation_type', 'New Type Without Severity')
            ->first();

        $this->assertNotNull($type);
        $this->assertNotNull($type->date_published);
        $this->assertSame(
            now('Asia/Manila')->format('Y-m-d H:i'),
            \Carbon\Carbon::parse($type->date_published)->format('Y-m-d H:i')
        );

        $audit = DB::table('audit_logs')
            ->where('module', 'Violation Types')
            ->where('action', 'Created')
            ->first();

        $this->assertNotNull($audit);
        $this->assertStringContainsString('New Type Without Severity', (string) $audit->description);
        $this->assertStringContainsString('New values', (string) $audit->description);
    }

    public function test_violation_type_update_succeeds_without_severity_preserves_publication_and_logs_changes(): void
    {
        $admin = $this->createAdmin();
        $typeId = $this->createViolationType(1, 1, 'Original Type');

        $publishedAt = DB::table('violation_type_tbl')
            ->where('violation_type_id', $typeId)
            ->value('date_published');

        $this->actingAs($admin, 'admin')
            ->putJson(route('admin.violation-types.update', $typeId), [
                'violation_category_id' => 1,
                'violation_type' => 'Renamed Type',
                'violation_description' => 'Updated description.',
                'resolution_number' => 'RES-001',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $type = DB::table('violation_type_tbl')->where('violation_type_id', $typeId)->first();

        $this->assertSame('Renamed Type', $type->violation_type);
        $this->assertSame($publishedAt, $type->date_published, 'Editing must not reset publication time.');

        $audit = DB::table('audit_logs')
            ->where('module', 'Violation Types')
            ->where('action', 'Updated')
            ->first();

        $this->assertNotNull($audit);
        $this->assertStringContainsString('violation_type', (string) $audit->description);
        $this->assertStringContainsString('Renamed Type', (string) $audit->description);
        // Unchanged fields must not be reported as modifications.
        $this->assertStringNotContainsString('resolution_number', (string) $audit->description);
    }

    // ==================================================================
    // TASK 5 — AI GRADUATION REPORT
    // ==================================================================

    public function test_ai_graduation_report_has_no_major_or_minor_counts(): void
    {
        $controller = app(\App\Http\Controllers\Admin\GraduationReviewController::class);

        $method = new \ReflectionMethod($controller, 'buildReport');
        $method->setAccessible(true);

        $report = $method->invoke($controller, collect([
            ['total_violations' => 0, 'outstanding_sanctions' => 0, 'repeated_violations_exist' => false],
            ['total_violations' => 2, 'outstanding_sanctions' => 1, 'repeated_violations_exist' => true],
        ]));

        $this->assertArrayNotHasKey('major', $report);
        $this->assertArrayNotHasKey('minor', $report);
        $this->assertArrayHasKey('total', $report);
        $this->assertArrayHasKey('no_history', $report);
        $this->assertArrayHasKey('outstanding', $report);

        $view = file_get_contents(resource_path('views/admin/reports/tabs/graduation-ai-report.blade.php'));

        $this->assertStringNotContainsString('Major Violations', $view);
        $this->assertStringNotContainsString('Minor Violations', $view);
        $this->assertStringContainsString('Students Reviewed', $view);
    }

    // ==================================================================
    // TASK 6 — PROFILE SYNCHRONIZATION
    // ==================================================================

    public function test_profile_sync_updates_the_canonical_relationship_without_changing_account_status(): void
    {
        $student = $this->createStudent('OLD-0001');
        $student->update(['status_id' => 2]); // Inactive / resolved-style account.
        $this->createStudentInfo($student);
        $this->createViolationType(1, 1, 'Dress Code');

        foreach (['pending', 'confirmed', 'denied'] as $index => $status) {
            $this->createViolation($student, 1, $status, '2026-01-0' . ($index + 1) . ' 09:00:00');
        }

        // The caller updates the canonical account first; the service then
        // refreshes only this account's display snapshots.
        $student->update(['student_number' => 'NEW-0001']);

        $sync = app(StudentProfileSyncService::class);
        $updated = $sync->synchronizeStudentNumber($student, 'NEW-0001', 'OLD-0001');

        $fresh = $student->fresh();
        $this->assertSame((int) $student->user_id, (int) $fresh->user_id);
        $this->assertSame(2, (int) $fresh->status_id, 'Profile sync must not reactivate the account.');

        $this->assertSame(3, (int) ($updated['violation_tbl'] ?? 0));
        $this->assertSame(0, Violation::where('student_number', 'OLD-0001')->count());
        $this->assertSame(3, Violation::where('user_id', $student->user_id)->count());
        $this->assertSame(
            'NEW-0001',
            Violation::where('user_id', $student->user_id)->first()->student->student_number
        );
    }

    public function test_profile_sync_works_for_a_student_with_no_records(): void
    {
        $student = $this->createStudent('OLD-0001');

        $updated = app(StudentProfileSyncService::class)
            ->synchronizeStudentNumber($student, 'NEW-0001', 'OLD-0001');

        $this->assertSame([], $updated);
        $this->assertSame((int) $student->user_id, (int) $student->fresh()->user_id);
    }

    // ==================================================================
    // SCHEMA / HELPERS
    // ==================================================================

    private function createAdmin(): Admin
    {
        return Admin::create([
            'username' => 'admin@example.com',
            'email' => 'admin@example.com',
            'password' => bcrypt('secret'),
        ]);
    }

    private function createStudent(?string $studentNumber): User
    {
        return User::create([
            'student_number' => $studentNumber,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => ($studentNumber ?: 'student-' . uniqid()) . '@example.com',
            'password_hash' => Hash::make('Password123!'),
            'roles_id' => 2,
            'status_id' => 1,
            'profile_completed_at' => now(),
        ]);
    }

    private function createStudentInfo(User $student): void
    {
        DB::table('student_info_tbl')->insert([
            'user_id' => $student->user_id,
            'program_id' => 1,
            'year_id' => 1,
            'section_id' => 1,
            'student_status_id' => 1,
            'ladderized' => 0,
        ]);
    }

    private function createViolationType(int $id, int $categoryId, string $name): int
    {
        DB::table('violation_type_tbl')->insert([
            'violation_type_id' => $id,
            'violation_type' => $name,
            'violation_category_id' => $categoryId,
            'resolution_number' => 'RES-001',
            'date_published' => now(),
            'severity_level' => 1,
        ]);

        return $id;
    }

    private function createViolation(
        User $student,
        int $typeId,
        string $status,
        string $date
    ): Violation {
        $id = DB::table('violation_tbl')->insertGetId([
            'user_id' => $student->user_id,
            'student_number' => $student->student_number,
            'violation_type_id' => $typeId,
            'violation_type' => (string) $typeId,
            'violation_date' => $date,
            'description' => 'Test violation.',
            'recorder_type' => 'security',
            'recorder_name' => 'Officer',
            'status' => $status,
        ]);

        return Violation::findOrFail($id);
    }

    private function seedReferenceData(): void
    {
        DB::table('roles_tbl')->insert([['roles_id' => 1, 'roles_name' => 'Admin'], ['roles_id' => 2, 'roles_name' => 'Student']]);
        DB::table('status_tbl')->insert([['status_id' => 1, 'status_name' => 'Active'], ['status_id' => 2, 'status_name' => 'Inactive']]);
        DB::table('student_status_tbl')->insert(['student_status_id' => 1, 'status_name' => 'Currently Enrolled']);
        DB::table('gender_tbl')->insert(['gender_id' => 1, 'gender_name' => 'Male']);
        DB::table('program_tbl')->insert(['program_id' => 1, 'program_name' => 'BSIT']);
        DB::table('year_tbl')->insert(['year_id' => 1, 'year' => '1']);
        DB::table('section_tbl')->insert(['section_id' => 1, 'section_name' => '1A']);
        DB::table('violation_category_tbl')->insert([
            ['violation_category_id' => 1, 'category_name' => 'Dress Code Policy'],
            ['violation_category_id' => 2, 'category_name' => 'Academic Integrity'],
        ]);
        DB::table('disciplinary_sanctions')->insert([
            'disciplinary_sanction_id' => 1,
            'violation_type_id' => 1,
            'offense_level' => '1st Offense',
            'disciplinary_sanction' => 'Warning',
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('admins', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('remember_token', 100)->nullable();
            $table->boolean('mfa_totp_enabled')->default(false);
            $table->unsignedBigInteger('role_id')->nullable();
        });

        Schema::create('admin_info_tbl', function (Blueprint $table): void {
            $table->increments('admin_info_id');
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('firstname')->nullable();
            $table->string('middlename')->nullable();
            $table->string('lastname')->nullable();
            $table->string('position')->nullable();
            $table->string('designation')->nullable();
            $table->string('role')->nullable();
            $table->string('title')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
        });

        Schema::create('roles_tbl', function (Blueprint $table): void {
            $table->increments('roles_id');
            $table->string('roles_name');
        });

        Schema::create('status_tbl', function (Blueprint $table): void {
            $table->increments('status_id');
            $table->string('status_name');
        });

        Schema::create('student_status_tbl', function (Blueprint $table): void {
            $table->increments('student_status_id');
            $table->string('status_name')->nullable();
        });

        Schema::create('gender_tbl', function (Blueprint $table): void {
            $table->increments('gender_id');
            $table->string('gender_name')->nullable();
        });

        Schema::create('program_tbl', function (Blueprint $table): void {
            $table->increments('program_id');
            $table->string('program_name')->nullable();
        });

        Schema::create('year_tbl', function (Blueprint $table): void {
            $table->increments('year_id');
            $table->string('year')->nullable();
        });

        Schema::create('section_tbl', function (Blueprint $table): void {
            $table->increments('section_id');
            $table->string('section_name')->nullable();
        });

        Schema::create('users_tbl', function (Blueprint $table): void {
            $table->increments('user_id');
            $table->string('student_number')->nullable()->unique();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('name_suffix')->nullable();
            $table->string('email')->nullable();
            $table->string('idp_subject')->nullable();
            $table->string('idp_email')->nullable();
            $table->timestamp('profile_completed_at')->nullable();
            $table->string('password_hash')->nullable();
            $table->unsignedInteger('roles_id')->nullable();
            $table->unsignedInteger('status_id')->nullable();
            $table->unsignedInteger('gender_id')->nullable();
            $table->timestamps();
        });

        Schema::create('student_info_tbl', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->primary();
            $table->unsignedBigInteger('program_id')->nullable();
            $table->unsignedBigInteger('year_id')->nullable();
            $table->unsignedBigInteger('section_id')->nullable();
            $table->unsignedBigInteger('student_status_id')->nullable();
            $table->unsignedTinyInteger('ladderized')->default(0);
        });

        Schema::create('violation_category_tbl', function (Blueprint $table): void {
            $table->increments('violation_category_id');
            $table->string('category_name');
        });

        Schema::create('violation_type_tbl', function (Blueprint $table): void {
            $table->increments('violation_type_id');
            $table->string('violation_type');
            $table->unsignedBigInteger('violation_category_id')->nullable();
            $table->string('resolution_number')->nullable();
            $table->text('violation_description')->nullable();
            $table->timestamp('date_published')->nullable();
            $table->integer('severity_level')->nullable();
        });

        Schema::create('violation_tbl', function (Blueprint $table): void {
            $table->increments('violation_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('student_number')->nullable();
            $table->unsignedInteger('violation_type_id')->nullable();
            $table->string('violation_type')->nullable();
            $table->dateTime('violation_date')->nullable();
            $table->text('description')->nullable();
            $table->boolean('identity_verified')->default(false);
            $table->timestamp('identity_verified_at')->nullable();
            $table->string('identity_verified_by_type', 30)->nullable();
            $table->integer('identity_verified_by_id')->nullable();
            $table->unsignedInteger('recorder_id')->nullable();
            $table->string('recorder_type')->nullable();
            $table->string('recorder_name')->nullable();
            $table->unsignedBigInteger('recorded_by_security_id')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_remarks')->nullable();
        });

        Schema::create('disciplinary_sanctions', function (Blueprint $table): void {
            $table->increments('disciplinary_sanction_id');
            $table->unsignedBigInteger('violation_type_id')->nullable();
            $table->string('offense_level')->nullable();
            $table->string('disciplinary_sanction')->nullable();
        });

        Schema::create('student_sanction_records_tbl', function (Blueprint $table): void {
            $table->increments('record_id');
            $table->string('student_number')->nullable();
            $table->unsignedBigInteger('violation_id')->nullable();
            $table->unsignedBigInteger('assigned_sanction_id')->nullable();
            $table->unsignedBigInteger('assigned_by_admin_id')->nullable();
            $table->string('status')->nullable();
            $table->date('deadline_date')->nullable();
            $table->string('designated_office', 255)->nullable();
            $table->date('starting_date')->nullable();
            $table->timestamp('date_assigned')->nullable();
            $table->timestamp('date_completed')->nullable();
            $table->string('proof_path', 500)->nullable();
            $table->string('proof_access_key', 64)->nullable();
            $table->string('proof_original_name', 255)->nullable();
            $table->timestamp('proof_submitted_at')->nullable();
            $table->text('proof_notes')->nullable();
            $table->unsignedBigInteger('completion_reviewed_by_admin_id')->nullable();
            $table->timestamp('completion_reviewed_at')->nullable();
            $table->text('completion_remarks')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->unsignedBigInteger('reopened_by_admin_id')->nullable();
        });

        Schema::create('sanction_proofs_tbl', function (Blueprint $table): void {
            $table->increments('proof_id');
            $table->unsignedBigInteger('record_id')->nullable();
            $table->string('student_number')->nullable();
            $table->string('status')->nullable();
        });

        Schema::create('sanction_compliance_history', function (Blueprint $table): void {
            $table->increments('history_id');
            $table->unsignedBigInteger('record_id')->nullable();
            $table->string('student_number')->nullable();
            $table->string('action')->nullable();
        });

        Schema::create('sanction_requests_tbl', function (Blueprint $table): void {
            $table->increments('request_id');
            $table->string('student_number')->nullable();
            $table->unsignedBigInteger('violation_type_id')->nullable();
            $table->dateTime('request_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status')->nullable();
        });

        Schema::create('violation_correction_requests', function (Blueprint $table): void {
            $table->increments('request_id');
            $table->unsignedBigInteger('violation_id')->nullable();
            $table->string('student_number')->nullable();
            $table->string('status')->nullable();
        });

        Schema::create('ai_graduation_reviews', function (Blueprint $table): void {
            $table->increments('review_id');
            $table->string('student_number')->nullable();
            $table->string('review_status')->nullable();
        });

        Schema::create('notifications_tbl', function (Blueprint $table): void {
            $table->increments('notification_id');
            $table->string('student_number')->nullable();
            $table->string('recipient_type')->nullable();
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->increments('audit_log_id');
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action')->nullable();
            $table->string('module')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('user_management_histories', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->string('action')->nullable();
            $table->text('details')->nullable();
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
}
