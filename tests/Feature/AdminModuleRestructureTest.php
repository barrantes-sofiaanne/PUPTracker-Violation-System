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
use Illuminate\Support\ViewErrorBag;
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

        // The retired mode is gone from the report form. Load the form partial
        // DIRECTLY rather than through a page, because the form's former host —
        // the Reports "AI Program Report" tab — is now the Graduation AI Report.
        // (The Report History tab still shows "Program Sanction Report" as a
        // filter option on purpose, so legacy report_mode='regular' rows stay
        // findable — that is why this is scoped to the form, not a whole page.)
        $form = view('admin.reports.tabs.ai-program-report', $this->programReportFormData())
            ->render();

        $this->assertStringNotContainsString('Program Sanction Report', $form);
        $this->assertStringNotContainsString('value="regular"', $form);

        // The graduating workflow remains, with report_mode still posted so the
        // existing controller contract/validation is unchanged.
        $this->assertStringContainsString('name="report_mode"', $form);
        $this->assertStringContainsString('value="graduating"', $form);
        $this->assertStringContainsString('Graduation Eligibility', $form);
        $this->assertStringContainsString('Preview Students', $form);
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
    // 5. Reports page — three tabs, existing processes relocated
    // ==================================================================

    public function test_reports_page_has_three_tabs_in_order_with_one_parent_header(): void
    {
        $admin = $this->createAdmin();

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.reports'))
            ->assertOk()
            ->getContent();

        // ONE parent-level header for the page.
        $this->assertStringContainsString('>Reports</h2>', $html);
        $this->assertStringContainsString('Manage and generate administrative reports.', $html);

        $violationPos = strpos($html, 'id="violation-reports-tab"');
        $aiPos = strpos($html, 'id="graduation-ai-tab"');
        $historyPos = strpos($html, 'id="history-tab"');

        $this->assertNotFalse($violationPos, 'Violation Reports tab must exist.');
        $this->assertNotFalse($aiPos, 'Graduation AI Report tab must exist.');
        $this->assertNotFalse($historyPos, 'Report History tab must exist.');

        // The retired "AI Program Report" tab label must be gone entirely.
        $this->assertStringNotContainsString('id="ai-program-tab"', $html);
        $this->assertStringNotContainsString('AI Program Report<', $html);

        $this->assertTrue(
            $violationPos < $aiPos && $aiPos < $historyPos,
            'Tabs must be ordered: Violation Reports, Graduation AI Report, Report History.'
        );

        // The default tab is the Violation Reports one.
        $this->assertSame(1, substr_count($html, 'tab-pane fade show active'));
    }

    public function test_reports_graduation_ai_tab_renders_the_graduation_ai_report(): void
    {
        $admin = $this->createAdmin();
        $this->createGraduatingStudent('2026-0601', 'Tab', 'Rendered');

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.reports', ['tab' => 'graduation-ai']))
            ->assertOk()
            ->getContent();

        // The Graduation AI Report's own content is present.
        $this->assertStringContainsString('id="aiReviewLoading"', $html);
        $this->assertStringContainsString('Graduating Students — Disciplinary Screening', $html);
        $this->assertStringContainsString('Students Reviewed', $html);
        $this->assertStringContainsString('Outstanding Sanctions', $html);

        // Its filter form and per-student review links point at the graduation
        // routes, which are now registered.
        $this->assertStringContainsString(route('admin.graduation-review.index'), $html);
        $this->assertStringContainsString(
            route('admin.graduation-review.show', '2026-0601'),
            $html
        );

        // The program-statistics FORM is no longer part of this tab.
        $this->assertStringNotContainsString('id="programReportForm"', $html);

        // Only the Graduation AI pane is active.
        $this->assertSame(1, substr_count($html, 'tab-pane fade show active'));
        $this->assertStringContainsString('id="graduation-ai"', $html);
    }

    /**
     * The program-statistics form itself is retained (only its host tab was
     * replaced), so rendering it directly must still produce the full markup.
     */
    public function test_retained_program_report_form_still_renders_completely(): void
    {
        $form = view('admin.reports.tabs.ai-program-report', $this->programReportFormData())
            ->render();

        $this->assertStringContainsString('id="programReportForm"', $form);
        $this->assertStringContainsString('name="program_id"', $form);
        $this->assertStringContainsString('name="report_type"', $form);
        $this->assertStringContainsString('id="previewGraduatingBtn"', $form);
        $this->assertStringContainsString('id="graduatingPreviewBody"', $form);
        $this->assertStringContainsString('id="excludedStudentsInputs"', $form);
        $this->assertStringContainsString(route('admin.program-report.generate'), $form);

        // Program Sanction Report remains removed from the form.
        $this->assertStringNotContainsString('Program Sanction Report', $form);
    }

    public function test_reports_history_tab_renders_the_existing_history_table(): void
    {
        $admin = $this->createAdmin();

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.reports', ['tab' => 'history']))
            ->assertOk()
            ->getContent();

        // The history filters (including the retained Report Mode filter) survive.
        $this->assertStringContainsString(route('admin.program-report.history'), $html);
        $this->assertStringContainsString('name="report_mode"', $html);
        $this->assertStringContainsString('Report Mode', $html);
        $this->assertStringContainsString('name="report_type"', $html);

        $this->assertSame(1, substr_count($html, 'tab-pane fade show active'));
        $this->assertStringContainsString('id="history"', $html);
    }

    public function test_unknown_tab_falls_back_to_the_default_tab(): void
    {
        $admin = $this->createAdmin();

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.reports', ['tab' => 'not-a-real-tab']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="violation-reports"', $html);
        $this->assertSame(1, substr_count($html, 'tab-pane fade show active'));
    }

    /**
     * The no-JavaScript filter submit re-renders the whole page, so the tabs the
     * user is NOT on must still be fully populated (not left as placeholders).
     */
    public function test_non_ajax_filter_submit_still_renders_all_three_tabs(): void
    {
        $admin = $this->createAdmin();
        $this->createStudent('2026-0701', 'Filtered', 'Student');
        $this->createViolation('2026-0701', now()->toDateString(), 1, Violation::STATUS_CONFIRMED);
        $this->createGraduatingStudent('2026-0702', 'Filter', 'Graduate');

        $html = $this->actingAs($admin, 'admin')
            ->post(route('admin.reports.filter'), [])
            ->assertOk()
            ->getContent();

        // The filtered Violation Reports pane...
        $this->assertStringContainsString('id="reportFilterForm"', $html);
        // ...and the other two tabs are fully rendered, not placeholders.
        $this->assertStringContainsString('id="aiReviewLoading"', $html);
        $this->assertStringContainsString(route('admin.program-report.history'), $html);
        // `bi-hourglass-split` is the deferred-content placeholder's icon and
        // appears nowhere else, so its absence proves no tab fell back to it.
        $this->assertStringNotContainsString('bi-hourglass-split', $html);
        $this->assertSame(1, substr_count($html, 'tab-pane fade show active'));
    }

    // ==================================================================
    // 6. Backward compatibility for the moved report pages
    // ==================================================================

    public function test_old_ai_program_report_url_redirects_to_the_graduation_ai_tab(): void
    {
        $admin = $this->createAdmin();

        // The process formerly hosted at the AI Program Report URL is now the
        // Graduation AI Report, so the legacy URL forwards there rather than
        // dead-ending on a superseded view.
        $this->actingAs($admin, 'admin')
            ->get(route('admin.program-report.index'))
            ->assertRedirect(route('admin.reports', ['tab' => 'graduation-ai']));
    }

    /**
     * The old `?tab=ai-program` value is kept as an alias so existing bookmarks
     * do not silently fall back to the default tab.
     */
    public function test_legacy_ai_program_tab_value_resolves_to_the_graduation_ai_tab(): void
    {
        $admin = $this->createAdmin();

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.reports', ['tab' => 'ai-program']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="aiReviewLoading"', $html);
        $this->assertStringContainsString('tab-pane fade show active', $html);
        $this->assertStringContainsString('id="graduation-ai"', $html);
        $this->assertSame(1, substr_count($html, 'tab-pane fade show active'));
    }

    public function test_old_report_history_url_redirects_to_its_tab(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.program-report.history'))
            ->assertRedirect(route('admin.reports', ['tab' => 'history']));
    }

    public function test_old_history_url_preserves_its_filters_through_the_redirect(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.program-report.history', [
                'program_id' => 7,
                'report_type' => 'violations',
                'report_mode' => 'graduating',
            ]));

        $response->assertRedirect();

        $location = (string) $response->headers->get('location');
        $this->assertStringContainsString('tab=history', $location);
        $this->assertStringContainsString('program_id=7', $location);
        $this->assertStringContainsString('report_type=violations', $location);
        $this->assertStringContainsString('report_mode=graduating', $location);
    }

    public function test_sidebar_drops_the_standalone_report_entries_but_keeps_reports(): void
    {
        $sidebar = file_get_contents(resource_path('views/admin/partials/sidebar.blade.php'));

        $this->assertStringContainsString("route('admin.reports')", $sidebar);

        $this->assertStringNotContainsString(
            "route('admin.program-report.index')",
            $sidebar,
            'The standalone AI Program Report sidebar entry must be gone.'
        );
        $this->assertStringNotContainsString(
            "route('admin.program-report.history')",
            $sidebar,
            'The standalone Report History sidebar entry must be gone.'
        );
    }

    public function test_superseded_standalone_views_are_deleted_and_tabs_exist(): void
    {
        $this->assertFileDoesNotExist(resource_path('views/admin/reports/index.blade.php'));
        $this->assertFileDoesNotExist(resource_path('views/admin/program-report/index.blade.php'));
        $this->assertFileDoesNotExist(resource_path('views/admin/program-report/history.blade.php'));

        $this->assertFileExists(resource_path('views/admin/reports/show.blade.php'));
        $this->assertFileExists(resource_path('views/admin/reports/tabs/violation-reports.blade.php'));
        $this->assertFileExists(resource_path('views/admin/reports/tabs/report-history.blade.php'));

        // The Graduation AI Report tab replaced the AI Program Report tab.
        $this->assertFileExists(resource_path('views/admin/reports/tabs/graduation-ai-report.blade.php'));

        // The program-statistics form partial is RETAINED (only its host tab was
        // replaced), so the feature is not deleted.
        $this->assertFileExists(resource_path('views/admin/reports/tabs/ai-program-report.blade.php'));
    }

    // ==================================================================
    // 7. Tab switching must never reveal a blank pane
    // ==================================================================

    /**
     * REGRESSION GUARD — the blank-tab bug.
     *
     * Bootstrap's tab plugin only toggles `display:none`; it never fetches
     * content. An earlier version rendered ONLY the active pane, so switching to
     * any other tab revealed an empty pane. Every tab is now rendered, so each
     * pane's real content must be present in the SAME response.
     */
    public function test_every_tab_pane_is_rendered_so_switching_never_shows_a_blank_tab(): void
    {
        $admin = $this->createAdmin();

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.reports'))
            ->assertOk()
            ->getContent();

        // All three panes exist...
        $this->assertStringContainsString('id="violation-reports"', $html);
        $this->assertStringContainsString('id="graduation-ai"', $html);
        $this->assertStringContainsString('id="history"', $html);

        // ...and each carries REAL content, not an empty div.
        // Violation Reports: the filter form.
        $this->assertStringContainsString('id="reportFilterForm"', $html);
        // Graduation AI Report: the AI loading overlay and the screening table.
        $this->assertStringContainsString('id="aiReviewLoading"', $html);
        $this->assertStringContainsString('Graduating Students — Disciplinary Screening', $html);
        // Report History: its filter form.
        $this->assertStringContainsString(route('admin.program-report.history'), $html);
        // The superseded AI Program Report tab is gone.
        $this->assertStringNotContainsString('id="ai-program"', $html);

        // Exactly one pane is active at a time.
        $this->assertSame(1, substr_count($html, 'tab-pane fade show active'));
    }

    /**
     * REGRESSION GUARD — the variable collision.
     *
     * The AI Program Report form and the Report History table both need a
     * program list. When they shared the name `$programs`, the AI tab's guard
     * passed using the HISTORY dataset and then failed on `$ladderizedPrograms`,
     * producing a 500. Each tab must still render its own complete content on
     * every tab URL.
     */
    public function test_no_variable_collision_between_the_tabs_on_any_tab_url(): void
    {
        $admin = $this->createAdmin();

        // The `ai-program` value is still honoured as a legacy alias, so it is
        // included here to prove the alias renders the same complete page.
        foreach (['violation-reports', 'graduation-ai', 'ai-program', 'history'] as $tab) {
            $html = $this->actingAs($admin, 'admin')
                ->get(route('admin.reports', ['tab' => $tab]))
                ->assertOk()
                ->getContent();

            // The Graduation AI Report must be complete (its program list comes
            // from the graduation dataset, not from the history list).
            $this->assertStringContainsString('id="aiReviewLoading"', $html, "Graduation AI content missing on tab={$tab}");
            $this->assertStringContainsString('Graduating Students — Disciplinary Screening', $html, "Graduation table missing on tab={$tab}");
            $this->assertStringContainsString(route('admin.graduation-review.index'), $html, "Graduation filter action missing on tab={$tab}");

            // The history table must be complete.
            $this->assertStringContainsString('Report Mode', $html, "History filters missing on tab={$tab}");
        }
    }

    // ==================================================================
    // 8. Top 10 Most Common Violations chart
    // ==================================================================

    /**
     * The chart is labelled "Top 10", so the SERVER must cap the dataset at 10
     * (most common first) rather than shipping every type and hiding the extras.
     */
    public function test_top_violations_chart_is_limited_to_ten_most_common(): void
    {
        $admin = $this->createAdmin();

        // The fixture seed already creates one violation type ('Cheating', id 1).
        // The chart counts violations, so that type starts with a count of 12
        // (the same as "Type01"), which would make the top-10 ORDER ambiguous.
        // A distinct, strictly-descending sequence is used instead: the 12 seeded
        // types get counts 25..14, so the top 10 is unambiguous and the pre-seed
        // type is counted in the same way as every other type.
        $this->assertSame(1, DB::table('violation_type_tbl')->count(), 'Expected exactly the one seeded type.');

        $expectedTop = [];
        for ($typeId = 2; $typeId <= 13; $typeId++) {
            $name = 'Type' . str_pad((string) ($typeId - 1), 2, '0', STR_PAD_LEFT);
            $this->createViolationType($typeId, $name);

            // Counts 25 down to 14 — strictly descending, well above the seeded
            // type's count so the ordering is deterministic.
            $count = 27 - $typeId;
            if ($typeId <= 11) {
                $expectedTop[$name] = $count;
            }

            for ($n = 0; $n < $count; $n++) {
                $number = '2027-' . str_pad((string) $typeId, 2, '0', STR_PAD_LEFT) . '-' . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
                $this->createStudent($number, 'Charted', $number);
                $this->createViolation($number, now()->toDateString(), $typeId, Violation::STATUS_CONFIRMED);
            }
        }

        $response = $this->actingAs($admin, 'admin')
            ->postJson(route('admin.reports.filter'), [])
            ->assertOk();

        $top = $response->json('charts.topViolations');

        $this->assertNotNull($top, 'The topViolations chart dataset must be present.');

        // Exactly 10 bars, even though 12 types exist.
        $this->assertCount(10, $top['labels'], 'The chart must contain exactly 10 violations.');
        $this->assertCount(10, $top['data']);

        // The 10 most common, in descending order.
        $this->assertSame(array_keys($expectedTop), $top['labels']);
        $this->assertSame(array_values($expectedTop), $top['data']);

        // The 11th and 12th most common must NOT appear.
        $this->assertNotContains('Type11', $top['labels']);
        $this->assertNotContains('Type12', $top['labels']);

        // Counts are unchanged — derived from the same query as before.
        $this->assertSame(25, $top['data'][0], 'The most common type must keep its full count.');

        // Descending order is enforced.
        $sorted = $top['data'];
        rsort($sorted);
        $this->assertSame($sorted, $top['data'], 'The chart data must be sorted descending by frequency.');
    }

    public function test_top_violations_chart_shows_only_available_types_when_fewer_than_ten_exist(): void
    {
        $admin = $this->createAdmin();

        // Only 3 distinct violation types.
        for ($typeId = 1; $typeId <= 3; $typeId++) {
            $this->createViolationType($typeId, 'Only' . $typeId);
        }

        for ($i = 1; $i <= 3; $i++) {
            $this->createStudent('2028-000' . $i, 'Few', 'Types' . $i);
            $this->createViolation('2028-000' . $i, now()->toDateString(), $i, Violation::STATUS_CONFIRMED);
        }

        $top = $this->actingAs($admin, 'admin')
            ->postJson(route('admin.reports.filter'), [])
            ->assertOk()
            ->json('charts.topViolations');

        $this->assertCount(3, $top['labels'], 'Fewer than 10 types must show only the available ones.');
        $this->assertCount(3, $top['data']);
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    /**
     * Dataset for rendering the retained program-statistics form partial.
     *
     * The partial is no longer hosted by any Reports tab (its host tab became the
     * Graduation AI Report), so tests render it directly with this dataset — the
     * same shape `ProgramReportController::formData()` supplies.
     *
     * @return array{programs: \Illuminate\Database\Eloquent\Collection, ladderizedPrograms: array<int, string>}
     */
    private function programReportFormData(): array
    {
        return [
            'programs' => Program::orderBy('program_name')->get(),
            'ladderizedPrograms' => config('graduation_review.graduating.ladderized_programs', ['BSOA', 'BSIT']),
            // The partial reads `$errors`, which Laravel normally shares with
            // every view during a request. Rendering it directly needs the bag.
            'errors' => new ViewErrorBag(),
        ];
    }

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

    private function createViolationType(int $id, string $name): void
    {
        if (DB::table('violation_type_tbl')->where('violation_type_id', $id)->exists()) {
            return;
        }

        DB::table('violation_type_tbl')->insert([
            'violation_type_id' => $id,
            'violation_type' => $name,
            'violation_category_id' => 1,
            'severity_level' => 1,
        ]);
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
