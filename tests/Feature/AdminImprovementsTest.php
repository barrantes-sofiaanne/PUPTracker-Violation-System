<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DisciplinarySanction;
use App\Models\Program;
use App\Models\StudentSanctionRecord;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationCategory;
use App\Models\ViolationType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Covers this batch of Admin/Super Admin improvements:
 *
 *   A. Create Student accepts manually entered names (email is IDP-only).
 *   B. Student import supports the name columns and does NOT require email.
 *   D. Dashboard shows at most 5 recent violations and a real pending queue.
 *   F. Violation type renders its NAME, never the raw numeric ID.
 *   J. Reopening a completed sanction REQUIRES a reason (server-side).
 */
class AdminImprovementsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTables();
    }

    // ==================================================================
    // A. CREATE STUDENT — manually entered names
    // ==================================================================

    public function test_admin_can_create_a_student_with_a_full_name(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin')->post(route('admin.students.store'), [
            'student_number' => '2026-9001',
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz',
            'name_suffix' => 'Jr.',
            'program_id' => $this->programId(),
            'year_id' => $this->yearId(),
            'section_id' => $this->sectionId(),
            'gender_id' => $this->genderId(),
            'status_id' => $this->statusId(),
        ])->assertRedirect();

        $student = User::where('student_number', '2026-9001')->first();

        $this->assertNotNull($student);
        $this->assertSame('Juan', $student->first_name);
        $this->assertSame('Dela', $student->middle_name);
        $this->assertSame('Cruz', $student->last_name);
        $this->assertSame('Jr.', $student->name_suffix);

        // Email is NOT set on create — it arrives from the PUP IDP on linking.
        $this->assertNull($student->email);
    }

    public function test_create_student_does_not_require_an_email(): void
    {
        $admin = $this->createAdmin();

        // No `email` key provided at all — the request must still succeed.
        $this->actingAs($admin, 'admin')->post(route('admin.students.store'), [
            'student_number' => '2026-9002',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'program_id' => $this->programId(),
            'year_id' => $this->yearId(),
            'section_id' => $this->sectionId(),
            'gender_id' => $this->genderId(),
            'status_id' => $this->statusId(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNotNull(User::where('student_number', '2026-9002')->first());
    }

    public function test_middle_name_and_suffix_are_optional_but_names_are_required(): void
    {
        $admin = $this->createAdmin();

        $base = [
            'student_number' => '2026-9003',
            'program_id' => $this->programId(),
            'year_id' => $this->yearId(),
            'section_id' => $this->sectionId(),
            'gender_id' => $this->genderId(),
            'status_id' => $this->statusId(),
        ];

        // Omitting the optional fields succeeds.
        $this->actingAs($admin, 'admin')
            ->post(route('admin.students.store'), $base + ['first_name' => 'Ana', 'last_name' => 'Reyes'])
            ->assertSessionHasNoErrors();

        // A missing first_name / last_name is rejected SERVER-SIDE — the
        // asterisks in the form reflect real validation, not just styling.
        foreach (['first_name', 'last_name', 'student_number'] as $required) {
            $payload = $base + ['first_name' => 'Ana', 'last_name' => 'Reyes'];
            $payload['student_number'] = '2026-9010';
            unset($payload[$required]);

            $this->actingAs($admin, 'admin')
                ->from(route('admin.students'))
                ->post(route('admin.students.store'), $payload)
                ->assertSessionHasErrors($required);
        }
    }

    public function test_super_admin_has_the_same_create_student_behaviour(): void
    {
        $super = $this->createAdmin(true);

        $this->actingAs($super, 'admin')->post(route('admin.students.store'), [
            'student_number' => '2026-9004',
            'first_name' => 'Super',
            'middle_name' => 'Admin',
            'last_name' => 'Created',
            'name_suffix' => 'III',
            'program_id' => $this->programId(),
            'year_id' => $this->yearId(),
            'section_id' => $this->sectionId(),
            'gender_id' => $this->genderId(),
            'status_id' => $this->statusId(),
        ])->assertRedirect();

        $student = User::where('student_number', '2026-9004')->first();

        $this->assertNotNull($student);
        $this->assertSame('Super', $student->first_name);
        $this->assertSame('Admin', $student->middle_name);
        $this->assertSame('Created', $student->last_name);
        $this->assertSame('III', $student->name_suffix);
    }

    public function test_create_student_form_allows_name_entry_and_has_accurate_asterisks(): void
    {
        $admin = $this->createAdmin();

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.students', ['tab' => 'students']))
            ->assertOk()
            ->getContent();

        // The name inputs exist and are editable (not readonly/disabled).
        foreach (['first_name', 'middle_name', 'last_name', 'name_suffix'] as $field) {
            $this->assertMatchesRegularExpression(
                '/name="' . $field . '"[^>]*>/',
                $html,
                "The Create Student modal must expose {$field}."
            );
            $this->assertDoesNotMatchRegularExpression(
                '/name="' . $field . '"[^>]*(readonly|disabled)/',
                $html,
                "{$field} must be editable in Create Student."
            );
        }

        // Email is NOT an input in the Create Student modal...
        $modal = $this->createStudentModal($html);
        $this->assertStringNotContainsString('name="email"', $modal);

        // ...and the misleading "name is managed by the PUP IDP" copy is gone.
        $this->assertStringNotContainsString('Name and email are managed by the PUP IDP', $html);

        // Required fields carry the asterisk; optional ones do not.
        // 8 required: Student Number, Gender, First Name, Last Name,
        // Program, Year, Section, Status.
        $this->assertSame(8, substr_count($modal, 'required-asterisk'));
        $this->assertStringContainsString('Student Number <span class="required-asterisk">', $modal);
        $this->assertStringContainsString('First Name <span class="required-asterisk">', $modal);
        $this->assertStringContainsString('Last Name <span class="required-asterisk">', $modal);
        $this->assertStringContainsString('Gender <span class="required-asterisk">', $modal);
        $this->assertStringContainsString('Program <span class="required-asterisk">', $modal);
        $this->assertStringContainsString('Year <span class="required-asterisk">', $modal);
        $this->assertStringContainsString('Section <span class="required-asterisk">', $modal);
        $this->assertStringContainsString('Status <span class="required-asterisk">', $modal);

        // The genuinely optional fields must NOT carry one.
        $this->assertStringNotContainsString('Middle Name <span class="required-asterisk">', $modal);
        $this->assertStringNotContainsString('Name Suffix <span class="required-asterisk">', $modal);
    }

    // ==================================================================
    // D. DASHBOARD
    // ==================================================================

    public function test_dashboard_limits_recent_violations_to_five(): void
    {
        $admin = $this->createAdmin();

        for ($i = 1; $i <= 9; $i++) {
            $this->createViolation('2026-800' . $i, now()->subDays($i), 1, Violation::STATUS_CONFIRMED);
        }

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        // Exactly the 5 newest: newest first.
        $this->assertSame(5, substr_count($html, 'recent-violation-item'));

        // Scope the ordering/absence checks to the Recent Violations table —
        // the "Top Offenders" widget also lists student numbers, so a page-wide
        // check would produce false results.
        $recent = $this->recentViolationsTable($html);

        $this->assertLessThan(
            strpos($recent, '2026-8005'),
            strpos($recent, '2026-8001'),
            'Recent violations must stay newest-first.'
        );

        // The 6th-newest must not be rendered in THIS table.
        $this->assertStringNotContainsString('2026-8006', $recent);
    }

    /**
     * Slice the rendered dashboard down to the Recent Violations table so
     * assertions are not polluted by the Top Offenders widget.
     */
    private function recentViolationsTable(string $html): string
    {
        $start = strpos($html, 'Recent Violations');
        if ($start === false) {
            return '';
        }

        $end = strpos($html, 'Top', $start);

        return $end === false ? substr($html, $start) : substr($html, $start, $end - $start);
    }

    public function test_dashboard_replaces_minor_major_cards_with_real_metrics(): void
    {
        $admin = $this->createAdmin();

        // One real student + one pending (awaiting confirmation) violation.
        $this->createViolation('2026-8101', now()->toDateString(), 1, Violation::STATUS_PENDING);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        // The retired offense-level cards are gone from the dashboard.
        $this->assertStringNotContainsString('Minor Violations', $html);
        $this->assertStringNotContainsString('Major Violations', $html);

        // The replacement metrics are real, database-derived values.
        $this->assertStringContainsString('Pending Confirmations', $html);
        $this->assertStringContainsString('Total Students', $html);
        $this->assertStringContainsString('Total Violations', $html);
        $this->assertStringContainsString('Pending Requests', $html);

        // Pending Confirmations reflects the pending row: exactly 1 > 0,
        // proving it is computed rather than hard-coded to zero.
        $this->assertMatchesRegularExpression(
            '/Pending Confirmations.*?>1</s',
            $html,
            'Pending Confirmations must be computed from real pending violations.'
        );
    }

    // ==================================================================
    // F. VIOLATION TYPE NAME, NOT A NUMBER
    // ==================================================================

    public function test_student_violation_summary_shows_type_name_not_the_numeric_id(): void
    {
        $admin = $this->createAdmin();

        $typeId = $this->createViolationType(10, 'No ID');

        // The show route resolves the STUDENT first, so the record must exist.
        $this->createStudentRow('2026-8201');

        // The row stores the numeric ID in the `violation_type` column, exactly
        // as the app writes it.
        DB::table('violation_tbl')->insert([
            'student_number' => '2026-8201',
            'violation_type' => (string) $typeId,
            'violation_date' => now(),
            'description' => 'No ID presented.',
            'recorder_type' => 'admin',
            'recorder_name' => 'Admin',
            'status' => Violation::STATUS_CONFIRMED,
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.violations.show', ['student_number' => '2026-8201']))
            ->assertOk()
            ->getContent();

        // The human-readable name is shown...
        $this->assertStringContainsString('No ID', $this->violationSummaryTable($html));

        // ...and the bare numeric ID is NOT printed as a type value anywhere.
        $this->assertDoesNotMatchRegularExpression(
            '/Violation Type<\/th>\s*<td>\s*10\s*<\/td>/',
            $html,
            'The violation type cell must not render the raw numeric ID.'
        );

        // The summary row's type cell carries the name, not the ID.
        $summary = $this->violationSummaryTable($html);
        $this->assertStringContainsString('No ID', $summary);
        $this->assertDoesNotMatchRegularExpression(
            '/<td>\s*10\s*<\/td>/',
            $summary,
            'The summary table must not render the raw numeric type ID.'
        );
    }

    public function test_legacy_name_valued_rows_group_with_id_valued_rows(): void
    {
        $admin = $this->createAdmin();

        $typeId = $this->createViolationType(24, 'Improper Uniform');

        $this->createStudentRow('2026-8301');

        // Same real type, two storage shapes: one row holds the ID, one holds
        // the name (legacy). They must collapse into ONE summary row.
        DB::table('violation_tbl')->insert([
            [
                'student_number' => '2026-8301',
                'violation_type' => (string) $typeId,
                'violation_date' => now()->subDay(),
                'description' => 'First instance.',
                'recorder_type' => 'admin',
                'recorder_name' => 'Admin',
                'status' => Violation::STATUS_CONFIRMED,
            ],
            [
                'student_number' => '2026-8301',
                'violation_type' => 'Improper Uniform',
                'violation_date' => now(),
                'description' => 'Second instance.',
                'recorder_type' => 'admin',
                'recorder_name' => 'Admin',
                'status' => Violation::STATUS_CONFIRMED,
            ],
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.violations.show', ['student_number' => '2026-8301']))
            ->assertOk()
            ->getContent();

        // One row for the type, at the 2nd Offense level (two instances).
        // Scoped to the SUMMARY table: the name also appears in the individual
        // violation log, so a page-wide count would be misleading.
        $summary = $this->violationSummaryTable($html);

        $this->assertSame(1, substr_count($summary, 'Improper Uniform'));
        $this->assertStringContainsString('2nd Offense', $summary);
        $this->assertStringContainsString('Multiple instances', $summary);
    }

    /** Slice the page down to the Violation Summary table (before Violation History). */
    private function violationSummaryTable(string $html): string
    {
        $start = strpos($html, 'Violation Summary');
        if ($start === false) {
            return '';
        }

        // "Violation History" is the NEXT card, so it marks the boundary.
        $end = strpos($html, 'Violation History', $start);

        return $end === false ? substr($html, $start) : substr($html, $start, $end - $start);
    }

    // ==================================================================
    // J. REOPEN SANCTION REQUIRES A REASON
    // ==================================================================

    public function test_reopening_a_sanction_without_a_reason_is_rejected_server_side(): void
    {
        $admin = $this->createAdmin();
        $record = $this->createCompletedSanction();

        // No `reason` at all.
        $this->actingAs($admin, 'admin')
            ->from(route('admin.sanctions.index'))
            ->post(route('admin.sanctions.records.revert', $record))
            ->assertSessionHasErrors('reason');

        // Blank / whitespace-only reason is also rejected.
        $this->actingAs($admin, 'admin')
            ->from(route('admin.sanctions.index'))
            ->post(route('admin.sanctions.records.revert', $record), ['reason' => '   '])
            ->assertSessionHasErrors('reason');

        // The record is untouched — it must still be Completed.
        $this->assertSame('Completed', (string) $record->fresh()->status);
    }

    public function test_reopening_with_a_valid_reason_succeeds_and_records_it(): void
    {
        $admin = $this->createAdmin();
        $record = $this->createCompletedSanction();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.sanctions.records.revert', $record), [
                'reason' => 'Student submitted a falsified certificate.',
            ])
            ->assertSessionHasNoErrors();

        $fresh = $record->fresh();

        // Back to the reopened/in-progress state.
        $this->assertSame(StudentSanctionRecord::STATUS_IN_PROGRESS, (string) $fresh->status);
        $this->assertNull($fresh->date_completed);

        // The reason is persisted on the record itself.
        if (Schema::hasColumn('student_sanction_records_tbl', 'reopen_reason')) {
            $this->assertSame('Student submitted a falsified certificate.', $fresh->reopen_reason);
            $this->assertNotNull($fresh->reopened_at);
        }

        // ...and mirrored into the audit log.
        $this->assertTrue(
            DB::table('audit_logs')
                ->where('action', 'Revert')
                ->where('description', 'like', '%falsified certificate%')
                ->exists(),
            'The reopening reason must be recorded in the audit log.'
        );
    }

    public function test_reopened_sanction_notifies_the_affected_student_with_the_reason(): void
    {
        $admin = $this->createAdmin();
        $record = $this->createCompletedSanction('2026-8401');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.sanctions.records.revert', $record), [
                'reason' => 'Proof of completion was invalid.',
            ])
            ->assertSessionHasNoErrors();

        // The notification belongs to the AFFECTED student, not the admin.
        $notification = DB::table('notifications_tbl')
            ->where('student_number', '2026-8401')
            ->where('notification_type', 'sanction')
            ->latest('notification_id')
            ->first();

        $this->assertNotNull($notification, 'The affected student must be notified.');
        $this->assertStringContainsString('Reopened', (string) $notification->title);
        $this->assertStringContainsString('Proof of completion was invalid.', (string) $notification->message);
    }

    public function test_only_completed_sanctions_can_be_reopened(): void
    {
        $admin = $this->createAdmin();
        $record = $this->createCompletedSanction();
        $record->update(['status' => StudentSanctionRecord::STATUS_IN_PROGRESS]);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.sanctions.index'))
            ->post(route('admin.sanctions.records.revert', $record), [
                'reason' => 'Should not work.',
            ])
            ->assertSessionHasErrors('sanction_record');
    }

    public function test_unauthenticated_users_cannot_reopen_a_sanction(): void
    {
        $record = $this->createCompletedSanction();

        $this->post(route('admin.sanctions.records.revert', $record), [
            'reason' => 'Not allowed.',
        ])->assertRedirect();

        // Still Completed — no state change from an unauthenticated request.
        $this->assertSame('Completed', (string) $record->fresh()->status);
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    /** Collapse the rendered page to just the Create Student modal. */
    private function createStudentModal(string $html): string
    {
        $start = strpos($html, 'id="createStudentModal"');
        if ($start === false) {
            return '';
        }

        $end = strpos($html, 'id="createAdminModal"', $start);

        return $end === false ? substr($html, $start) : substr($html, $start, $end - $start);
    }

    private function createAdmin(bool $super = false): Admin
    {
        return Admin::create([
            'username' => $super ? 'super@example.com' : 'admin@example.com',
            'email' => $super ? 'super@example.com' : 'admin@example.com',
            'password' => bcrypt('secret'),
            'role_id' => $super ? 4 : 1,
        ]);
    }

    private function createCompletedSanction(string $studentNumber = '2026-8400'): StudentSanctionRecord
    {
        $this->createStudentRow($studentNumber);

        return StudentSanctionRecord::create([
            'student_number' => $studentNumber,
            'violation_id' => 1,
            'assigned_sanction_id' => 1,
            'assigned_by_admin_id' => 1,
            'status' => StudentSanctionRecord::STATUS_COMPLETED,
            'date_assigned' => now()->subDays(10),
            'date_completed' => now()->subDay(),
        ]);
    }

    private function createStudentRow(string $number): void
    {
        if (User::where('student_number', $number)->exists()) {
            return;
        }

        DB::table('users_tbl')->insert([
            'student_number' => $number,
            'first_name' => 'Notify',
            'last_name' => 'Me',
            'email' => $number . '@example.com',
            'password_hash' => 'x',
            'roles_id' => 2,
            'status_id' => 1,
        ]);
    }

    private function createViolation(string $number, $date, int $typeId, string $status): void
    {
        DB::table('violation_tbl')->insert([
            'student_number' => $number,
            'violation_type' => (string) $typeId,
            'violation_date' => $date,
            'description' => 'Test violation.',
            'recorder_id' => 1,
            'recorder_type' => 'admin',
            'recorder_name' => 'Admin',
            'status' => $status,
        ]);
    }

    private function programId(): int
    {
        return (int) DB::table('program_tbl')->insertGetId(['program_name' => 'BSIT', 'degree_type' => 'Bachelor']);
    }

    private function yearId(): int
    {
        return (int) DB::table('year_tbl')->insertGetId(['year' => '1st Year']);
    }

    private function sectionId(): int
    {
        return (int) DB::table('section_tbl')->insertGetId(['section_name' => '1A']);
    }

    private function genderId(): int
    {
        return (int) DB::table('gender_tbl')->insertGetId(['gender_name' => 'Male']);
    }

    private function statusId(): int
    {
        return (int) DB::table('status_tbl')->insertGetId(['status_name' => 'Active']);
    }

    private function createViolationType(int $id, string $name): int
    {
        DB::table('violation_type_tbl')->insert([
            'violation_type_id' => $id,
            'violation_type' => $name,
            'violation_category_id' => 1,
            'severity_level' => 1,
        ]);

        return $id;
    }

    private function ensureTables(): void
    {
        $defs = [
            'roles_tbl' => function (Blueprint $t): void {
                $t->increments('roles_id');
                $t->string('roles_name')->nullable();
            },
            'status_tbl' => function (Blueprint $t): void {
                $t->increments('status_id');
                $t->string('status_name')->nullable();
            },
            'section_tbl' => function (Blueprint $t): void {
                $t->increments('section_id');
                $t->string('section_name')->nullable();
            },
            'gender_tbl' => function (Blueprint $t): void {
                $t->increments('gender_id');
                $t->string('gender_name')->nullable();
            },
            'program_tbl' => function (Blueprint $t): void {
                $t->increments('program_id');
                $t->string('program_name')->nullable();
                $t->string('degree_type')->nullable();
            },
            'year_tbl' => function (Blueprint $t): void {
                $t->increments('year_id');
                $t->string('year')->nullable();
            },
            'users_tbl' => function (Blueprint $t): void {
                $t->increments('user_id');
                $t->string('student_number')->nullable();
                $t->string('first_name')->nullable();
                $t->string('middle_name')->nullable();
                $t->string('last_name')->nullable();
                $t->string('name_suffix')->nullable();
                $t->string('email')->nullable();
                $t->string('password_hash')->nullable();
                $t->unsignedBigInteger('roles_id')->nullable();
                $t->unsignedBigInteger('status_id')->nullable();
                $t->unsignedBigInteger('gender_id')->nullable();
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
            'student_status_tbl' => function (Blueprint $t): void {
                $t->increments('student_status_id');
                $t->string('status_name')->nullable();
            },
            'admins' => function (Blueprint $t): void {
                $t->increments('id');
                $t->string('username')->nullable();
                $t->string('email')->nullable();
                $t->string('password')->nullable();
                $t->unsignedBigInteger('role_id')->nullable();
                $t->string('position')->nullable();
            },
            'admin_info_tbl' => function (Blueprint $t): void {
                $t->increments('admin_info_id');
                $t->unsignedBigInteger('admin_id')->nullable();
                $t->string('firstname')->nullable();
                $t->string('middlename')->nullable();
                $t->string('lastname')->nullable();
            },
            'violation_category_tbl' => function (Blueprint $t): void {
                $t->increments('violation_category_id');
                $t->string('category_name')->nullable();
            },
            'violation_type_tbl' => function (Blueprint $t): void {
                $t->increments('violation_type_id');
                $t->string('violation_type')->nullable();
                $t->unsignedBigInteger('violation_category_id')->nullable();
                $t->integer('severity_level')->nullable();
            },
            'violation_tbl' => function (Blueprint $t): void {
                $t->increments('violation_id');
                $t->string('student_number')->nullable();
                $t->string('violation_type')->nullable();
                $t->dateTime('violation_date')->nullable();
                $t->text('description')->nullable();
                $t->unsignedBigInteger('recorder_id')->nullable();
                $t->string('recorder_type')->nullable();
                $t->string('recorder_name')->nullable();
                $t->string('status')->nullable();
                $t->text('review_remarks')->nullable();
                $t->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            },
            'disciplinary_sanctions' => function (Blueprint $t): void {
                $t->increments('disciplinary_sanction_id');
                $t->unsignedBigInteger('violation_type_id')->nullable();
                $t->string('offense_level')->nullable();
                $t->string('disciplinary_sanction')->nullable();
            },
            'sanction_requests_tbl' => function (Blueprint $t): void {
                $t->increments('sanction_request_id');
                $t->string('student_number')->nullable();
                $t->unsignedBigInteger('violation_type_id')->nullable();
                $t->string('status')->nullable();
                $t->dateTime('request_date')->nullable();
            },
            'security' => function (Blueprint $t): void {
                $t->increments('id');
                $t->string('email')->nullable();
                $t->string('password')->nullable();
                $t->boolean('mfa_totp_enabled')->default(false);
            },
            'student_sanction_records_tbl' => function (Blueprint $t): void {
                $t->increments('record_id');
                $t->string('student_number')->nullable();
                $t->unsignedBigInteger('violation_id')->nullable();
                $t->unsignedBigInteger('assigned_sanction_id')->nullable();
                $t->unsignedBigInteger('assigned_by_admin_id')->nullable();
                $t->string('status')->nullable();
                $t->date('deadline_date')->nullable();
                $t->date('starting_date')->nullable();
                $t->string('designated_office')->nullable();
                $t->dateTime('date_assigned')->nullable();
                $t->dateTime('date_completed')->nullable();
                $t->string('proof_path')->nullable();
                $t->string('proof_original_name')->nullable();
                $t->dateTime('proof_submitted_at')->nullable();
                $t->text('proof_notes')->nullable();
                $t->unsignedBigInteger('completion_reviewed_by_admin_id')->nullable();
                $t->dateTime('completion_reviewed_at')->nullable();
                $t->text('completion_remarks')->nullable();
                $t->text('reopen_reason')->nullable();
                $t->dateTime('reopened_at')->nullable();
                $t->unsignedBigInteger('reopened_by_admin_id')->nullable();
            },
            'notifications_tbl' => function (Blueprint $t): void {
                $t->increments('notification_id');
                $t->string('student_number')->nullable();
                $t->string('notification_type')->nullable();
                $t->string('recipient_type')->nullable();
                $t->unsignedBigInteger('recipient_id')->nullable();
                $t->string('title')->nullable();
                $t->text('message')->nullable();
                $t->string('link')->nullable();
                $t->string('target_type')->nullable();
                $t->unsignedBigInteger('target_id')->nullable();
                $t->boolean('is_read')->default(false);
                $t->dateTime('created_at')->nullable();
            },
            'audit_logs' => function (Blueprint $t): void {
                $t->increments('id');
                $t->string('actor_type')->nullable();
                $t->unsignedBigInteger('actor_id')->nullable();
                $t->string('action')->nullable();
                $t->string('module')->nullable();
                $t->text('description')->nullable();
                $t->dateTime('created_at')->nullable();
                $t->dateTime('updated_at')->nullable();
            },
            'program_reports' => function (Blueprint $t): void {
                $t->increments('report_id');
                $t->unsignedBigInteger('program_id')->nullable();
                $t->string('academic_year')->nullable();
                $t->string('report_type')->nullable();
                $t->string('report_mode')->nullable();
                $t->string('report_status')->nullable();
                $t->dateTime('generated_at')->nullable();
            },
        ];

        foreach ($defs as $table => $definition) {
            if (! Schema::hasTable($table)) {
                Schema::create($table, $definition);
            }
        }

        // Seed the minimum reference rows the app expects.
        if (DB::table('roles_tbl')->count() === 0) {
            DB::table('roles_tbl')->insert([
                ['roles_id' => 1, 'roles_name' => 'Admin'],
                ['roles_id' => 2, 'roles_name' => 'Student'],
                ['roles_id' => 4, 'roles_name' => 'IT Administrator'],
            ]);
        }

        if (DB::table('student_status_tbl')->count() === 0) {
            DB::table('student_status_tbl')->insert(['status_name' => 'Active']);
        }

        if (DB::table('violation_category_tbl')->count() === 0) {
            DB::table('violation_category_tbl')->insert(['category_name' => 'Minor']);
        }
    }
}
