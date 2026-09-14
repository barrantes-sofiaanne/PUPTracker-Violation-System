<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Program;
use App\Models\StudentSanctionRecord;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationCategory;
use App\Models\ViolationEvidence;
use App\Models\ViolationType;
use App\Services\PrivateFileResponder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Focused coverage for the four requirements implemented after the first batch:
 *
 *   E. Admin Record Violation UI matches the Security module's wizard.
 *   G. Admin category/type selection uses the SAME data as Security.
 *   H. Admin/Super Admin module header matches Security (+ PHT clock).
 *   K. Evidence/proof viewing returns controlled errors, never raw 404 pages.
 */
class AdminSecurityParityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTables();
    }

    // ==================================================================
    // E. RECORD VIOLATION UI — SAME WIZARD AS SECURITY
    // ==================================================================

    /**
     * The Admin modal must render the same 4-step wizard skeleton the Security
     * modal does: the step bubbles, the step labels, the evidence + review
     * steps and the protected-capture notice.
     */
    public function test_admin_record_violation_renders_the_security_wizard_structure(): void
    {
        $admin = $this->createAdmin();
        $this->createCategory(1, 'ID VIOLATION');
        $this->createViolationType(10, 'No ID', 1);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.violations.index', ['tab' => 'management']))
            ->assertOk()
            ->getContent();

        $modal = $this->recordViolationModal($html);
        $this->assertNotSame('', $modal, 'The Record Violation modal must render.');

        // The 4 step indicators, with the same element ids Security uses.
        foreach (['studentStepIndicator', 'violationStepIndicator', 'evidenceStepIndicator', 'reviewStepIndicator'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $modal, "Missing step indicator {$id}.");
        }

        // The 4 step CONTAINERS.
        foreach (['studentStep', 'violationStep', 'evidenceStep', 'reviewStep'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $modal, "Missing step container {$id}.");
        }

        // Step labels. Matched without requiring exact whitespace, because the
        // Blade markup puts each label on its own line between tags.
        foreach (['Student', 'Violation', 'Evidence', 'Review'] as $label) {
            $this->assertMatchesRegularExpression(
                '/>\s*' . $label . '\s*</',
                $modal,
                "Missing step label {$label}."
            );
        }

        // Wizard visual classes (shared presentation with Security).
        $this->assertStringContainsString('wizard-bubble', $modal);
        $this->assertStringContainsString('wizard-card', $modal);

        // Header: same icon + title as Security, with a resolvable label id.
        $this->assertStringContainsString('bi-shield-exclamation', $modal);
        $this->assertStringContainsString('Record Student Violation', $modal);
        $this->assertStringContainsString('id="addViolationModalLabel"', $modal);
        $this->assertStringContainsString('aria-labelledby="addViolationModalLabel"', $modal);

        // Evidence step: protected capture notice + camera + PDF upload.
        $this->assertStringContainsString('id="cameraWrap"', $modal);
        $this->assertStringContainsString('cameraPreview', $modal);
        $this->assertStringContainsString('bi-shield-lock', $modal);

        // The obscured-camera behaviour is a CSS rule (declared in the style
        // block, which Blade strips from the rendered body), so it is asserted
        // against the partial source in the CSS-parity test below.
        $partial = file_get_contents(resource_path('views/admin/violations/partials/record-violation.blade.php'));
        $this->assertStringContainsString('#cameraPreview.camera-obscured', $partial);
        $this->assertStringContainsString('#cameraWrap.video-interrupted #cameraPreview', $partial);

        // Review step + footer navigation.
        $this->assertStringContainsString('id="backStep"', $modal);
        $this->assertStringContainsString('id="saveViolation"', $modal);
    }

    /**
     * Presentation must MATCH Security: the Admin modal reproduces the Security
     * visual values (hero header gradient, wizard radius/shadow, preview box).
     */
    public function test_admin_record_violation_css_matches_security_values(): void
    {
        $adminPartial = file_get_contents(resource_path('views/admin/violations/partials/record-violation.blade.php'));
        $securityPartial = file_get_contents(resource_path('views/security/violations/partials/record-violation.blade.php'));

        // Values Security defines that must also be present in the Admin partial.
        $sharedValues = [
            'width: 52px',                       // wizard bubble
            'height: 52px',
            'border-radius: 999px',
            'font-weight: 800',
            'border-radius: 0.9rem',             // preview box
            'background: #fffdf7',               // preview box fill
            'filter: blur(18px) brightness(0.5)', // obscured camera
            'transition: filter 0.15s ease',
            'rgba(122, 15, 30, 0.2)',            // input border tone
        ];

        foreach ($sharedValues as $value) {
            $this->assertStringContainsString($value, $securityPartial, "Sanity: Security should define {$value}");
            $this->assertStringContainsString($value, $adminPartial, "Admin modal must match Security's {$value}");
        }

        // The Admin modal keeps its OWN class scope (no leaking into other pages).
        $this->assertStringContainsString('.admin-record-modal', $adminPartial);
        $this->assertStringNotContainsString('.security-record-modal', $adminPartial);
    }

    // ==================================================================
    // G. CATEGORY / TYPE PARITY
    // ==================================================================

    /**
     * Admin and Security must read the SAME category records, with the same
     * names — no separate hard-coded Admin list.
     */
    public function test_admin_category_dropdown_uses_the_same_records_as_security(): void
    {
        $admin = $this->createAdmin();

        $this->createCategory(1, 'ID VIOLATION');
        $this->createCategory(2, 'DRESS CODE POLICY');
        $this->createCategory(3, 'STUDENT CONDUCT');

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.violations.index', ['tab' => 'management']))
            ->assertOk()
            ->getContent();

        $modal = $this->recordViolationModal($html);

        // Every DB category appears, by name.
        foreach (['ID VIOLATION', 'DRESS CODE POLICY', 'STUDENT CONDUCT'] as $name) {
            $this->assertStringContainsString($name, $modal);
        }

        // The dropdown must NOT append the raw category id to the label the way
        // it used to (e.g. "ID VIOLATION (1)").
        $this->assertStringNotContainsString('ID VIOLATION (1)', $modal);
        $this->assertStringNotContainsString('STUDENT CONDUCT (3)', $modal);

        // The option VALUE is still the real id — ids stay internal to selects.
        $this->assertStringContainsString('value="1"', $modal);
    }

    /**
     * Selecting a category returns exactly the types stored for it, and the
     * type NAME is what the UI renders (never the numeric id).
     */
    public function test_category_selection_returns_the_same_types_and_renders_the_name(): void
    {
        $admin = $this->createAdmin();

        $this->createCategory(1, 'ID VIOLATION');
        $this->createCategory(2, 'STUDENT CONDUCT');

        // The requirement's example: type id 10 named "No ID".
        $this->createViolationType(10, 'No ID', 1);
        $this->createViolationType(11, 'Wearing of ID', 1);
        $this->createViolationType(20, 'Bullying', 2);

        // The endpoint the dropdown depends on returns this category's types.
        $response = $this->actingAs($admin, 'admin')
            ->getJson(route('admin.violations.types', ['category_id' => 1]))
            ->assertOk();

        $types = collect($response->json());
        $this->assertSame([10, 11], $types->pluck('violation_type_id')->sort()->values()->all());
        $this->assertSame(['No ID', 'Wearing of ID'], $types->pluck('violation_type')->sort()->values()->all());

        // Category 2's types are excluded — the filtering is real.
        $this->assertNotContains('Bullying', $types->pluck('violation_type')->all());
    }

    /**
     * The Record Violation UI must show the human-readable type name. A row
     * whose `violation_type` column holds the numeric id must NOT print "10".
     */
    public function test_violation_type_renders_name_not_numeric_id(): void
    {
        $admin = $this->createAdmin();

        $category = $this->createCategory(1, 'ID VIOLATION');
        $this->createViolationType(10, 'No ID', 1);

        $this->createStudentRow('2026-7001');

        DB::table('violation_tbl')->insert([
            'student_number' => '2026-7001',
            'violation_type' => '10',
            'violation_date' => now(),
            'description' => 'No ID presented.',
            'recorder_type' => 'admin',
            'recorder_name' => 'Admin',
            'status' => Violation::STATUS_CONFIRMED,
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.violations.show', ['student_number' => '2026-7001']))
            ->assertOk()
            ->getContent();

        // The NAME is resolved from violation_type_tbl, not hard-coded.
        $this->assertStringContainsString('No ID', $html);

        // The raw numeric id must not be rendered as the Violation TYPE cell.
        // Scoped to the summary table: a bare "<td>10</td>" elsewhere on the
        // page (e.g. a count column) is unrelated to type rendering.
        $summary = substr(
            $html,
            (int) strpos($html, 'Violation Summary'),
            (int) strpos($html, 'Violation History') - (int) strpos($html, 'Violation Summary')
        );

        $this->assertStringContainsString('No ID', $summary);
        $this->assertDoesNotMatchRegularExpression(
            '/<td>\s*10\s*<\/td>/',
            $summary,
            'The violation type must not be rendered as the raw numeric id.'
        );
    }

    // ==================================================================
    // H. HEADER + PHT TIME
    // ==================================================================

    public function test_admin_pages_use_the_standardized_module_header(): void
    {
        $admin = $this->createAdmin();

        foreach ([
            route('admin.dashboard'),
            route('admin.students'),
        ] as $url) {
            $html = $this->actingAs($admin, 'admin')->get($url)->assertOk()->getContent();

            // The Security hero structure: hero class + title + PHT clock.
            $this->assertStringContainsString('page-header-modern', $html, "Missing header on {$url}");
            $this->assertStringContainsString('dashboard-hero-grid', $html, "Missing hero grid on {$url}");
            $this->assertStringContainsString('dashboard-time-label', $html, "Missing time label on {$url}");
            $this->assertStringContainsString('Philippine Standard Time', $html, "Missing PHT label on {$url}");
            $this->assertStringContainsString('id="phTimeDisplay"', $html, "Missing clock on {$url}");
            $this->assertStringContainsString('bi-clock-history', $html, "Missing clock icon on {$url}");
        }
    }

    /**
     * The clock must be the SAME implementation as Security's: en-PH locale,
     * Asia/Manila, 24-hour, suffixed " PHT".
     */
    public function test_header_clock_uses_the_same_pht_formatting_as_security(): void
    {
        $admin = $this->createAdmin();

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        // Same formatter options Security uses.
        $this->assertStringContainsString("timeZone: 'Asia/Manila'", $html);
        $this->assertStringContainsString("'en-PH'", $html);
        $this->assertStringContainsString('hour12: false', $html);
        $this->assertStringContainsString("+ ' PHT'", $html);

        // Exactly ONE clock implementation per page (no duplicate timers).
        $this->assertSame(1, substr_count($html, "getElementById('phTimeDisplay')"));
    }

    public function test_super_admin_dashboard_uses_the_standardized_header(): void
    {
        $super = $this->createAdmin(true);

        $html = $this->actingAs($super, 'admin')
            ->get(route('admin.super-admin.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('page-header-modern', $html);
        $this->assertStringContainsString('dashboard-hero-grid', $html);
        $this->assertStringContainsString('id="phTimeDisplay"', $html);
        $this->assertStringContainsString('Super Admin Dashboard', $html);
    }

    // ==================================================================
    // K. EVIDENCE / PROOF — CONTROLLED ERRORS
    // ==================================================================

    public function test_existing_evidence_is_served_from_private_storage(): void
    {
        $admin = $this->createAdmin();
        Storage::fake('local');
        Storage::disk('local')->put('violation-evidence/1/EVD-1.jpg', 'image-bytes');

        $evidence = $this->createEvidence('violation-evidence/1/EVD-1.jpg');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.violations.evidence', $evidence))
            ->assertOk();
    }

    /**
     * A stored key with a legacy/duplicated prefix must still resolve, so older
     * rows keep working. The database value is NOT rewritten.
     */
    public function test_legacy_prefixed_evidence_paths_still_resolve(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('violation-evidence/1/EVD-2.jpg', 'image-bytes');

        foreach ([
            '/violation-evidence/1/EVD-2.jpg',
            'storage/violation-evidence/1/EVD-2.jpg',
            '/storage/violation-evidence/1/EVD-2.jpg',
        ] as $legacyPath) {
            $this->assertSame(
                'violation-evidence/1/EVD-2.jpg',
                PrivateFileResponder::resolveExistingKey($legacyPath),
                "Legacy path {$legacyPath} should resolve to the stored object key."
            );
        }
    }

    public function test_missing_evidence_path_returns_a_controlled_error(): void
    {
        $admin = $this->createAdmin();
        Storage::fake('local');

        // No disk_path stored at all.
        $evidence = $this->createEvidence(null);

        // The request fails with a CONTROLLED 404 whose message is the friendly
        // application message — not a raw filesystem error.
        $this->assertControlledError(
            fn () => $this->actingAs($admin, 'admin')->get(route('admin.violations.evidence', $evidence)),
            404,
            'Evidence could not be found'
        );
    }

    public function test_evidence_object_missing_from_storage_returns_a_controlled_error(): void
    {
        $admin = $this->createAdmin();
        Storage::fake('local');

        // A path is stored, but the object does not exist on the disk.
        $evidence = $this->createEvidence('violation-evidence/1/GONE.jpg');

        $this->assertControlledError(
            fn () => $this->actingAs($admin, 'admin')->get(route('admin.violations.evidence', $evidence)),
            404,
            'Evidence could not be found'
        );
    }

    public function test_missing_proof_path_returns_a_controlled_error(): void
    {
        $admin = $this->createAdmin();
        Storage::fake('local');

        $this->createStudentRow('2026-7101');
        $record = StudentSanctionRecord::create([
            'student_number' => '2026-7101',
            'violation_id' => 1,
            'status' => StudentSanctionRecord::STATUS_COMPLETED,
            'proof_path' => null,
        ]);

        $this->assertControlledError(
            fn () => $this->actingAs($admin, 'admin')->get(route('admin.sanctions.proof.download', $record)),
            404,
            'Proof document could not be found'
        );
    }

    public function test_proof_object_missing_from_storage_returns_a_controlled_error(): void
    {
        $admin = $this->createAdmin();
        Storage::fake('local');

        $this->createStudentRow('2026-7102');
        $record = StudentSanctionRecord::create([
            'student_number' => '2026-7102',
            'violation_id' => 1,
            'status' => StudentSanctionRecord::STATUS_COMPLETED,
            'proof_path' => 'sanction-proofs/1/PROOF-GONE.jpg',
        ]);

        $this->assertControlledError(
            fn () => $this->actingAs($admin, 'admin')->get(route('admin.sanctions.proof.download', $record)),
            404,
            'Proof document could not be found'
        );
    }

    public function test_existing_proof_is_served_from_private_storage(): void
    {
        $admin = $this->createAdmin();
        Storage::fake('local');
        Storage::disk('local')->put('sanction-proofs/1/PROOF-1.jpg', 'proof-bytes');

        $this->createStudentRow('2026-7103');
        $record = StudentSanctionRecord::create([
            'student_number' => '2026-7103',
            'violation_id' => 1,
            'status' => StudentSanctionRecord::STATUS_COMPLETED,
            'proof_path' => 'sanction-proofs/1/PROOF-1.jpg',
            'proof_original_name' => 'proof.jpg',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.sanctions.proof.download', $record))
            ->assertOk();
    }

    public function test_unauthorized_users_cannot_view_evidence(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('violation-evidence/1/EVD-3.jpg', 'x');
        $evidence = $this->createEvidence('violation-evidence/1/EVD-3.jpg');

        // A guest is bounced by the existing admin guard — no file is served.
        $this->get(route('admin.violations.evidence', $evidence))->assertRedirect();

        // A student session cannot reach the ADMIN evidence endpoint.
        $this->createStudentRow('2026-7201');
        $student = User::where('student_number', '2026-7201')->firstOrFail();

        $this->actingAs($student, 'student')
            ->get(route('admin.violations.evidence', $evidence))
            ->assertRedirect();
    }

    public function test_controlled_error_messages_do_not_leak_storage_internals(): void
    {
        $admin = $this->createAdmin();
        Storage::fake('local');

        $evidence = $this->createEvidence('violation-evidence/1/SECRET-KEY.jpg');

        $body = $this->actingAs($admin, 'admin')
            ->get(route('admin.violations.evidence', $evidence))
            ->assertNotFound()
            ->getContent();

        // No key, bucket, disk or stack-trace detail leaks to the user.
        foreach (['SECRET-KEY', 'violation-evidence', 's3', 'r2', 'Stack trace', 'PDOException'] as $needle) {
            $this->assertStringNotContainsString($needle, $body, "Response must not leak '{$needle}'.");
        }
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    /**
     * Assert a request fails with a controlled HttpException carrying the
     * friendly application message (rather than a raw filesystem/DB error).
     */
    private function assertControlledError(callable $request, int $status, string $messageFragment): void
    {
        // The framework's handler renders HttpExceptions into a generic error
        // page, so it is disabled here to inspect the controlled exception
        // itself — which is what carries the friendly message.
        $this->withoutExceptionHandling();

        try {
            $request();
            $this->fail('Expected a controlled error response, but none was thrown.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame($status, $e->getStatusCode());
            $this->assertStringContainsString($messageFragment, $e->getMessage());
            return;
        }
    }

    /** Slice the rendered page down to the Record Violation modal. */
    private function recordViolationModal(string $html): string
    {
        // Match the modal ELEMENT (not the CSS class in the <style> block).
        $marker = 'class="modal fade admin-record-modal"';
        $start = strpos($html, $marker);

        if ($start === false) {
            return '';
        }

        // Walk back to that wrapper's opening '<div'.
        $open = strrpos(substr($html, 0, $start), '<div');
        if ($open !== false) {
            $start = $open;
        }

        return substr($html, $start);
    }

    /**
     * The Admin modal is the LAST modal rendered by the page, so everything from
     * its opening tag to the end of the document belongs to the record-violation
     * UI (including its own notice modal). Returning the remainder avoids
     * depending on Blade-stripped comment boundaries.
     */
    private function recordViolationModalToEnd(string $html): string
    {
        return $this->recordViolationModal($html);
    }

    private function createAdmin(bool $super = false): Admin
    {
        $admin = Admin::create([
            'username' => $super ? 'super@example.com' : 'admin@example.com',
            'email' => $super ? 'super@example.com' : 'admin@example.com',
            'password' => bcrypt('secret'),
            'role_id' => 1,
        ]);

        // Super-Admin access is gated by `super.admin`, which checks
        // Admin::isItAdministrator() — that reads a designation/position from
        // admin_info_tbl. The designation is part of the AUTHENTICATION fixture,
        // not of the header being tested.
        if ($super) {
            DB::table('admin_info_tbl')->insert([
                'admin_id' => $admin->id,
                'firstname' => 'Super',
                'lastname' => 'Admin',
                'position' => 'IT Administrator',
            ]);
        }

        return $admin;
    }

    private function createEvidence(?string $path): ViolationEvidence
    {
        $this->createStudentRow('2026-7301');

        $violation = Violation::create([
            'student_number' => '2026-7301',
            'violation_type' => 1,
            'violation_date' => now(),
            'description' => 'Evidence case.',
            'recorder_type' => 'admin',
            'recorder_name' => 'Admin',
            'status' => Violation::STATUS_CONFIRMED,
        ]);

        return ViolationEvidence::create([
            'violation_id' => $violation->violation_id,
            'disk_path' => $path,
            'original_name' => 'evidence.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 10,
        ]);
    }

    private function createStudentRow(string $number): void
    {
        if (User::where('student_number', $number)->exists()) {
            return;
        }

        DB::table('users_tbl')->insert([
            'student_number' => $number,
            'first_name' => 'Test',
            'last_name' => 'Student',
            'email' => $number . '@example.com',
            'password_hash' => 'x',
            'roles_id' => 2,
            'status_id' => 1,
        ]);
    }

    private function createCategory(int $id, string $name): ViolationCategory
    {
        // `violation_category_id` is not fillable, so the id is written directly
        // to keep the fixtures deterministic.
        DB::table('violation_category_tbl')->insert([
            'violation_category_id' => $id,
            'category_name' => $name,
        ]);

        return ViolationCategory::findOrFail($id);
    }

    private function createViolationType(int $id, string $name, int $categoryId): void
    {
        DB::table('violation_type_tbl')->insert([
            'violation_type_id' => $id,
            'violation_type' => $name,
            'violation_category_id' => $categoryId,
            'severity_level' => 1,
        ]);
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
            'student_status_tbl' => function (Blueprint $t): void {
                $t->increments('student_status_id');
                $t->string('status_name')->nullable();
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
            'admins' => function (Blueprint $t): void {
                $t->increments('id');
                $t->string('username')->nullable();
                $t->string('email')->nullable();
                $t->string('password')->nullable();
                $t->unsignedBigInteger('role_id')->nullable();
            },
            'admin_info_tbl' => function (Blueprint $t): void {
                $t->increments('admin_info_id');
                $t->unsignedBigInteger('admin_id')->nullable();
                $t->string('firstname')->nullable();
                $t->string('middlename')->nullable();
                $t->string('lastname')->nullable();
                $t->string('position')->nullable();
                $t->unsignedBigInteger('role_id')->nullable();
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
                $t->string('violation_description')->nullable();
                $t->string('resolution_number')->nullable();
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
            'violation_evidence_tbl' => function (Blueprint $t): void {
                $t->increments('evidence_id');
                $t->unsignedBigInteger('violation_id')->nullable();
                $t->string('disk_path')->nullable();
                $t->string('original_name')->nullable();
                $t->string('mime_type')->nullable();
                $t->unsignedBigInteger('file_size')->nullable();
                $t->string('capture_session_id')->nullable();
                $t->timestamps();
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
            'evidence_security_events_tbl' => function (Blueprint $t): void {
                $t->increments('event_id');
                $t->string('capture_session_id', 64)->nullable();
                $t->unsignedBigInteger('violation_id')->nullable();
                $t->unsignedBigInteger('evidence_id')->nullable();
                $t->string('event_type', 80);
                $t->string('severity', 20)->default('info');
                $t->string('actor_type', 20)->nullable();
                $t->unsignedBigInteger('actor_id')->nullable();
                $t->string('ip_address', 64)->nullable();
                $t->string('user_agent', 500)->nullable();
                $t->text('meta')->nullable();
                $t->timestamp('created_at')->nullable();
            },
            'audit_logs' => function (Blueprint $t): void {
                $t->increments('id');
                $t->string('actor_type')->nullable();
                $t->unsignedBigInteger('actor_id')->nullable();
                $t->string('action')->nullable();
                $t->string('module')->nullable();
                $t->text('description')->nullable();
                $t->timestamps();
            },
            'security' => function (Blueprint $t): void {
                $t->increments('id');
                $t->string('email')->nullable();
                $t->string('password')->nullable();
                $t->boolean('mfa_totp_enabled')->default(false);
            },
            'maintenance_records' => function (Blueprint $t): void {
                $t->increments('maintenance_id');
                $t->string('maintenance_type')->nullable();
                $t->date('maintenance_date')->nullable();
                $t->text('description')->nullable();
                $t->string('performed_by')->nullable();
                $t->timestamps();
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
        ];

        foreach ($defs as $table => $definition) {
            if (! Schema::hasTable($table)) {
                Schema::create($table, $definition);
            }
        }

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
    }
}
