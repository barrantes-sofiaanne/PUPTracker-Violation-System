<?php

namespace Tests\Feature;

use App\Models\DisciplinarySanction;
use App\Models\Security;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationCategory;
use App\Models\ViolationType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Security Module changes:
 *   1.1 Minor/Major dashboard cards removed (data/classification untouched).
 *   1.2 Violation and Sanction Guide added, sharing the Student implementation.
 *   1.3 Dashboard chart = violations recorded by the CURRENT security user,
 *       grouped by week.
 *   2.1 Generate Report produces a real PDF (content/ordering preserved).
 *   2.2 Major/Minor cards removed from the Security Student Record UI.
 *   2.3 Student Record shows the violation's CURRENT authoritative status
 *       (confirmed vs rejected vs pending) instead of assuming confirmed.
 */
class SecurityModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
    }

    // ==================================================================
    // 1.1 Dashboard cards
    // ==================================================================

    public function test_security_dashboard_no_longer_renders_minor_or_major_cards(): void
    {
        $officer = $this->createSecurity('officer@example.com');

        $html = $this->actingAs($officer, 'security')
            ->get(route('security.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Major Violations', $html);
        $this->assertStringNotContainsString('Minor Violations', $html);

        // The remaining dashboard stats are untouched.
        $this->assertStringContainsString('Total Violations', $html);
        $this->assertStringContainsString('Active Students', $html);
    }

    public function test_removing_the_cards_does_not_remove_severity_classification(): void
    {
        $officer = $this->createSecurity('officer@example.com');
        $this->createViolationType(1, 'Major Offence', severityLevel: 1);
        $this->createViolation('2024-0001', 1, 'Security: officer@example.com');

        $this->actingAs($officer, 'security')
            ->get(route('security.dashboard'))
            ->assertOk();

        // The classification still exists in the database for other features.
        $this->assertDatabaseHas('violation_type_tbl', [
            'violation_type_id' => 1,
            'severity_level' => 1,
        ]);
    }

    // ==================================================================
    // 1.2 Guide
    // ==================================================================

    public function test_security_dashboard_shows_the_violation_and_sanction_guide(): void
    {
        $officer = $this->createSecurity('officer@example.com');
        $this->createCategory(1, 'Major Offenses');
        $this->createViolationType(1, 'Cheating', categoryId: 1);
        DisciplinarySanction::create([
            'violation_type_id' => 1,
            'offense_level' => '1st Offense',
            'disciplinary_sanction' => 'Community service 40 hours',
        ]);

        $html = $this->actingAs($officer, 'security')
            ->get(route('security.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Violation and Sanction Guide', $html);

        // The SAME guide content the Student dashboard shows.
        $this->assertStringContainsString('Major Offenses', $html);
        $this->assertStringContainsString('Cheating', $html);
        $this->assertStringContainsString('Community service 40 hours', $html);

        // Same shared implementation (accordion + portal card + table).
        $this->assertStringContainsString('accordion', $html);
        $this->assertStringContainsString('portal-card', $html);
        $this->assertStringContainsString('portal-table', $html);
    }

    public function test_student_and_security_guides_render_the_same_content(): void
    {
        $officer = $this->createSecurity('officer@example.com');
        $student = $this->createStudent('2024-0001');

        $this->createCategory(1, 'Major Offenses');
        $this->createViolationType(1, 'Cheating', categoryId: 1);
        DisciplinarySanction::create([
            'violation_type_id' => 1,
            'offense_level' => '1st Offense',
            'disciplinary_sanction' => 'Community service 40 hours',
        ]);

        $securityHtml = $this->actingAs($officer, 'security')
            ->get(route('security.dashboard'))->getContent();

        $studentHtml = $this->actingAs($student, 'student')
            ->get(route('student.dashboard'))->getContent();

        // Both draw the same guide rows from the same data source.
        foreach (['Major Offenses', 'Cheating', 'Community service 40 hours', '1st Offense'] as $expected) {
            $this->assertStringContainsString($expected, $securityHtml, "Security guide missing: {$expected}");
            $this->assertStringContainsString($expected, $studentHtml, "Student guide missing: {$expected}");
        }

        // The Student dashboard keeps its own title (unchanged). Blade escapes
        // the ampersand, so assert the rendered form.
        $this->assertStringContainsString('Violation Code &amp; Sanctions Guide', $studentHtml);
    }

    // ==================================================================
    // 1.3 Chart scoping
    // ==================================================================

    public function test_chart_counts_only_the_current_security_users_violations_by_week(): void
    {
        $officerA = $this->createSecurity('officer-a@example.com');
        $this->createSecurity('officer-b@example.com');

        // Officer A records 2 this week; officer B records 3; an admin records 4.
        for ($i = 0; $i < 2; $i++) {
            $this->createViolation('2024-0001', 1, 'Security: officer-a@example.com', 'security', Violation::STATUS_CONFIRMED);
        }
        for ($i = 0; $i < 3; $i++) {
            $this->createViolation('2024-0002', 1, 'Security: officer-b@example.com', 'security', Violation::STATUS_CONFIRMED);
        }
        for ($i = 0; $i < 4; $i++) {
            $this->createViolation('2024-0003', 1, 'Admin One', 'admin', Violation::STATUS_CONFIRMED);
        }

        $this->actingAs($officerA, 'security');

        $payload = $this->getJson(route('security.dashboard.chart-data').'?period=week')
            ->assertOk()
            ->json();

        // Week series is per-day (Mon..Sun of the current week).
        $this->assertCount(count($payload['labels']), $payload['values']);
        $this->assertSame(7, count($payload['values']), 'The weekly chart should have one bucket per day.');

        // Only officer A's 2 violations count.
        $this->assertSame(2, $payload['total']);
    }

    public function test_chart_renders_zeroes_when_the_officer_recorded_nothing(): void
    {
        $officer = $this->createSecurity('officer@example.com');
        // Someone else recorded violations.
        $this->createViolation('2024-0001', 1, 'Security: other@example.com', 'security', Violation::STATUS_CONFIRMED);

        $payload = $this->actingAs($officer, 'security')
            ->getJson(route('security.dashboard.chart-data').'?period=week')
            ->assertOk()
            ->json();

        $this->assertSame(0, $payload['total']);
        $this->assertSame([0, 0, 0, 0, 0, 0, 0], $payload['values']);
    }

    // ==================================================================
    // 2.1 Report PDF
    // ==================================================================

    public function test_generate_report_returns_a_pdf(): void
    {
        $officer = $this->createSecurity('officer@example.com');
        $this->createViolation('2024-0001', 1, 'Security: officer@example.com');

        $response = $this->actingAs($officer, 'security')
            ->get(route('security.violations.report', ['period' => 'last7', 'format' => 'pdf']));

        $response->assertOk();

        $this->assertStringContainsString(
            'application/pdf',
            (string) $response->headers->get('content-type')
        );

        $this->assertStringContainsString(
            'attachment',
            (string) $response->headers->get('content-disposition')
        );

        // Meaningful filename.
        $this->assertMatchesRegularExpression(
            '/violation-report-\d{8}_\d{6}\.pdf/',
            (string) $response->headers->get('content-disposition')
        );

        // A real PDF: the %PDF- magic header, and a PDF EOF marker.
        $body = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $body);
        $this->assertStringContainsString('%%EOF', $body);
        $this->assertGreaterThan(1000, strlen($body), 'The PDF should contain the rendered report.');
    }

    public function test_report_still_renders_html_when_pdf_is_not_requested(): void
    {
        $officer = $this->createSecurity('officer@example.com');
        $this->createViolation('2024-0001', 1, 'Security: officer@example.com');

        $response = $this->actingAs($officer, 'security')
            ->get(route('security.violations.report', ['period' => 'last7']));

        $response->assertOk();
        $this->assertStringContainsString('text/html', (string) $response->headers->get('content-type'));
    }

    public function test_report_pdf_preserves_the_report_content(): void
    {
        $officer = $this->createSecurity('officer@example.com');
        // The report's per-student block resolves the student record, so it
        // must exist for the content to be present.
        $this->createStudent('2024-0001');
        $this->createCategory(1, 'Major Offenses');
        $this->createViolationType(1, 'Cheating', categoryId: 1);
        $this->createViolation(
            '2024-0001',
            1,
            'Security: officer@example.com',
            'security',
            Violation::STATUS_CONFIRMED,
            'Copied from a classmate during the final exam.'
        );

        // The HTML report is the source of truth for the content.
        $html = $this->actingAs($officer, 'security')
            ->get(route('security.violations.report', ['period' => 'last7']))
            ->getContent();

        foreach (['PUPT Student Violation Report', 'Cheating', '2024-0001'] as $expected) {
            $this->assertStringContainsString($expected, $html, "HTML report missing: {$expected}");
        }

        // The PDF is generated from that exact view, so the content matches.
        $pdf = $this->actingAs($officer, 'security')
            ->get(route('security.violations.report', ['period' => 'last7', 'format' => 'pdf']))
            ->assertOk()
            ->getContent();

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);

        // The rendered report body made it into the document: mPDF emits real
        // page objects, and the output is far larger than an empty PDF. (The
        // text stream is compressed, so the literal strings are asserted on the
        // shared HTML rendering above instead.)
        $this->assertStringContainsString('/Type /Page', $pdf, 'The PDF must contain a rendered page.');
        $this->assertGreaterThan(2000, strlen($pdf), 'The PDF should contain the rendered report body.');
    }

    // ==================================================================
    // 2.2 + 2.3 Student Record
    // ==================================================================

    public function test_student_record_no_longer_renders_major_or_minor_cards(): void
    {
        $officer = $this->createSecurity('officer@example.com');
        $this->createCategory(1, 'Major Offenses');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating', severityLevel: 1);
        $this->createViolation('2024-0001', 1, 'Security: officer@example.com', 'security', Violation::STATUS_CONFIRMED);

        $html = $this->actingAs($officer, 'security')
            ->get(route('security.violations.show', '2024-0001'))
            ->assertOk()
            ->getContent();

        // The two summary cards are gone. (A violation's CATEGORY may read
        // "Major Offenses" — that is record data, not the removed stat card.)
        $this->assertStringNotContainsString('>Major<', $html);
        $this->assertStringNotContainsString('>Minor<', $html);
        $this->assertStringNotContainsString('stat-mini-label">Major<', $html);
        $this->assertStringNotContainsString('stat-mini-label">Minor<', $html);

        // ...but the student's records still render.
        $this->assertStringContainsString('Total Violations', $html);
        $this->assertStringContainsString('Cheating', $html);
    }

    /**
     * NEW required behaviour: the record reflects the administrator's decision.
     * A REJECTED (denied) violation is now shown as Rejected rather than being
     * silently hidden (this supersedes the previous exclusion).
     */
    public function test_student_record_shows_rejected_status_for_a_denied_violation(): void
    {
        $officer = $this->createSecurity('officer@example.com');
        $this->createCategory(1, 'Major Offenses');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createViolation(
            '2024-0001', 1, 'Security: officer@example.com', 'security',
            Violation::STATUS_DENIED, 'Rejected submission.'
        );

        $html = $this->actingAs($officer, 'security')
            ->get(route('security.violations.show', '2024-0001'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Rejected submission.', $html);
        $this->assertStringContainsString('>Rejected<', $html);
        $this->assertStringNotContainsString('>Confirmed<', $html);
    }

    public function test_student_record_shows_confirmed_and_rejected_together_with_correct_labels(): void
    {
        $officer = $this->createSecurity('officer@example.com');
        $this->createCategory(1, 'Major Offenses');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createViolationType(2, 'Fighting');

        $this->createViolation(
            '2024-0001', 1, 'Security: officer@example.com', 'security',
            Violation::STATUS_CONFIRMED, 'Approved submission.'
        );
        $this->createViolation(
            '2024-0001', 2, 'Security: officer@example.com', 'security',
            Violation::STATUS_DENIED, 'Rejected submission.'
        );

        $html = $this->actingAs($officer, 'security')
            ->get(route('security.violations.show', '2024-0001'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Approved submission.', $html);
        $this->assertStringContainsString('Rejected submission.', $html);
        $this->assertStringContainsString('>Confirmed<', $html);
        $this->assertStringContainsString('>Rejected<', $html);
    }

    /**
     * Pending/unreviewed violations must remain excluded from the record: only
     * the administrator's decision (Confirmed / Rejected) reflects there.
     */
    public function test_student_record_excludes_pending_violations(): void
    {
        $officer = $this->createSecurity('officer@example.com');
        $this->createCategory(1, 'Major Offenses');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createViolation(
            '2024-0001', 1, 'Security: officer@example.com', 'security',
            Violation::STATUS_PENDING, 'Awaiting review.'
        );

        $html = $this->actingAs($officer, 'security')
            ->get(route('security.violations.show', '2024-0001'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Awaiting review.', $html);
        $this->assertStringNotContainsString('>Confirmed<', $html);
        $this->assertStringNotContainsString('>Rejected<', $html);
    }

    /**
     * The rejected submission remains visible where the officer needs it — the
     * Submitted Violations tracker, labelled Rejected.
     */
    public function test_rejected_violation_is_tracked_in_submitted_violations(): void
    {
        $officer = $this->createSecurity('officer@example.com');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createViolation(
            '2024-0001', 1, 'Security: officer@example.com', 'security',
            Violation::STATUS_DENIED, 'Rejected submission.'
        );

        $this->actingAs($officer, 'security')
            ->get(route('security.violations.submitted', ['status' => 'rejected']))
            ->assertOk()
            ->assertSee('Rejected submission.')
            ->assertSee('Rejected');
    }

    public function test_student_record_still_excludes_other_officers_violations(): void
    {
        $officerA = $this->createSecurity('officer-a@example.com');
        $this->createSecurity('officer-b@example.com');
        $this->createCategory(1, 'Major Offenses');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Mine');
        $this->createViolationType(2, 'Theirs');

        $this->createViolation('2024-0001', 1, 'Security: officer-a@example.com', 'security', Violation::STATUS_CONFIRMED);
        $this->createViolation('2024-0001', 2, 'Security: officer-b@example.com', 'security', Violation::STATUS_CONFIRMED);

        $html = $this->actingAs($officerA, 'security')
            ->get(route('security.violations.show', '2024-0001'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Mine', $html);
        $this->assertStringNotContainsString(
            'Theirs',
            $html,
            'Another officer\'s recording must not appear.'
        );
    }

    // ==================================================================
    // 2. Violations tab structure
    // ==================================================================

    public function test_violations_page_has_exactly_two_tabs_in_order(): void
    {
        $officer = $this->createSecurity('officer@example.com');

        $html = $this->actingAs($officer, 'security')
            ->get(route('security.violations.students'))
            ->assertOk()
            ->getContent();

        // Student Record first, Submitted Violations second.
        $studentPos = strpos($html, 'id="studentRecordTab"');
        $submittedPos = strpos($html, 'id="submittedViolationsTab"');

        $this->assertNotFalse($studentPos, 'The Student Record tab must exist.');
        $this->assertNotFalse($submittedPos, 'The Submitted Violations tab must exist.');
        $this->assertTrue($studentPos < $submittedPos, 'Student Record must be the first tab.');

        // Student Record is the default (active) tab on its own page.
        $this->assertMatchesRegularExpression(
            '/id="studentRecordTab"\s+role="tab"\s+aria-controls="studentRecord"\s+aria-selected="true"/',
            $html
        );

        // The same two tabs render on the Submitted Violations page.
        $submittedHtml = $this->actingAs($officer, 'security')
            ->get(route('security.violations.submitted'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="studentRecordTab"', $submittedHtml);
        $this->assertMatchesRegularExpression(
            '/id="submittedViolationsTab"\s+role="tab"\s+aria-controls="submittedViolations"\s+aria-selected="true"/',
            $submittedHtml
        );
    }

    // ==================================================================
    // 3 + 4. Submitted Violations
    // ==================================================================

    public function test_submitted_violations_lists_the_officers_own_records_with_statuses(): void
    {
        $officer = $this->createSecurity('officer@example.com');
        $this->createCategory(1, 'Major Offenses');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');

        $this->createViolation('2024-0001', 1, 'Security: officer@example.com', 'security', Violation::STATUS_PENDING, 'Pending one.');
        $this->createViolation('2024-0001', 1, 'Security: officer@example.com', 'security', Violation::STATUS_CONFIRMED, 'Confirmed one.');
        $this->createViolation('2024-0001', 1, 'Security: officer@example.com', 'security', Violation::STATUS_DENIED, 'Rejected one.');

        $html = $this->actingAs($officer, 'security')
            ->get(route('security.violations.submitted'))
            ->assertOk()
            ->getContent();

        // All three of the officer's own submissions, each with its status.
        $this->assertStringContainsString('Pending one.', $html);
        $this->assertStringContainsString('Confirmed one.', $html);
        $this->assertStringContainsString('Rejected one.', $html);

        $this->assertStringContainsString('Pending Review', $html);
        $this->assertStringContainsString('Confirmed', $html);
        $this->assertStringContainsString('Rejected', $html);

        // The student is identifiable.
        $this->assertStringContainsString('2024-0001', $html);
    }

    public function test_submitted_violations_excludes_other_officers_submissions(): void
    {
        $officerA = $this->createSecurity('officer-a@example.com');
        $this->createSecurity('officer-b@example.com');
        $this->createCategory(1, 'Major Offenses');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');

        $this->createViolation('2024-0001', 1, 'Security: officer-a@example.com', 'security', Violation::STATUS_PENDING, 'Mine.');
        $this->createViolation('2024-0001', 1, 'Security: officer-b@example.com', 'security', Violation::STATUS_PENDING, 'Someone elses.');

        $html = $this->actingAs($officerA, 'security')
            ->get(route('security.violations.submitted'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Mine.', $html);
        $this->assertStringNotContainsString(
            'Someone elses.',
            $html,
            'Another officer\'s submission must never appear.'
        );
    }

    public function test_submitted_violations_status_filter_maps_rejected_to_denied(): void
    {
        $officer = $this->createSecurity('officer@example.com');
        $this->createCategory(1, 'Major Offenses');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');

        $this->createViolation('2024-0001', 1, 'Security: officer@example.com', 'security', Violation::STATUS_CONFIRMED, 'Approved one.');
        $this->createViolation('2024-0001', 1, 'Security: officer@example.com', 'security', Violation::STATUS_DENIED, 'Rejected one.');

        $rejected = $this->actingAs($officer, 'security')
            ->get(route('security.violations.submitted', ['status' => 'rejected']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Rejected one.', $rejected);
        $this->assertStringNotContainsString('Approved one.', $rejected);

        $confirmed = $this->actingAs($officer, 'security')
            ->get(route('security.violations.submitted', ['status' => 'confirmed']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Approved one.', $confirmed);
        $this->assertStringNotContainsString('Rejected one.', $confirmed);
    }

    // ==================================================================
    // 5 + 6. Notification deep link + highlight
    // ==================================================================

    public function test_confirmed_violation_notification_deep_links_to_the_student_record(): void
    {
        $officer = $this->createSecurity('officer@example.com');
        $this->createCategory(1, 'Major Offenses');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $violation = $this->createViolation(
            '2024-0001', 1, 'Security: officer@example.com', 'security',
            Violation::STATUS_CONFIRMED, 'Confirmed one.'
        );

        $url = app(\App\Services\SecurityNotificationService::class)->openUrl(
            new \App\Models\Notification([
                'target_type' => 'violation',
                'target_id' => $violation->violation_id,
                'recipient_type' => 'security',
            ])
        );

        $this->assertNotNull($url);
        // Lands on the exact student's record with the record identified.
        $this->assertStringContainsString('security/violations/student/2024-0001', $url);
        $this->assertStringContainsString('highlight_violation=' . $violation->violation_id, $url);

        // And the page actually highlights that record.
        $html = $this->actingAs($officer, 'security')
            ->get($url)
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('violation-item-highlight', $html);
        $this->assertStringContainsString('data-violation-id="' . $violation->violation_id . '"', $html);
    }

    public function test_unreviewed_violation_notification_deep_links_to_submitted_violations(): void
    {
        $officer = $this->createSecurity('officer@example.com');
        $this->createCategory(1, 'Major Offenses');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $violation = $this->createViolation(
            '2024-0001', 1, 'Security: officer@example.com', 'security',
            Violation::STATUS_PENDING, 'Pending one.'
        );

        $url = app(\App\Services\SecurityNotificationService::class)->openUrl(
            new \App\Models\Notification([
                'target_type' => 'violation',
                'target_id' => $violation->violation_id,
                'recipient_type' => 'security',
            ])
        );

        $this->assertNotNull($url);
        $this->assertStringContainsString('violations/submitted', $url);
        $this->assertStringContainsString('highlight_violation=' . $violation->violation_id, $url);

        $html = $this->actingAs($officer, 'security')
            ->get($url)
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('resubmit-highlight', $html);
        $this->assertStringContainsString('data-violation-id="' . $violation->violation_id . '"', $html);
    }

    public function test_highlight_fades_after_user_interaction(): void
    {
        // The Student Module's highlight fades/removes after interaction. The
        // Security views reuse the same approach: a CSS animation plus a click
        // handler that clears the highlight class.
        $show = file_get_contents(resource_path('views/security/violations/show.blade.php'));

        $this->assertStringContainsString('violationHighlightPulse', $show);
        $this->assertStringContainsString('classList.remove', $show);

        $submitted = file_get_contents(resource_path('views/security/violations/submitted.blade.php'));

        $this->assertStringContainsString('submittedRowFlash', $submitted);
        $this->assertStringContainsString('classList.remove', $submitted);
    }

    /**
     * REGRESSION — "Generate Report just keeps loading".
     *
     * mPDF resolves every remote URL while writing the document. The template
     * used to link the Bootstrap CDN and load the logo over HTTP via asset(),
     * so the request hung (spinner forever) whenever the server could not reach
     * those hosts. The rendered template must therefore be fully
     * self-contained: no remote stylesheets, scripts, or images.
     */
    public function test_report_template_is_self_contained_for_pdf_rendering(): void
    {
        $officer = $this->createSecurity('officer@example.com');
        $this->createCategory(1, 'Major Offenses');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createViolation(
            '2024-0001', 1, 'Security: officer@example.com', 'security',
            Violation::STATUS_CONFIRMED, 'Reported violation.'
        );

        $html = $this->actingAs($officer, 'security')
            ->get(route('security.violations.report', ['period' => 'last7']))
            ->assertOk()
            ->getContent();

        // No remote stylesheet/script/image may remain in the document body.
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/<link[^>]+href="https?:\/\//',
            $html,
            'A remote stylesheet would make mPDF hang while fetching it.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<img[^>]+src="https?:\/\//',
            $html,
            'A remote image would make mPDF hang while fetching it.'
        );

        // The logo is embedded from disk instead.
        $this->assertStringContainsString('data:image/png;base64,', $html);

        // The report content is still intact.
        $this->assertStringContainsString('PUPT Student Violation Report', $html);
        $this->assertStringContainsString('Cheating', $html);
    }

    public function test_report_pdf_generates_without_network_access(): void
    {
        $officer = $this->createSecurity('officer@example.com');
        $this->createCategory(1, 'Major Offenses');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createViolation(
            '2024-0001', 1, 'Security: officer@example.com', 'security',
            Violation::STATUS_CONFIRMED, 'Reported violation.'
        );

        $started = microtime(true);

        $response = $this->actingAs($officer, 'security')
            ->get(route('security.violations.report', ['period' => 'last7', 'format' => 'pdf']));

        $elapsed = microtime(true) - $started;

        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());

        // A self-contained render is fast; a network fetch would blow past this.
        $this->assertLessThan(
            10,
            $elapsed,
            'PDF generation took too long — the template likely still resolves a remote URL.'
        );
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    private function createSecurity(string $email): Security
    {
        return Security::create([
            'email' => $email,
            'password' => Hash::make('Password123!'),
            'mfa_totp_enabled' => 0,
        ]);
    }

    private function createStudent(string $studentNumber): User
    {
        $user = User::create([
            'student_number' => $studentNumber,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => $studentNumber . '@example.com',
            'password_hash' => Hash::make('Password123!'),
            'roles_id' => 2,
            'status_id' => 1,
        ]);

        if (Schema::hasTable('student_info_tbl')) {
            DB::table('student_info_tbl')->insert(['user_id' => $user->user_id]);
        }

        return $user;
    }

    private function createCategory(int $id, string $name): void
    {
        ViolationCategory::create([
            'violation_category_id' => $id,
            'category_name' => $name,
        ]);
    }

    private function createViolationType(
        int $id,
        string $type,
        ?int $categoryId = null,
        int $severityLevel = 1,
    ): void {
        ViolationType::create([
            'violation_type_id' => $id,
            'violation_type' => $type,
            'violation_category_id' => $categoryId ?? 1,
            'severity_level' => $severityLevel,
        ]);
    }

    private function createViolation(
        string $studentNumber,
        int $typeId,
        string $recorderName,
        string $recorderType = 'security',
        string $status = Violation::STATUS_PENDING,
        string $description = 'Test violation.',
    ): Violation {
        return Violation::create([
            'student_number' => $studentNumber,
            'violation_type' => $typeId,
            'violation_date' => now(),
            'description' => $description,
            'recorder_type' => $recorderType,
            'recorder_name' => $recorderName,
            'status' => $status,
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
                $table->string('firstname')->nullable();
                $table->string('middlename')->nullable();
                $table->string('lastname')->nullable();
                $table->string('position')->nullable();
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
            // Required by the Security layout (sidebar/officer name).
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
                $table->string('password_hash')->nullable();
                $table->unsignedInteger('roles_id')->nullable();
                $table->unsignedInteger('status_id')->nullable();
                $table->unsignedBigInteger('gender_id')->nullable();
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
            'gender_tbl' => function (Blueprint $table): void {
                $table->increments('gender_id');
                $table->string('gender_name')->nullable();
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
                $table->unsignedBigInteger('violation_id')->nullable();
                $table->string('disk_path', 500)->nullable();
                $table->string('original_name', 255)->nullable();
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->char('sha256_hash', 64)->nullable();
                $table->timestamp('created_at')->nullable();
            },
            // Needed by the Student dashboard's Approaching Deadlines section,
            // which this test renders to compare the shared guide.
            'student_sanction_records_tbl' => function (Blueprint $table): void {
                $table->increments('record_id');
                $table->string('student_number')->nullable();
                $table->unsignedBigInteger('violation_id')->nullable();
                $table->unsignedBigInteger('assigned_sanction_id')->nullable();
                $table->unsignedBigInteger('assigned_by_admin_id')->nullable();
                $table->string('status')->nullable();
                $table->date('deadline_date')->nullable();
                $table->string('designated_office', 255)->nullable();
                $table->date('starting_date')->nullable();
                $table->unsignedInteger('required_hours')->nullable();
                $table->timestamp('date_assigned')->nullable();
                $table->timestamp('date_completed')->nullable();
                $table->string('proof_path', 500)->nullable();
                $table->string('proof_original_name', 255)->nullable();
                $table->timestamp('proof_submitted_at')->nullable();
                $table->text('proof_notes')->nullable();
                $table->unsignedBigInteger('completion_reviewed_by_admin_id')->nullable();
                $table->timestamp('completion_reviewed_at')->nullable();
                $table->text('completion_remarks')->nullable();
            },
            'sanction_requests_tbl' => function (Blueprint $table): void {
                $table->increments('request_id');
                $table->string('student_number');
                $table->unsignedInteger('violation_type_id');
                $table->dateTime('request_date')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('status')->nullable();
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
                $table->string('type')->nullable();
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
                $table->text('message')->nullable();
                $table->string('title')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('created_at')->nullable();
            },
            'announcements_tbl' => function (Blueprint $table): void {
                $table->increments('announcement_id');
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->string('title')->nullable();
                $table->longText('content')->nullable();
                $table->string('attachment_path')->nullable();
                $table->boolean('show_on_login')->default(false);
                $table->timestamp('login_modal_expires_at')->nullable();
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
