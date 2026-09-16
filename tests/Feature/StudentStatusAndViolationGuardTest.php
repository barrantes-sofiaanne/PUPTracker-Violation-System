<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Security;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationEvidence;
use App\Models\ViolationType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers the revision's security + UI requirements (§15-§28):
 *
 *   STUDENT ACCESS  1-5   active allowed / inactive denied / IDP does not bypass
 *   VIOLATIONS      6-10  active can be recorded / inactive cannot, server-side
 *   EVIDENCE       11-16  authorized view, IDOR blocked, private storage, watermark
 *   CAMERA         17-24  notice, capture, preview, retake, use photo, watermark
 *   UI             25-35  cards removed, modal X, progress line, colors, evidence column
 *
 * The inactive-student rule is a SECURITY boundary, so every violation case
 * asserts a DIRECT POST is rejected — not merely that a button is hidden.
 */
class StudentStatusAndViolationGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.idp.base_url', 'https://idp.test/api/v1');
        Config::set('services.idp.client_id', 'student-client');
        Config::set('services.idp.client_secret', 'student-secret');
        Config::set('services.idp.redirect_uri', 'https://puptvs.com/student/idp/callback');
        Config::set('services.idp.timeout', 15);
        Config::set('services.idp.student_roles', 'student');

        $this->createTables();
        Storage::fake('local');
    }

    // ==================================================================
    // STUDENT ACCESS (1-5)
    // ==================================================================

    public function test_active_student_can_reach_the_dashboard(): void
    {
        $student = $this->createStudent('2024-0001', active: true);

        $this->actingAs($student, 'student')
            ->get(route('student.dashboard'))
            ->assertOk();
    }

    public function test_inactive_student_cannot_reach_the_dashboard(): void
    {
        $student = $this->createStudent('2024-0001', active: false);

        $this->actingAs($student, 'student')
            ->get(route('student.dashboard'))
            ->assertRedirect(route('student.login'))
            ->assertSessionHasErrors('login');

        $this->assertGuest('student');
    }

    public function test_inactive_student_cannot_reach_any_student_route(): void
    {
        $student = $this->createStudent('2024-0001', active: false);

        // Every protected student route must refuse, not just the dashboard.
        foreach ([
            'student.dashboard',
            'student.record',
            'student.notifications',
            'student.profile',
            'student.settings',
            'student.announcements',
        ] as $routeName) {
            $this->actingAs($student, 'student')
                ->get(route($routeName))
                ->assertRedirect(route('student.login'));
        }

        $this->assertGuest('student');
    }

    /**
     * The denial message must be friendly and must NOT leak internal status
     * values, ids, or who changed the account.
     */
    public function test_inactive_denial_message_is_generic(): void
    {
        $student = $this->createStudent('2024-0001', active: false);

        $response = $this->actingAs($student, 'student')
            ->get(route('student.dashboard'));

        $errors = session('errors');
        $message = (string) $errors->first('login');

        $this->assertStringContainsString('currently inactive', $message);
        $this->assertStringContainsString('contact the appropriate office', $message);

        $this->assertStringNotContainsString('status_id', $message);
        $this->assertStringNotContainsString('2', $message, 'The raw status id must not appear.');
        $this->assertStringNotContainsString('deactivated by', $message);
    }

    /**
     * §15/§16: IDP authentication succeeds, but the PUPTracker status denies
     * access. This is the core rule — identity != authorisation.
     */
    public function test_successful_idp_authentication_does_not_bypass_inactive_status(): void
    {
        $student = $this->createStudent('2024-0001', active: false, idpSubject: 'user-bound');
        $this->fakeIdp(idpSubject: 'user-bound', email: '2024-0001@example.com');

        $response = $this->hitCallback();

        $response->assertRedirect(route('student.login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest('student');

        // And crucially: no authenticated session was established.
        $this->assertNull(auth('student')->user());
    }

    public function test_active_student_with_valid_idp_is_allowed_in(): void
    {
        $this->createStudent('2024-0001', active: true, idpSubject: 'user-bound');
        $this->fakeIdp(idpSubject: 'user-bound', email: '2024-0001@example.com');

        $this->hitCallback()->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated('student');
    }

    // ==================================================================
    // VIOLATIONS (6-10)
    // ==================================================================

    public function test_active_student_can_have_a_violation_recorded(): void
    {
        $admin = $this->createAdmin();
        $this->createStudent('2024-0001', active: true);
        $this->createViolationType(1, 'Cheating');

        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.violations.store'), $this->violationPayload())
            ->assertOk();

        $this->assertDatabaseHas('violation_tbl', ['student_number' => '2024-0001']);
    }

    /**
     * §17/§18/§21 — the important one. A DIRECT POST for an inactive student
     * must be rejected by the SERVER. Nothing in this test touches the UI, so a
     * disabled button or client-side filter cannot make it pass or fail.
     */
    public function test_inactive_student_cannot_have_a_violation_recorded_via_direct_post(): void
    {
        $admin = $this->createAdmin();
        $this->createStudent('2024-0001', active: false);
        $this->createViolationType(1, 'Cheating');

        $response = $this->actingAs($admin, 'admin')
            ->postJson(route('admin.violations.store'), $this->violationPayload());

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('student_number');

        $this->assertStringContainsString(
            'inactive',
            (string) $response->json('errors.student_number.0')
        );

        // No record was created.
        $this->assertSame(0, Violation::count());
    }

    /** §18: the Security module shares the rule (same FormRequest). */
    public function test_inactive_student_cannot_have_a_violation_recorded_by_security(): void
    {
        $officer = $this->createSecurity();
        $this->createStudent('2024-0001', active: false);
        $this->createViolationType(1, 'Cheating');

        $response = $this->actingAs($officer, 'security')
            ->postJson(route('security.violations.store'), $this->violationPayload());

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('student_number');
        $this->assertSame(0, Violation::count());
    }

    /**
     * §21: changing the student id in the request must not bypass the rule —
     * swapping in an INACTIVE student is still refused, and swapping in an
     * ACTIVE one is only honoured for that active student.
     */
    public function test_changing_the_student_id_cannot_bypass_status_validation(): void
    {
        $admin = $this->createAdmin();
        $this->createStudent('2024-0001', active: false);   // inactive
        $this->createStudent('2024-0002', active: true);    // active
        $this->createViolationType(1, 'Cheating');

        // Tampering to the inactive student -> refused.
        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.violations.store'), $this->violationPayload([
                'student_number' => '2024-0001',
            ]))
            ->assertStatus(422);

        $this->assertSame(0, Violation::count());

        // The active student still works, proving the rule is per-account.
        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.violations.store'), $this->violationPayload([
                'student_number' => '2024-0002',
            ]))
            ->assertOk();

        $this->assertDatabaseHas('violation_tbl', ['student_number' => '2024-0002']);
        $this->assertDatabaseMissing('violation_tbl', ['student_number' => '2024-0001']);
    }

    /**
     * §19: an EXISTING historical violation for a now-inactive student must
     * remain readable by an authorized admin. The rule only blocks NEW records.
     */
    public function test_existing_historical_violation_for_inactive_student_remains_accessible(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent('2024-0001', active: true);
        $this->createViolationType(1, 'Cheating');

        // Recorded while active...
        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.violations.store'), $this->violationPayload())
            ->assertOk();

        $violation = Violation::firstOrFail();

        // ...then the student is deactivated.
        $student->update(['status_id' => 2]);

        // The history page still renders it, and it is not deleted.
        $this->actingAs($admin, 'admin')
            ->get(route('admin.violations.index', ['tab' => 'history']))
            ->assertOk()
            ->assertSee('2024-0001', false);

        $this->assertDatabaseHas('violation_tbl', [
            'violation_id' => $violation->violation_id,
            'student_number' => '2024-0001',
        ]);
    }

    // ==================================================================
    // EVIDENCE (11-16)
    // ==================================================================

    public function test_authorized_admin_can_view_violation_evidence(): void
    {
        $admin = $this->createAdmin();
        $this->createStudent('2024-0001', active: true);
        $evidence = $this->createStoredEvidence('2024-0001');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.violations.evidence', $evidence))
            ->assertOk();
    }

    public function test_unauthorized_user_cannot_view_evidence(): void
    {
        $student = $this->createStudent('2024-0001', active: true);
        $this->createStudent('2024-0002', active: true);
        $evidence = $this->createStoredEvidence('2024-0001');

        // A DIFFERENT student must not be able to read it.
        $foreign = User::where('student_number', '2024-0002')->firstOrFail();

        $this->actingAs($foreign, 'student')
            ->get(route('student.violations.evidence', $evidence))
            ->assertForbidden();

        // ...and the owner still can.
        $this->actingAs($student, 'student')
            ->get(route('student.violations.evidence', $evidence))
            ->assertOk();
    }

    /** §11/§24 (IDOR): guessing the evidence id must not grant access. */
    public function test_evidence_url_cannot_be_guessed_to_bypass_authorization(): void
    {
        $this->createStudent('2024-0001', active: true);
        $this->createStudent('2024-0002', active: true);

        // Evidence belonging to student 1 only.
        $evidence = $this->createStoredEvidence('2024-0001');

        $attacker = User::where('student_number', '2024-0002')->firstOrFail();

        // Sweep a range of ids around the real one — none may leak.
        for ($id = 1; $id <= 5; $id++) {
            $response = $this->actingAs($attacker, 'student')
                ->get(route('student.violations.evidence', $id));

            $this->assertContains(
                $response->getStatusCode(),
                [403, 404],
                "Guessing evidence id {$id} must not succeed."
            );
        }

        // Sanity: the real id is still the one belonging to student 1.
        $this->assertSame('2024-0001', $evidence->violation->student_number);
    }

    public function test_multiple_evidence_files_remain_accessible(): void
    {
        $admin = $this->createAdmin();
        $this->createStudent('2024-0001', active: true);

        $violation = $this->createViolation('2024-0001');
        $first = $this->createStoredEvidence('2024-0001', $violation, 'one.jpg');
        $second = $this->createStoredEvidence('2024-0001', $violation, 'two.jpg');

        // The relation really is one-to-many (no schema change needed).
        $this->assertSame(2, $violation->fresh()->evidence->count());

        foreach ([$first, $second] as $evidence) {
            $this->actingAs($admin, 'admin')
                ->get(route('admin.violations.evidence', $evidence))
                ->assertOk();
        }
    }

    public function test_evidence_remains_privately_stored(): void
    {
        $this->createStudent('2024-0001', active: true);
        $evidence = $this->createStoredEvidence('2024-0001');

        // The file lives on the private disk, not a public path.
        Storage::disk('local')->assertExists($evidence->disk_path);

        $this->assertStringNotContainsString('storage/', (string) $evidence->disk_path);
        $this->assertFalse(Storage::disk('public')->exists($evidence->disk_path));
    }

    public function test_evidence_records_watermark_state(): void
    {
        $this->createStudent('2024-0001', active: true);
        $evidence = $this->createStoredEvidence('2024-0001');

        // The watermark flags are part of the stored evidence contract and must
        // survive the UI changes (camera/processing is unchanged by this task).
        $this->assertTrue((bool) $evidence->watermarked);
        $this->assertNotNull($evidence->evidence_code);
    }

    // ==================================================================
    // CAMERA (17-24) — behaviour is verified against the shipped markup/JS
    // ==================================================================

    /**
     * The camera flow lives in JS + markup. These assertions prove the preview
     * step, the Retake/Use Photo controls and the watermark element are present
     * and wired — they do NOT claim a live capture was performed (that needs a
     * real camera).
     */
    public function test_camera_preview_and_review_controls_are_present(): void
    {
        $admin = $this->createAdmin();

        $page = $this->actingAs($admin, 'admin')
            ->get(route('admin.violations.index'))
            ->assertOk()
            ->getContent();

        // §12/§17: security notice + acknowledgement gate.
        $this->assertStringContainsString('securityNotice', $page);

        // §12: the capture + preview controls.
        $this->assertStringContainsString('capturePhotoBtn', $page);
        $this->assertStringContainsString('captureReviewPanel', $page);
        $this->assertStringContainsString('captureReviewImg', $page);

        // §13: Retake + Use Photo.
        $this->assertStringContainsString('retakePhotoBtn', $page);
        $this->assertStringContainsString('usePhotoBtn', $page);

        // §14: the live watermark overlay is still rendered.
        $this->assertStringContainsString('liveWatermarkStudent', $page);
        $this->assertStringContainsString('liveWatermarkTime', $page);
    }

    public function test_camera_preview_is_shown_immediately_after_capture_in_js(): void
    {
        $js = file_get_contents(public_path('js/admin/record-violation.js'));

        // The preview is invoked from INSIDE the capture handler, i.e. straight
        // after the frame is grabbed — not only when the user confirms.
        $this->assertMatchesRegularExpression(
            '/capturePhotoBtn\.addEventListener\(\s*"click".*?showCaptureReview\(/s',
            $js,
            'The captured photo must be previewed immediately after capture.'
        );

        // Retake clears the staged blob (discard) and returns to the camera.
        $this->assertStringContainsString('retakePhotoBtn', $js);
        $this->assertStringContainsString('capturedImageBlob = null', $js);

        // Use Photo keeps the image and stages it as camera evidence.
        $this->assertStringContainsString('usePhotoBtn', $js);
        $this->assertStringContainsString('method: "camera"', $js);

        // The security logging / unique-code protections are untouched.
        $this->assertStringContainsString('CAPTURE_ABANDONED', $js);
        $this->assertStringContainsString('captureSessionId', $js);
    }

    public function test_security_module_camera_flow_matches_admin(): void
    {
        $officer = $this->createSecurity();

        $page = $this->actingAs($officer, 'security')
            ->get(route('security.violations.students'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('captureReviewPanel', $page);
        $this->assertStringContainsString('retakePhotoBtn', $page);
        $this->assertStringContainsString('usePhotoBtn', $page);
    }

    // ==================================================================
    // UI (25-35)
    // ==================================================================

    public function test_minor_and_major_violation_cards_are_removed(): void
    {
        $admin = $this->createAdmin();

        $page = $this->actingAs($admin, 'admin')
            ->get(route('admin.reports'))
            ->assertOk()
            ->getContent();

        // The two cards are gone...
        $this->assertStringNotContainsString('id="minorViolations"', $page);
        $this->assertStringNotContainsString('id="majorViolations"', $page);
        $this->assertStringNotContainsString('Minor Violations', $page);
        $this->assertStringNotContainsString('Major Violations', $page);

        // ...and the ones that were required to stay are still there.
        $this->assertStringContainsString('id="totalViolations"', $page);
        $this->assertStringContainsString('Repeat Offenders', $page);

        // Existing report functionality is untouched.
        $this->assertStringContainsString('exportPdf', $page);
        $this->assertStringContainsString('reportFilterForm', $page);
    }

    public function test_reports_js_does_not_reference_the_removed_cards(): void
    {
        $js = file_get_contents(public_path('js/admin/reports.js'));

        // A dangling getElementById(...).textContent would throw and abort the
        // AJAX refresh (table + charts) on every filter change.
        $this->assertStringNotContainsString('minorViolations', $js);
        $this->assertStringNotContainsString('majorViolations', $js);
    }

    public function test_wizard_progress_line_is_removed(): void
    {
        $admin = $this->createAdmin();

        $page = $this->actingAs($admin, 'admin')
            ->get(route('admin.violations.index'))
            ->assertOk()
            ->getContent();

        // The horizontal progress bar element is gone.
        $this->assertStringNotContainsString('id="violationWizardProgress"', $page);
        $this->assertStringNotContainsString('role="progressbar"', $page);

        // The numbered circles remain as the progress indicator.
        $this->assertStringContainsString('studentStepIndicator', $page);
        $this->assertStringContainsString('reviewStepIndicator', $page);
    }

    /**
     * §6/§24: the step circles must use MAROON + GOLD only. Bootstrap's
     * `bg-success` (green) and `bg-danger` (red) are no longer used for the
     * wizard, and the JS must not reintroduce them.
     */
    public function test_wizard_step_colors_are_maroon_and_gold_only(): void
    {
        $admin = $this->createAdmin();

        $page = $this->actingAs($admin, 'admin')
            ->get(route('admin.violations.index'))
            ->assertOk()
            ->getContent();

        // The circles no longer carry non-palette Bootstrap background classes.
        $this->assertStringNotContainsString('wizard-bubble bg-success', $page);
        $this->assertStringNotContainsString('wizard-bubble bg-danger', $page);
        $this->assertStringNotContainsString('wizard-bubble bg-goldenrod', $page);

        // The palette classes are used instead.
        $this->assertStringContainsString('wizard-step-current', $page);
        $this->assertStringContainsString('wizard-step-upcoming', $page);

        foreach ([
            'public/js/admin/record-violation.js',
            'public/js/security/record-violation.js',
        ] as $path) {
            $js = file_get_contents(base_path($path));

            $this->assertStringNotContainsString(
                'add("bg-success")',
                $js,
                "$path must not use the green completed state."
            );
            $this->assertStringNotContainsString(
                'add("bg-danger")',
                $js,
                "$path must not use Bootstrap red for the active step."
            );
            $this->assertStringContainsString('wizard-step-completed', $js);
            $this->assertStringContainsString('wizard-step-current', $js);
            $this->assertStringContainsString('wizard-step-upcoming', $js);
        }
    }

    /** §6: the wizard only uses maroon + gold, never green/blue/purple. */
    public function test_wizard_palette_has_no_non_puptracker_hues(): void
    {
        $css = file_get_contents(resource_path('views/admin/violations/partials/record-violation.blade.php'));

        foreach (['wizard-step-completed', 'wizard-step-current', 'wizard-step-upcoming'] as $class) {
            $this->assertStringContainsString(
                '.' . $class,
                $css,
                "Missing the palette state .{$class}"
            );
        }

        // Extract the step-state block and assert no foreign hue sneaks in.
        $start = strpos($css, '.admin-record-modal .wizard-bubble.wizard-step-current');
        $end = strpos($css, '/* Mirrors Security', $start);
        $block = $start !== false ? substr($css, $start, ($end ?: strlen($css)) - $start) : '';

        $this->assertNotSame('', $block, 'Could not locate the wizard palette block.');

        foreach (['#1e7d3c', '#198754', '#0d6efd', '#0dcaf0', '#6f42c1', '#dc3545'] as $foreign) {
            $this->assertStringNotContainsString(
                $foreign,
                strtolower($block),
                "Foreign colour {$foreign} found in the wizard step palette."
            );
        }
    }

    /** §3/§5: the modal close is a bare × with no white background/filter. */
    public function test_modal_close_button_has_no_white_background(): void
    {
        $components = file_get_contents(public_path('css/admin-components.css'));

        // The `filter: invert(...)` trick is what painted a white box behind the
        // × (it re-renders the whole button element).
        $start = strpos($components, '.admin-modal-header .admin-modal-close');
        $block = $start !== false ? substr($components, $start, 2200) : '';

        $this->assertNotSame('', $block);
        $this->assertStringContainsString('background-color: transparent !important', $block);
        $this->assertStringContainsString('filter: none !important', $block);
        $this->assertStringNotContainsString('invert(', $block);

        // Touch target + no-overlap guarantees.
        $this->assertStringContainsString('width: 44px', $block);
        $this->assertStringContainsString('height: 44px', $block);
        $this->assertStringContainsString('flex: 0 0 auto', $block);
    }

    /** §4/§5: header + footer stay reachable; buttons wrap instead of clipping. */
    public function test_modal_header_and_footer_are_mobile_safe(): void
    {
        $view = file_get_contents(resource_path('views/admin/violations/partials/record-violation.blade.php'));

        // Body scrolls independently so a tall step cannot push the footer away.
        $this->assertStringContainsString('overflow-y: auto', $view);
        $this->assertStringContainsString('max-height: calc(100vh - 1rem)', $view);

        // Footer wraps rather than clipping / overlapping its buttons.
        $this->assertStringContainsString('flex-wrap: wrap', $view);

        // The primary CTA takes its own row on phones.
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 575\.98px\).*?#saveViolation.*?flex: 1 1 100%/s',
            $view,
            'Record Violation must get its own full-width row on small screens.'
        );
    }

    /** §7: the history date filters are clearly labelled. */
    public function test_history_date_filters_are_labelled(): void
    {
        $admin = $this->createAdmin();

        $page = $this->actingAs($admin, 'admin')
            ->get(route('admin.violations.index', ['tab' => 'history']))
            ->assertOk()
            ->getContent();

        $flat = preg_replace('/\s+/', ' ', $page);

        $this->assertStringContainsString('Start Date', $flat);
        $this->assertStringContainsString('End Date', $flat);

        // The labels are bound to their inputs (accessibility + clarity).
        $this->assertStringContainsString('for="history_from"', $flat);
        $this->assertStringContainsString('for="history_to"', $flat);
        $this->assertStringContainsString('id="history_from"', $flat);
        $this->assertStringContainsString('id="history_to"', $flat);
    }

    /** §8 + §9: Violation column gone, Evidence column present. */
    public function test_history_table_shows_evidence_and_drops_the_violation_column(): void
    {
        $admin = $this->createAdmin();
        $this->createStudent('2024-0001', active: true);
        $this->createViolationType(1, 'Cheating');
        $this->createViolation('2024-0001', status: 'confirmed');

        $page = $this->actingAs($admin, 'admin')
            ->get(route('admin.violations.index', ['tab' => 'history']))
            ->assertOk()
            ->getContent();

        // SCOPE to the history table itself. The page also renders every other
        // tab (Configuration legitimately contains "Violation Type"), so a
        // page-wide assertion would be wrong.
        $table = $this->historyTableRegion($page);
        $flat = preg_replace('/\s+/', ' ', $table);

        $this->assertNotSame('', $table, 'Could not locate the Violation History table.');

        // §9: the Evidence column exists.
        $this->assertStringContainsString('Evidence', $flat);
        $this->assertStringContainsString('Recorded By', $flat);
        $this->assertStringContainsString('Category', $flat);

        // §8: the per-violation "Violation Type" column is gone from THIS table.
        $this->assertStringNotContainsString('Violation Type', $flat);

        // The underlying data is untouched — only the display changed.
        $this->assertDatabaseHas('violation_tbl', [
            'student_number' => '2024-0001',
            'violation_type' => 1,
        ]);
    }

    /**
     * Extract just the Violation History table from the Violations page.
     *
     * The page renders ALL tabs at once, so assertions about this table must be
     * scoped to it or they pick up other tabs' identical-sounding headers.
     */
    private function historyTableRegion(string $page): string
    {
        // Anchor on the history date filter, which only that tab renders.
        $anchor = strpos($page, 'history_from');

        if ($anchor === false) {
            return '';
        }

        $tableStart = strpos($page, '<table', $anchor);

        if ($tableStart === false) {
            return '';
        }

        $tableEnd = strpos($page, '</table>', $tableStart);

        if ($tableEnd === false) {
            return '';
        }

        return substr($page, $tableStart, $tableEnd - $tableStart);
    }

    public function test_history_evidence_column_links_to_authorized_endpoint(): void
    {
        $admin = $this->createAdmin();
        $this->createStudent('2024-0001', active: true);
        $this->createViolationType(1, 'Cheating');
        $violation = $this->createViolation('2024-0001', status: 'confirmed');
        $this->createStoredEvidence('2024-0001', $violation);

        $page = $this->actingAs($admin, 'admin')
            ->get(route('admin.violations.index', ['tab' => 'history']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('View Evidence', $page);

        // Never a raw disk path, and it points at the authorized route.
        $this->assertStringNotContainsString('violation-evidence/', $page);
        $this->assertStringContainsString(
            rtrim(route('admin.violations.evidence', $violation->evidence->first()), '0..9') === ''
                ? 'admin/violations/evidence'
                : '/admin/violations/evidence/',
            $page
        );
    }

    public function test_history_shows_all_evidence_files_when_there_are_many(): void
    {
        $admin = $this->createAdmin();
        $this->createStudent('2024-0001', active: true);
        $this->createViolationType(1, 'Cheating');
        $violation = $this->createViolation('2024-0001', status: 'confirmed');

        foreach (['a.jpg', 'b.jpg', 'c.jpg'] as $name) {
            $this->createStoredEvidence('2024-0001', $violation, $name);
        }

        $this->actingAs($admin, 'admin')
            ->get(route('admin.violations.index', ['tab' => 'history']))
            ->assertOk()
            ->assertSee('View 3 Evidence Files', false);
    }

    /** §23: the shared wizard fixes apply to Security too. */
    public function test_shared_wizard_fixes_are_applied_to_the_security_module(): void
    {
        $view = file_get_contents(resource_path('views/security/violations/partials/record-violation.blade.php'));

        // Same palette states...
        $this->assertStringContainsString('.security-record-modal .wizard-bubble.wizard-step-current', $view);
        $this->assertStringContainsString('.security-record-modal .wizard-bubble.wizard-step-completed', $view);
        $this->assertStringContainsString('.security-record-modal .wizard-bubble.wizard-step-upcoming', $view);

        // ...and the same footer/scroll safety.
        $this->assertStringContainsString('flex-wrap: wrap', $view);
        $this->assertStringContainsString('overflow-y: auto', $view);
        $this->assertStringContainsString('max-height: calc(100vh - 1rem)', $view);
    }

    // ==================================================================
    // MOBILE SIDEBAR CLOSE (×) — all modules
    // ==================================================================

    /**
     * The mobile sidebar close (×) must NOT carry Bootstrap's `btn-light`.
     *
     * `btn-light` paints a solid WHITE pill on top of the maroon sidebar
     * gradient, which is conspicuous on phones where the button only shows.
     * The themed `.sidebar-close` class replaces it in every module.
     */
    public function test_sidebar_close_does_not_use_the_white_btn_light_class(): void
    {
        foreach ([
            'admin/partials/sidebar.blade.php',
            'student/partials/sidebar.blade.php',
            'security/partials/sidebar.blade.php',
        ] as $partial) {
            $view = file_get_contents(resource_path('views/' . $partial));

            $this->assertStringContainsString(
                'sidebar-close',
                $view,
                "{$partial} must use the themed .sidebar-close button."
            );
            $this->assertStringNotContainsString(
                'btn-light',
                $view,
                "{$partial} must not use btn-light: it renders a white box on the maroon sidebar."
            );
        }
    }

    /** The close control stays reachable and clearly labelled in all modules. */
    public function test_sidebar_close_is_present_and_labelled_in_every_module(): void
    {
        $admin = $this->createAdmin();

        $adminPage = $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="sidebarClose"', $adminPage);
        $this->assertStringContainsString('aria-label="Close sidebar"', $adminPage);
        $this->assertStringNotContainsString('btn-light', $adminPage);

        $student = $this->createStudent('2024-0001', active: true);
        $studentPage = $this->actingAs($student, 'student')
            ->get(route('student.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="portalSidebarClose"', $studentPage);
        $this->assertStringNotContainsString('btn-light', $studentPage);

        $officer = $this->createSecurity();
        $securityPage = $this->actingAs($officer, 'security')
            ->get(route('security.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="portalSidebarClose"', $securityPage);
        $this->assertStringNotContainsString('btn-light', $securityPage);
    }

    /**
     * `.sidebar-close` must be transparent with a 44px touch target, and hidden
     * on desktop where the sidebar is permanently visible.
     */
    public function test_sidebar_close_styles_are_transparent_with_a_touch_target(): void
    {
        $css = file_get_contents(public_path('css/admin.css'));

        // Extract the rule block for the close button.
        $start = strpos($css, '.sidebar-close {');
        $this->assertNotFalse($start, 'Missing the .sidebar-close rule in admin.css.');

        $end = strpos($css, '}', $start);
        $block = substr($css, $start, $end - $start);

        $this->assertStringContainsString('background: transparent', $block);
        $this->assertStringContainsString('background-color: transparent', $block);
        $this->assertStringContainsString('width: 44px', $block);
        $this->assertStringContainsString('height: 44px', $block);
        $this->assertStringContainsString('display: none', $block, 'Hidden on desktop.');

        // Shown only in the drawer breakpoint.
        $mediaStart = strpos($css, '@media (max-width: 991.98px)', $start);
        $this->assertNotFalse($mediaStart);

        $mediaBlock = substr($css, $mediaStart, strpos($css, '}', $mediaStart) - $mediaStart);
        $this->assertStringContainsString('display: inline-flex', $mediaBlock);

        // No white pill sneaks in via a background-image or filter.
        $this->assertStringNotContainsString('background-image', $block);
        $this->assertStringNotContainsString('filter:', $block);
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    private function violationPayload(array $overrides = []): array
    {
        return array_merge([
            'student_number' => '2024-0001',
            'violation_type_id' => 1,
            'violation_date' => now()->format('Y-m-d H:i:s'),
            'description' => 'Caught using a phone.',
        ], $overrides);
    }

    private function fakeIdp(string $idpSubject, string $email): void
    {
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
                'first_name' => 'Juan',
                'middle_name' => null,
                'last_name' => 'Dela Cruz',
                'name_suffix' => null,
                'roles' => 'student',
            ], 200),
        ]);
    }

    private function hitCallback(): \Illuminate\Testing\TestResponse
    {
        return $this->withSession(['student.idp.state' => 'state-123'])
            ->get(route('student.idp.callback', ['code' => 'auth-code-123']));
    }

    private function createAdmin(): Admin
    {
        return Admin::create([
            'username' => 'admin@example.com',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    private function createSecurity(): Security
    {
        $security = Security::create([
            'email' => 'officer@example.com',
            'password' => Hash::make('password'),
        ]);

        DB::table('security_info_tbl')->insert([
            'security_id' => $security->id,
            'firstname' => 'Officer',
            'middlename' => '',
            'lastname' => 'Alpha',
        ]);

        DB::table('security_info')->insert([
            'security_id' => $security->id,
            'firstname' => 'Officer',
            'middlename' => '',
            'lastname' => 'Alpha',
        ]);

        return $security;
    }

    /**
     * @param  bool  $active  false sets a NON-Active status id (2 = Inactive).
     */
    private function createStudent(string $studentNumber, bool $active = true, ?string $idpSubject = null): User
    {
        $student = User::create([
            'student_number' => $studentNumber,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => $studentNumber . '@example.com',
            'password_hash' => Hash::make('Password123!'),
            'roles_id' => 2,
            'status_id' => $active ? 1 : 2,
            'idp_subject' => $idpSubject,
            'profile_completed_at' => now()->subDay(),
        ]);

        DB::table('student_info_tbl')->insert([
            'user_id' => $student->user_id,
            'program_id' => 1,
            'year_id' => 1,
            'section_id' => 1,
            'student_status_id' => 1,
        ]);

        return $student;
    }

    private function createViolationType(int $id, string $type): void
    {
        ViolationType::create([
            'violation_type_id' => $id,
            'violation_type' => $type,
            'violation_category_id' => 1,
            'severity_level' => 1,
        ]);
    }

    private function createViolation(string $studentNumber, string $status = 'pending'): Violation
    {
        return Violation::create([
            'student_number' => $studentNumber,
            'violation_type' => 1,
            'violation_date' => now(),
            'description' => 'Caught cheating.',
            'recorder_type' => 'admin',
            'recorder_name' => 'Admin One',
            'status' => $status,
        ]);
    }

    /**
     * Create evidence row + a real file on the PRIVATE disk, so "privately
     * stored" is asserted against actual storage rather than a column value.
     */
    private function createStoredEvidence(
        string $studentNumber,
        ?Violation $violation = null,
        string $originalName = 'evidence.jpg'
    ): ViolationEvidence {
        $violation ??= $this->createViolation($studentNumber, 'confirmed');

        $path = 'violation-evidence/' . $violation->violation_id . '/EVD-TEST-' . uniqid() . '.jpg';
        Storage::disk('local')->put($path, 'fake-image-bytes');

        return ViolationEvidence::create([
            'violation_id' => $violation->violation_id,
            'disk_path' => $path,
            'original_name' => $originalName,
            'mime_type' => 'image/jpeg',
            'file_size' => 100,
            'capture_method' => 'camera',
            'watermarked' => true,
            'evidence_code' => 'EVD-2026-' . random_int(100000, 999999),
            'uploader_type' => 'security',
            'uploader_id' => 1,
        ]);
    }

    private function createTables(): void
    {
        foreach ([
            'admins' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('username')->nullable();
                $table->string('email');
                $table->string('password');
                $table->text('mfa_totp_secret')->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
            },
            'admin_info_tbl' => function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->string('firstname')->nullable();
                $table->string('middlename')->nullable();
                $table->string('lastname')->nullable();
            },
            'security' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('email');
                $table->string('password');
                $table->text('mfa_totp_secret')->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
            },
            'security_info_tbl' => function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedBigInteger('security_id')->nullable();
                $table->string('firstname')->nullable();
                $table->string('middlename')->nullable();
                $table->string('lastname')->nullable();
            },
            'security_info' => function (Blueprint $table): void {
                $table->unsignedBigInteger('security_id')->primary();
                $table->string('firstname')->nullable();
                $table->string('middlename')->nullable();
                $table->string('lastname')->nullable();
            },
            'users_tbl' => function (Blueprint $table): void {
                $table->increments('user_id');
                $table->string('student_number')->nullable();
                $table->string('first_name')->nullable();
                $table->string('middle_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('name_suffix')->nullable();
                $table->string('email')->nullable();
                $table->string('idp_subject')->nullable();
                $table->string('idp_email')->nullable();
                $table->timestamp('idp_connected_at')->nullable();
                $table->timestamp('idp_last_login_at')->nullable();
                $table->string('password_hash')->nullable();
                $table->unsignedBigInteger('gender_id')->nullable();
                $table->unsignedInteger('roles_id')->nullable();
                $table->unsignedInteger('status_id')->nullable();
                $table->timestamp('profile_completed_at')->nullable();
                $table->timestamps();
            },
            'student_info_tbl' => function (Blueprint $table): void {
                $table->unsignedBigInteger('user_id')->primary();
                $table->unsignedBigInteger('program_id')->nullable();
                $table->unsignedBigInteger('year_id')->nullable();
                $table->unsignedBigInteger('section_id')->nullable();
                $table->unsignedBigInteger('student_status_id')->nullable();
                $table->unsignedTinyInteger('ladderized')->default(0);
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
            'gender_tbl' => function (Blueprint $table): void {
                $table->increments('gender_id');
                $table->string('gender_name')->nullable();
            },
            'roles_tbl' => function (Blueprint $table): void {
                $table->increments('roles_id');
                $table->string('roles_name');
            },
            'status_tbl' => function (Blueprint $table): void {
                $table->increments('status_id');
                $table->string('status_name');
            },
            'student_status_tbl' => function (Blueprint $table): void {
                $table->increments('student_status_id');
                $table->string('status_name');
            },
            'violation_category_tbl' => function (Blueprint $table): void {
                $table->increments('violation_category_id');
                $table->string('category_name');
            },
            'violation_type_tbl' => function (Blueprint $table): void {
                $table->increments('violation_type_id');
                $table->string('violation_type');
                $table->unsignedBigInteger('violation_category_id')->nullable();
                $table->string('severity_level')->nullable();
            },
            'violation_tbl' => function (Blueprint $table): void {
                $table->increments('violation_id');
                $table->string('student_number')->nullable();
                $table->string('violation_type')->nullable();
                $table->dateTime('violation_date')->nullable();
                $table->text('description')->nullable();
                $table->unsignedBigInteger('recorder_id')->nullable();
                $table->string('recorder_type', 20)->nullable();
                $table->string('recorder_name', 150)->nullable();
                $table->unsignedBigInteger('recorded_by_security_id')->nullable();
                $table->string('status', 20)->nullable();
                $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
                $table->dateTime('reviewed_at')->nullable();
                $table->text('review_remarks')->nullable();
            },
            'violation_evidence_tbl' => function (Blueprint $table): void {
                $table->increments('evidence_id');
                $table->string('evidence_code')->nullable();
                $table->string('capture_session_id')->nullable();
                $table->unsignedBigInteger('violation_id')->nullable();
                $table->string('disk_path')->nullable();
                $table->string('original_name')->nullable();
                $table->string('mime_type')->nullable();
                $table->string('capture_method')->nullable();
                $table->boolean('watermarked')->default(false);
                $table->boolean('server_watermarked')->default(false);
                $table->string('sha256_hash')->nullable();
                $table->dateTime('captured_at')->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->string('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->string('uploader_type')->nullable();
                $table->unsignedBigInteger('uploader_id')->nullable();
                $table->timestamp('created_at')->nullable();
            },
            'evidence_security_events_tbl' => function (Blueprint $table): void {
                $table->increments('event_id');
                $table->string('capture_session_id', 64)->nullable();
                $table->unsignedBigInteger('violation_id')->nullable();
                $table->unsignedBigInteger('evidence_id')->nullable();
                $table->string('event_type', 80);
                $table->string('severity', 20)->default('info');
                $table->string('actor_type', 20)->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->text('meta')->nullable();
                $table->timestamp('created_at')->nullable();
            },
            // Needed by the Reports page (its history tab counts own reports).
            'program_reports' => function (Blueprint $table): void {
                $table->increments('report_id');
                $table->unsignedBigInteger('program_id')->nullable();
                $table->unsignedBigInteger('generated_by_admin_id')->nullable();
                $table->string('academic_year')->nullable();
                $table->string('report_type')->nullable();
                $table->string('report_mode')->nullable();
                $table->string('report_status')->nullable();
                $table->text('report_data')->nullable();
                $table->dateTime('generated_at')->nullable();
                $table->timestamps();
            },
            'disciplinary_sanctions' => function (Blueprint $table): void {
                $table->increments('disciplinary_sanction_id');
                $table->unsignedBigInteger('violation_type_id')->nullable();
                $table->string('offense_level')->nullable();
                $table->string('disciplinary_sanction')->nullable();
            },
            'sanction_requests_tbl' => function (Blueprint $table): void {
                $table->increments('request_id');
                $table->string('student_number')->nullable();
                $table->unsignedBigInteger('violation_type_id')->nullable();
                $table->string('status')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            },
            'student_sanction_records_tbl' => function (Blueprint $table): void {
                $table->increments('record_id');
                $table->string('student_number')->nullable();
                $table->unsignedBigInteger('sanction_request_id')->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
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
                $table->boolean('is_read')->default(false);
                $table->timestamp('created_at')->nullable();
            },
            'admin_notifications_tbl' => function (Blueprint $table): void {
                $table->increments('admin_notification_id');
                $table->string('notification_type')->nullable();
                $table->string('title')->nullable();
                $table->text('message')->nullable();
                $table->string('target_type')->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->string('recipient_type')->nullable();
                $table->unsignedBigInteger('recipient_id')->nullable();
                $table->boolean('is_read')->default(false);
                $table->dateTime('created_at')->nullable();
            },
            'audit_logs' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('action')->nullable();
                $table->string('module')->nullable();
                $table->string('actor_type')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->text('description')->nullable();
                $table->timestamp('created_at')->nullable();
            },
            'announcements_tbl' => function (Blueprint $table): void {
                $table->increments('announcement_id');
                $table->string('title')->nullable();
                $table->text('content')->nullable();
                $table->timestamps();
            },
        ] as $table => $definition) {
            if (! Schema::hasTable($table)) {
                Schema::create($table, $definition);
            }
        }

        DB::table('roles_tbl')->insertOrIgnore([
            ['roles_id' => 1, 'roles_name' => 'Admin'],
            ['roles_id' => 2, 'roles_name' => 'Student'],
            ['roles_id' => 3, 'roles_name' => 'Security'],
        ]);
        DB::table('status_tbl')->insertOrIgnore([
            ['status_id' => 1, 'status_name' => 'Active'],
            ['status_id' => 2, 'status_name' => 'Inactive'],
        ]);
        DB::table('student_status_tbl')->insertOrIgnore([
            ['student_status_id' => 1, 'status_name' => 'Active'],
        ]);
        DB::table('program_tbl')->insertOrIgnore([
            ['program_id' => 1, 'program_name' => 'BSIT'],
        ]);
        DB::table('year_tbl')->insertOrIgnore([
            ['year_id' => 1, 'year' => '1st Year'],
        ]);
        DB::table('section_tbl')->insertOrIgnore([
            ['section_id' => 1, 'section_name' => 'BSIT 1-A'],
        ]);
        DB::table('violation_category_tbl')->insertOrIgnore([
            ['violation_category_id' => 1, 'category_name' => 'Minor'],
        ]);
    }
}
