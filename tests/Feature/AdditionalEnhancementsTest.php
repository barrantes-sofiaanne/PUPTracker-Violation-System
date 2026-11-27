<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ViolationController;
use App\Models\User;
use App\Models\Violation;
use App\Services\OffenseNumberService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Coverage for the second enhancement batch:
 *
 *  1. Admin confirmation queue — oldest-first ordering, calculated offense
 *     numbers, and the offense filter (combined with category/type/date).
 *  2. Mobile responsiveness of the Security and OSS Staff record tables.
 *  3. "Courses" → "Programs" and "Middle Name" → "Middle Initials" labels.
 */
class AdditionalEnhancementsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
    }

    // ==================================================================
    // TASK 1 — ADMIN CONFIRMATION QUEUE
    // ==================================================================

    public function test_confirmation_queue_is_ordered_oldest_first_with_calculated_offense_numbers(): void
    {
        $student = $this->createStudent('2026-0001');
        $this->createViolationType(1, 1, 'Dress Code');

        // Confirmed 1st offense, then two pending violations.
        $this->createViolation($student, 1, 'confirmed', '2026-01-01 09:00:00');
        $second = $this->createViolation($student, 1, 'pending', '2026-01-02 09:00:00');
        $third = $this->createViolation($student, 1, 'pending', '2026-01-03 09:00:00');

        $queue = $this->confirmationQueue([]);

        $this->assertSame(
            [(int) $second->violation_id, (int) $third->violation_id],
            $this->ids($queue),
            'The confirmation queue must list the OLDEST pending violation first.'
        );

        $this->assertSame('2nd Offense', $queue[0]->offense_level);
        $this->assertSame('3rd Offense', $queue[1]->offense_level);
    }

    public function test_offense_filter_matches_the_calculated_sequence(): void
    {
        $student = $this->createStudent('2026-0001');
        $this->createViolationType(1, 1, 'Dress Code');

        $first = $this->createViolation($student, 1, 'confirmed', '2026-01-01 09:00:00');
        $second = $this->createViolation($student, 1, 'pending', '2026-01-02 09:00:00');
        $third = $this->createViolation($student, 1, 'pending', '2026-01-03 09:00:00');
        $fourth = $this->createViolation($student, 1, 'pending', '2026-01-04 09:00:00');

        $this->assertSame([(int) $second->violation_id], $this->ids($this->confirmationQueue(['offense' => '2'])));
        $this->assertSame([(int) $third->violation_id], $this->ids($this->confirmationQueue(['offense' => '3'])));
        $this->assertSame([(int) $fourth->violation_id], $this->ids($this->confirmationQueue(['offense' => '4plus'])));

        // The confirmed 1st offense is not part of the pending queue.
        $this->assertSame([], $this->ids($this->confirmationQueue(['offense' => '1'])));
        $this->assertSame([(int) $first->violation_id], [(int) $first->violation_id]);
    }

    public function test_rejecting_an_earlier_violation_recalculates_the_queue_numbers(): void
    {
        $student = $this->createStudent('2026-0001');
        $this->createViolationType(1, 1, 'Dress Code');

        $first = $this->createViolation($student, 1, 'pending', '2026-01-01 09:00:00');
        $second = $this->createViolation($student, 1, 'pending', '2026-01-02 09:00:00');
        $third = $this->createViolation($student, 1, 'pending', '2026-01-03 09:00:00');

        $this->assertSame('2nd Offense', $second->fresh()->offense_level);
        $this->assertSame([(int) $second->violation_id], $this->ids($this->confirmationQueue(['offense' => '2'])));

        // Administrator rejects the earliest violation.
        DB::table('violation_tbl')->where('violation_id', $first->violation_id)->update([
            'status' => Violation::STATUS_DENIED,
        ]);

        $queue = $this->confirmationQueue([]);
        $this->assertSame([(int) $second->violation_id, (int) $third->violation_id], $this->ids($queue));
        $this->assertSame('1st Offense', $queue[0]->offense_level);
        $this->assertSame('2nd Offense', $queue[1]->offense_level);

        $this->assertSame(
            [(int) $second->violation_id],
            $this->ids($this->confirmationQueue(['offense' => '1'])),
            'The filter must follow the RECALCULATED number, not a stored value.'
        );
    }

    public function test_offense_filter_combines_with_category_and_violation_type_filters(): void
    {
        $student = $this->createStudent('2026-0001');
        $this->createViolationType(1, 1, 'Dress Code');
        $this->createViolationType(2, 2, 'Academic Dishonesty');

        $dress = $this->createViolation($student, 1, 'pending', '2026-01-01 09:00:00');
        $academic = $this->createViolation($student, 2, 'pending', '2026-01-02 09:00:00');

        // Both are 1st Offense of their own type, but different categories.
        $this->assertSame('1st Offense', $dress->fresh()->offense_level);
        $this->assertSame('1st Offense', $academic->fresh()->offense_level);

        $this->assertSame(
            [(int) $dress->violation_id],
            $this->ids($this->confirmationQueue(['offense' => '1', 'category' => '1']))
        );

        $this->assertSame(
            [(int) $academic->violation_id],
            $this->ids($this->confirmationQueue(['offense' => '1', 'violation_type' => '2']))
        );

        // Date filter still applies alongside the offense filter.
        $this->assertSame(
            [],
            $this->ids($this->confirmationQueue(['offense' => '1', 'from' => '2026-01-05']))
        );
    }

    public function test_unknown_offense_filter_falls_back_to_all_offenses(): void
    {
        $student = $this->createStudent('2026-0001');
        $this->createViolationType(1, 1, 'Dress Code');
        $this->createViolation($student, 1, 'pending', '2026-01-01 09:00:00');

        $queue = $this->confirmationQueue(['offense' => '9']);

        $this->assertCount(1, $queue);
        $this->assertSame('1st Offense', $queue[0]->offense_level);
    }

    public function test_offense_sql_expression_agrees_with_the_php_sequence(): void
    {
        $student = $this->createStudent('2026-0001');
        $this->createViolationType(1, 1, 'Dress Code');

        $this->createViolation($student, 1, 'pending', '2026-01-05 09:00:00');
        $this->createViolation($student, 1, 'confirmed', '2026-01-01 09:00:00');
        $this->createViolation($student, 1, 'denied', '2026-01-03 09:00:00');
        $this->createViolation($student, 1, 'deleted', '2026-01-02 09:00:00');
        $this->createViolation($student, 1, 'pending', '2026-01-04 09:00:00');

        $service = app(OffenseNumberService::class);
        $sql = $service->offenseNumberSql();

        $fromSql = DB::select("SELECT violation_id, {$sql} AS offense_number FROM violation_tbl");
        $sqlNumbers = [];
        foreach ($fromSql as $row) {
            $sqlNumbers[(int) $row->violation_id] = (int) $row->offense_number;
        }

        $fromPhp = $service->offenseNumbersFor((int) $student->user_id, 1);

        foreach ($fromPhp as $violationId => $number) {
            $this->assertSame($number, $sqlNumbers[$violationId], "SQL and PHP disagree for violation {$violationId}.");
        }

        // Rejected/deleted rows report 0 and never shift later rows.
        // Three of the five rows are eligible, so the last slot is 3rd Offense.
        $this->assertSame(3, max($sqlNumbers));
        $this->assertSame(0, $sqlNumbers[(int) DB::table('violation_tbl')->where('status', 'denied')->value('violation_id')]);
        $this->assertSame(0, $sqlNumbers[(int) DB::table('violation_tbl')->where('status', 'deleted')->value('violation_id')]);
    }

    public function test_confirmation_view_renders_the_offense_filter_and_calculated_number(): void
    {
        $view = file_get_contents(resource_path('views/admin/violations/tabs/confirmations.blade.php'));

        foreach (['name="offense"', 'All Offenses', '1st Offense', '2nd Offense', '3rd Offense', '4th Offense and succeeding'] as $needle) {
            $this->assertStringContainsString($needle, $view);
        }

        foreach (['name="category"', 'name="violation_type"', 'name="from"', 'name="to"', 'name="search"'] as $needle) {
            $this->assertStringContainsString($needle, $view);
        }

        $this->assertStringContainsString('$violation->offense_level', $view);
        $this->assertStringContainsString('Oldest violation first', $view);
        $this->assertStringContainsString('data-offense="{{ $violation->offense_level', $view);

        // The review modal shows the offense before the admin confirms.
        $modal = file_get_contents(resource_path('views/admin/violations/partials/confirmation-modals.blade.php'));
        $this->assertStringContainsString('id="confirmModalOffense"', $modal);
    }

    // ==================================================================
    // TASK 2 — RESPONSIVE SECURITY / OSS STAFF TABLES
    // ==================================================================

    public function test_security_and_oss_tables_use_the_responsive_card_markup(): void
    {
        $tables = [
            'resources/views/security/dashboard.blade.php',
            'resources/views/oss-staff/dashboard.blade.php',
            'resources/views/security/violations/partials/submitted-violations.blade.php',
            'resources/views/oss-staff/violations/partials/submitted-violations.blade.php',
        ];

        foreach ($tables as $relative) {
            $contents = file_get_contents(base_path($relative));

            $this->assertStringContainsString('pt-responsive-table', $contents, "{$relative} must opt into the responsive table.");
            $this->assertStringContainsString('data-label=', $contents, "{$relative} must label every mobile cell.");
        }

        // The Security dashboard must keep all required mobile fields.
        $securityDashboard = file_get_contents(base_path('resources/views/security/dashboard.blade.php'));
        foreach (['data-label="Student"', 'data-label="Violation Type"', 'data-label="Offense Level"', 'data-label="Date &amp; Time"', 'data-label="Action"'] as $label) {
            $this->assertStringContainsString($label, $securityDashboard);
        }

        $ossDashboard = file_get_contents(base_path('resources/views/oss-staff/dashboard.blade.php'));
        foreach (['data-label="Student"', 'data-label="Violation Type"', 'data-label="Offense Level"', 'data-label="Date & Time"', 'data-label="Action"'] as $label) {
            $this->assertStringContainsString($label, $ossDashboard);
        }
    }

    public function test_submitted_violation_tables_keep_every_field_on_mobile(): void
    {
        foreach ([
            'resources/views/security/violations/partials/submitted-violations.blade.php',
            'resources/views/oss-staff/violations/partials/submitted-violations.blade.php',
        ] as $relative) {
            $contents = file_get_contents(base_path($relative));

            foreach (['Date', 'Student', 'Violation', 'Remarks', 'Evidence', 'Status', 'Review Note'] as $label) {
                $this->assertStringContainsString('data-label="' . $label . '"', $contents, "{$relative} is missing the {$label} label.");
            }
        }
    }

    public function test_shared_stylesheet_contains_the_mobile_card_layout(): void
    {
        $css = file_get_contents(public_path('css/module-dashboard-ui.css'));

        $this->assertStringContainsString('.pt-responsive-table', $css);
        $this->assertStringContainsString('attr(data-label)', $css);
        $this->assertStringContainsString('.module-oss .portal-main .table-responsive.pt-responsive-table', $css);
        $this->assertStringContainsString('.module-security #managementTableContainer', $css);
        $this->assertStringContainsString('.module-oss #managementTableContainer', $css);

        // The mobile card rules must live inside a max-width media query.
        $this->assertMatchesRegularExpression(
            '/@media\s*\(max-width:\s*767\.98px\)\s*\{[\s\S]*?\.pt-responsive-table/',
            $css
        );
    }

    // ==================================================================
    // TASK 3 — LABELS
    // ==================================================================

    public function test_user_facing_labels_use_programs_and_middle_initials(): void
    {
        $checks = [
            'resources/views/admin/reports/tabs/violation-reports.blade.php' => ['Program', 'All Programs'],
            'resources/views/admin/reports/pdf.blade.php' => ['Program'],
            'resources/views/admin/students/index.blade.php' => ['Middle Initials', 'Program'],
            'resources/views/admin/students/edit.blade.php' => ['Middle Initials'],
            'resources/views/security/violations/show.blade.php' => ['Program:'],
            'resources/views/oss-staff/violations/show.blade.php' => ['Program:'],
            'resources/views/student/profile.blade.php' => ['Program'],
            'resources/views/student/idp-link.blade.php' => ['Program'],
            'resources/views/admin/violations/partials/add-modal.blade.php' => ['Program'],
            'resources/views/student/idp-link-preview.blade.php' => ['Middle Initials'],
        ];

        foreach ($checks as $relative => $needles) {
            $contents = file_get_contents(base_path($relative));

            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $contents, "{$relative} must show '{$needle}'.");
            }
        }
    }

    public function test_no_user_facing_old_labels_remain(): void
    {
        $views = [
            'resources/views/admin/students/index.blade.php',
            'resources/views/admin/students/edit.blade.php',
            'resources/views/admin/reports/tabs/violation-reports.blade.php',
            'resources/views/admin/reports/pdf.blade.php',
            'resources/views/admin/violations/partials/add-modal.blade.php',
            'resources/views/security/violations/show.blade.php',
            'resources/views/oss-staff/violations/show.blade.php',
            'resources/views/student/profile.blade.php',
            'resources/views/student/idp-link.blade.php',
            'resources/views/student/idp-link-preview.blade.php',
        ];

        foreach ($views as $relative) {
            $contents = file_get_contents(base_path($relative));

            $this->assertStringNotContainsString('Middle Name', $contents, "{$relative} still shows 'Middle Name'.");
        }

        // "Course" may only survive as a backend identifier, never as a label.
        foreach ($views as $relative) {
            $contents = file_get_contents(base_path($relative));

            $this->assertDoesNotMatchRegularExpression(
                '/>\s*Courses?\s*</',
                $contents,
                "{$relative} still renders a Course label."
            );
        }
    }

    public function test_renaming_labels_did_not_change_backend_field_names(): void
    {
        $index = file_get_contents(base_path('resources/views/admin/students/index.blade.php'));

        // The submitted field / database column must stay `middle_name` /
        // `middlename`; only the visible label changed.
        $this->assertStringContainsString('name="middle_name"', $index);
        $this->assertStringContainsString('name="middlename"', $index);

        $report = file_get_contents(base_path('resources/views/admin/reports/tabs/violation-reports.blade.php'));
        $this->assertStringContainsString('name="course"', $report, 'The report filter parameter must stay `course`.');
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    /**
     * Invoke the protected confirmation-queue builder with the same query
     * parameters the Admin page receives.
     *
     * @return array<int, Violation>
     */
    private function confirmationQueue(array $query): array
    {
        $request = Request::create('/admin/violations', 'GET', array_merge(
            ['tab' => 'confirmations'],
            $query
        ));

        $controller = app(ViolationController::class);
        $method = new ReflectionMethod($controller, 'buildConfirmationsPayload');
        $method->setAccessible(true);

        $payload = $method->invoke($controller, $request);

        return array_values($payload['violations']->items());
    }

    /**
     * @param  array<int, Violation>  $rows
     * @return array<int, int>
     */
    private function ids(array $rows): array
    {
        return array_map(static fn (Violation $row): int => (int) $row->violation_id, $rows);
    }

    private function createStudent(?string $studentNumber): User
    {
        return User::create([
            'student_number' => $studentNumber,
            'first_name' => 'Juan',
            'middle_name' => null,
            'last_name' => 'Dela Cruz',
            'email' => ($studentNumber ?: 'student-' . uniqid()) . '@example.com',
            'password_hash' => 'x',
            'roles_id' => 2,
            'status_id' => 1,
            'profile_completed_at' => now(),
        ]);
    }

    private function createViolationType(int $id, int $categoryId, string $name): void
    {
        DB::table('violation_type_tbl')->insert([
            'violation_type_id' => $id,
            'violation_type' => $name,
            'violation_category_id' => $categoryId,
            'resolution_number' => 'RES-001',
            'date_published' => now(),
            'severity_level' => 1,
        ]);
    }

    private function createViolation(User $student, int $typeId, string $status, string $date): Violation
    {
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

    private function createSchema(): void
    {
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
            $table->string('password_hash')->nullable();
            $table->unsignedInteger('roles_id')->nullable();
            $table->unsignedInteger('status_id')->nullable();
            $table->unsignedInteger('gender_id')->nullable();
            $table->timestamp('profile_completed_at')->nullable();
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
            $table->string('recorder_type')->nullable();
            $table->string('recorder_name')->nullable();
            $table->unsignedInteger('recorder_id')->nullable();
            $table->unsignedBigInteger('recorded_by_security_id')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_remarks')->nullable();
        });

        Schema::create('admins', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username')->nullable();
            $table->string('email')->unique();
            $table->string('password');
        });

        Schema::create('admin_info_tbl', function (Blueprint $table): void {
            $table->increments('admin_info_id');
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('firstname')->nullable();
            $table->string('middlename')->nullable();
            $table->string('lastname')->nullable();
        });

        Schema::create('violation_evidence_tbl', function (Blueprint $table): void {
            $table->increments('evidence_id');
            $table->unsignedInteger('violation_id')->nullable();
            $table->string('access_key')->nullable();
            $table->string('evidence_code')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('capture_method')->nullable();
            $table->string('disk_path')->nullable();
            $table->boolean('watermarked')->default(false);
        });
    }
}
