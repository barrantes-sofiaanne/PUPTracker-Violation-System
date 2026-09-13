<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DisciplinarySanction;
use App\Models\Notification;
use App\Models\StudentSanctionRecord;
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
 * Student Module UI/data-display changes.
 *
 * Covers the requested behaviour:
 *   - Dashboard: no Unread Notifications card, no Latest Notifications section,
 *     violation preview limited to the 3 most recent, and an Approaching
 *     Deadlines section that deep-links to the exact sanction.
 *   - Violation Record: Summary includes Admin-recorded violations, still
 *     scoped to the authenticated student; Individual Log shows "Confirmed".
 *   - Sanction Record: Office/Starting rendered once; Submit Proof modal
 *     centred with maroon action buttons.
 *   - Notifications: only "Mark All as Read" remains as a bulk action.
 *   - Profile: student email is displayed.
 *   - Tables use the project's responsive wrapper.
 */
class StudentModuleUiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
    }

    // ==================================================================
    // Dashboard
    // ==================================================================

    public function test_dashboard_does_not_render_unread_notifications_card(): void
    {
        $student = $this->createStudent('2024-0001');
        $this->createNotification('2024-0001', 'Something happened');

        $html = $this->actingAs($student, 'student')
            ->get(route('student.dashboard'))
            ->assertOk()
            ->getContent();

        // The card label must be gone...
        $this->assertStringNotContainsString('Unread Notifications', $html);

        // ...but the notification itself and its tab still work.
        $this->assertStringContainsString('Total Violations', $html);
        $this->actingAs($student, 'student')
            ->get(route('student.notifications'))
            ->assertOk()
            ->assertSee('Something happened');
    }

    public function test_dashboard_does_not_render_latest_notifications_section(): void
    {
        $student = $this->createStudent('2024-0001');
        $this->createNotification('2024-0001', 'A notification body');

        $html = $this->actingAs($student, 'student')
            ->get(route('student.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Latest Notifications', $html);
        $this->assertStringContainsString('Approaching Deadlines', $html);
    }

    public function test_dashboard_shows_only_the_three_most_recent_violations(): void
    {
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Type One');
        $this->createViolationType(2, 'Type Two');
        $this->createViolationType(3, 'Type Three');
        $this->createViolationType(4, 'Type Four');
        $this->createViolationType(5, 'Type Five');

        // Insert oldest first so insertion order cannot be mistaken for the
        // chronological ordering the requirements ask for.
        $this->createViolation('2024-0001', 1, now()->subDays(50));
        $this->createViolation('2024-0001', 2, now()->subDays(40));
        $this->createViolation('2024-0001', 3, now()->subDays(30));
        $this->createViolation('2024-0001', 4, now()->subDays(20));
        $this->createViolation('2024-0001', 5, now()->subDays(10));

        $html = $this->actingAs($student, 'student')
            ->get(route('student.dashboard'))
            ->assertOk()
            ->getContent();

        // Scope the assertions to the VIOLATION RECORDS PREVIEW card. The
        // dashboard also renders the "Violation Code & Sanctions Guide", which
        // legitimately lists every violation type in the system, so a
        // whole-page assertion would be meaningless here.
        $preview = $this->violationPreviewRegion($html);

        // The three newest must be present...
        foreach (['Type Five', 'Type Four', 'Type Three'] as $expected) {
            $this->assertStringContainsString($expected, $preview, "{$expected} should appear in the dashboard preview.");
        }

        // ...and the two oldest must not.
        foreach (['Type One', 'Type Two'] as $unexpected) {
            $this->assertStringNotContainsString(
                $unexpected,
                $preview,
                "{$unexpected} is older than the 3 most recent and must not appear in the preview."
            );
        }

        // Older records are untouched in the Violation Record tab.
        $record = $this->actingAs($student, 'student')->get(route('student.record'))->assertOk()->getContent();
        $this->assertStringContainsString('Type One', $record);
    }

    public function test_dashboard_shows_fewer_than_three_when_that_is_all_there_is(): void
    {
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Only Type');
        $this->createViolation('2024-0001', 1, now()->subDay());

        $this->actingAs($student, 'student')
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Only Type');
    }

    // ==================================================================
    // Approaching deadlines
    // ==================================================================

    public function test_approaching_deadlines_excludes_completed_and_other_students(): void
    {
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');

        $mine = $this->createViolation('2024-0001', 1, now()->subDays(5));
        $other = $this->createViolation('2024-0002', 1, now()->subDays(5));

        // Mine, open, with a deadline.
        StudentSanctionRecord::create([
            'student_number' => '2024-0001',
            'violation_id' => $mine->violation_id,
            'assigned_sanction_id' => 1,
            'status' => StudentSanctionRecord::STATUS_IN_PROGRESS,
            'deadline_date' => now()->addDays(3)->toDateString(),
            'designated_office' => 'Office of Student Affairs',
        ]);

        // Mine but completed -> not an approaching deadline.
        StudentSanctionRecord::create([
            'student_number' => '2024-0001',
            'violation_id' => $mine->violation_id,
            'assigned_sanction_id' => 1,
            'status' => StudentSanctionRecord::STATUS_COMPLETED,
            'deadline_date' => now()->addDays(4)->toDateString(),
            'designated_office' => 'Completed Office',
        ]);

        // Another student's open sanction -> must never appear.
        StudentSanctionRecord::create([
            'student_number' => '2024-0002',
            'violation_id' => $other->violation_id,
            'assigned_sanction_id' => 1,
            'status' => StudentSanctionRecord::STATUS_IN_PROGRESS,
            'deadline_date' => now()->addDays(1)->toDateString(),
            'designated_office' => 'Other Student Office',
        ]);

        $html = $this->actingAs($student, 'student')
            ->get(route('student.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Office of Student Affairs', $html);
        $this->assertStringNotContainsString('Completed Office', $html, 'Completed sanctions are not approaching deadlines.');
        $this->assertStringNotContainsString('Other Student Office', $html, 'Another student\'s sanction must never be exposed.');
        $this->assertStringNotContainsString('2024-0002', $html);
    }

    public function test_approaching_deadlines_empty_state(): void
    {
        $student = $this->createStudent('2024-0001');

        $html = $this->actingAs($student, 'student')
            ->get(route('student.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Approaching Deadlines', $html);
        $this->assertStringContainsString('No approaching deadlines.', $html);
    }

    public function test_deadline_view_action_identifies_the_specific_sanction(): void
    {
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');
        $violation = $this->createViolation('2024-0001', 1, now()->subDay());

        $record = StudentSanctionRecord::create([
            'student_number' => '2024-0001',
            'violation_id' => $violation->violation_id,
            'assigned_sanction_id' => 1,
            'status' => StudentSanctionRecord::STATUS_IN_PROGRESS,
            'deadline_date' => now()->addDays(2)->toDateString(),
            'designated_office' => 'Office of Student Affairs',
        ]);

        $html = $this->actingAs($student, 'student')
            ->get(route('student.dashboard'))
            ->assertOk()
            ->getContent();

        // The View action must carry the specific record id AND target the
        // Sanction Record tab.
        $this->assertStringContainsString('sanction=' . $record->record_id, $html);
        $this->assertStringContainsString('#sanctionRecord', $html);

        // The Sanction Record page must expose a matching row anchor so the
        // sanction is identifiable after navigation/reload.
        $record_html = $this->actingAs($student, 'student')
            ->get(route('student.record', ['sanction' => $record->record_id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'data-sanction-record-id="' . $record->record_id . '"',
            $record_html,
            'The sanction row must be addressable so it can be highlighted.'
        );
    }

    // ==================================================================
    // Violation Record — Summary includes Admin-recorded violations
    // ==================================================================

    public function test_summary_by_violation_includes_admin_recorded_violations(): void
    {
        $student = $this->createStudent('2024-0001');
        $this->createAdmin('admin@example.com');
        $this->createViolationType(1, 'Admin Recorded Offence');
        $this->createDisciplinarySanction(1, '1st Offense', 'One month suspension');

        // Exactly how the Admin module persists a violation: recorder_type
        // 'admin' and an immediately-confirmed status.
        Violation::create([
            'student_number' => '2024-0001',
            'violation_type' => 1,
            'violation_date' => now()->subDay(),
            'description' => 'Recorded by an administrator.',
            'recorder_id' => 1,
            'recorder_type' => 'admin',
            'recorder_name' => 'Admin One',
            'status' => Violation::STATUS_CONFIRMED,
            'reviewed_by_admin_id' => 1,
            'reviewed_at' => now(),
        ]);

        $html = $this->actingAs($student, 'student')
            ->get(route('student.record'))
            ->assertOk()
            ->getContent();

        $summary = $this->summaryRegion($html);

        $this->assertStringContainsString(
            'Admin Recorded Offence',
            $summary,
            'Admin-recorded violations must appear in Summary by Violation.'
        );

        // The offence must be counted as a confirmed violation in the summary.
        $this->assertStringContainsString('1st Offense', $summary);
    }

    public function test_summary_by_violation_excludes_other_students(): void
    {
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Mine Only');
        $this->createViolationType(2, 'Someone Elses');
        $this->createDisciplinarySanction(1, '1st Offense', 'One month suspension');
        $this->createDisciplinarySanction(2, '1st Offense', 'One month suspension');

        $this->createViolation('2024-0001', 1, now()->subDay());
        $this->createViolation('2024-0099', 2, now()->subDay());

        $html = $this->actingAs($student, 'student')
            ->get(route('student.record'))
            ->assertOk()
            ->getContent();

        $summary = $this->summaryRegion($html);

        $this->assertStringContainsString('Mine Only', $summary);
        $this->assertStringNotContainsString(
            'Someone Elses',
            $html,
            'Another student\'s violation must never be visible.'
        );
    }

    public function test_summary_does_not_duplicate_rows_for_repeated_admin_recordings(): void
    {
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Repeated Offence');
        $this->createDisciplinarySanction(1, '1st Offense', 'One month suspension');
        $this->createDisciplinarySanction(1, '2nd Offense', 'Two month suspension');

        // Two recordings of the SAME type must aggregate into ONE summary row.
        $this->createViolation('2024-0001', 1, now()->subDays(10));
        $this->createViolation('2024-0001', 1, now()->subDays(2));

        $html = $this->actingAs($student, 'student')
            ->get(route('student.record'))
            ->assertOk()
            ->getContent();

        $summary = $this->summaryRegion($html);

        $this->assertSame(
            1,
            substr_count($summary, 'Repeated Offence'),
            'A violation type must produce exactly one summary row.'
        );

        // Both instances remain visible in the Individual Log.
        $this->assertSame(2, substr_count($html, 'Repeated Offence') - 1);
    }

    // ==================================================================
    // Violation Record — status label + responsive tables
    // ==================================================================

    public function test_individual_violation_log_shows_confirmed_instead_of_on_record(): void
    {
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Confirmed Offence');
        $this->createDisciplinarySanction(1, '1st Offense', 'One month suspension');
        $this->createViolation('2024-0001', 1, now()->subDay());

        $html = $this->actingAs($student, 'student')
            ->get(route('student.record'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('On Record', $html);
        $this->assertStringContainsString('Confirmed', $html);

        // The stored status value must be untouched.
        $this->assertDatabaseHas('violation_tbl', [
            'student_number' => '2024-0001',
            'status' => Violation::STATUS_CONFIRMED,
        ]);
    }

    public function test_pending_violation_still_shows_pending_confirmation(): void
    {
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Pending Offence');

        Violation::create([
            'student_number' => '2024-0001',
            'violation_type' => 1,
            'violation_date' => now()->subDay(),
            'description' => 'Awaiting confirmation.',
            'recorder_type' => 'security',
            'status' => Violation::STATUS_PENDING,
        ]);

        $this->actingAs($student, 'student')
            ->get(route('student.record'))
            ->assertOk()
            ->assertSee('Pending Confirmation');
    }

    public function test_violation_record_tables_use_the_responsive_wrapper(): void
    {
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');
        $this->createViolation('2024-0001', 1, now()->subDay());

        $html = $this->actingAs($student, 'student')
            ->get(route('student.record'))
            ->assertOk()
            ->getContent();

        // Summary, Individual Log and Sanction Record each sit inside the
        // shared .table-responsive container (which owns the horizontal
        // scrolling, keeping the page itself from overflowing).
        $this->assertSame(
            3,
            substr_count($html, 'class="table-responsive"'),
            'Every Violation/Sanction table must use the responsive wrapper.'
        );

        // Each wrapper must immediately contain its table.
        preg_match_all('/class="table-responsive"\s*>\s*<table/', $html, $wrapped);
        $this->assertCount(3, $wrapped[0], 'Each responsive wrapper must wrap a table directly.');
    }

    // ==================================================================
    // Sanction Record — Office/Starting once, modal, maroon buttons
    // ==================================================================

    public function test_office_and_starting_are_rendered_only_once(): void
    {
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        // A Community/Campus Service sanction is what requires proof of
        // completion, so it is the case where Office/Starting are shown.
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');
        $violation = $this->createViolation('2024-0001', 1, now()->subDay());

        StudentSanctionRecord::create([
            'student_number' => '2024-0001',
            'violation_id' => $violation->violation_id,
            'assigned_sanction_id' => 1,
            'status' => StudentSanctionRecord::STATUS_IN_PROGRESS,
            'deadline_date' => now()->addDays(5)->toDateString(),
            'designated_office' => 'Office of Student Affairs',
            'starting_date' => now()->addDays(1)->toDateString(),
            'required_hours' => 40,
        ]);

        $html = $this->actingAs($student, 'student')
            ->get(route('student.record'))
            ->assertOk()
            ->getContent();

        // The duplicate summary block that used to sit above Submit Proof must
        // be gone entirely (markup AND its styles).
        $this->assertStringNotContainsString('proof-service-summary', $html);
        $this->assertStringNotContainsString('proof-service-summary-label', $html);

        // The labelled Office/Starting/Hours fields are now rendered in exactly
        // ONE place: the Disciplinary Sanction cell.
        $this->assertSame(1, substr_count($html, 'Designated Office:'));
        $this->assertSame(1, substr_count($html, 'Starting Date:'));
        $this->assertSame(1, substr_count($html, 'Required Hours:'));

        // The office appears in the visible details and once more as the Submit
        // Proof modal's data payload (which pre-fills the dialog) — but never
        // as a second visible summary block.
        $this->assertSame(2, substr_count($html, 'Office of Student Affairs'));
        $this->assertSame(1, substr_count($html, 'data-office="Office of Student Affairs"'));
        $this->assertSame(1, substr_count($html, '>Office of Student Affairs<'));

        // The Submit Proof action is still offered for this sanction.
        $this->assertStringContainsString('Submit Proof', $html);
    }

    public function test_submit_proof_modal_is_centered(): void
    {
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');
        $violation = $this->createViolation('2024-0001', 1, now()->subDay());

        StudentSanctionRecord::create([
            'student_number' => '2024-0001',
            'violation_id' => $violation->violation_id,
            'assigned_sanction_id' => 1,
            'status' => StudentSanctionRecord::STATUS_IN_PROGRESS,
            'deadline_date' => now()->addDays(5)->toDateString(),
            'designated_office' => 'Office of Student Affairs',
            'required_hours' => 40,
        ]);

        $html = $this->actingAs($student, 'student')
            ->get(route('student.record'))
            ->assertOk()
            ->getContent();

        // Bootstrap centres the dialog through these classes; both the
        // horizontal and vertical centring plus scrollability must be present.
        $this->assertStringContainsString('modal-dialog-centered', $html);
        $this->assertStringContainsString('modal-dialog-scrollable', $html);

        // The old leaking `max-height` override that made the dialog look
        // off-centre on short screens must be gone (it only survived as prose
        // in a comment, so match the CSS declaration form).
        $this->assertStringNotContainsString('max-height: calc(100vh - 15rem)', $html);
    }

    public function test_submit_proof_modal_action_buttons_use_maroon_styling(): void
    {
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');
        $violation = $this->createViolation('2024-0001', 1, now()->subDay());

        StudentSanctionRecord::create([
            'student_number' => '2024-0001',
            'violation_id' => $violation->violation_id,
            'assigned_sanction_id' => 1,
            'status' => StudentSanctionRecord::STATUS_IN_PROGRESS,
            'deadline_date' => now()->addDays(5)->toDateString(),
            'designated_office' => 'Office of Student Affairs',
            'required_hours' => 40,
        ]);

        $html = $this->actingAs($student, 'student')
            ->get(route('student.record'))
            ->assertOk()
            ->getContent();

        $modal = substr($html, strpos($html, 'id="submitProofModal"'));
        $modal = substr($modal, 0, strpos($modal, '</form>'));

        // Download Official Form must use the same maroon button as Submit Proof.
        $this->assertMatchesRegularExpression(
            '/id="officialFormDownloadBtn"[^>]*class="btn portal-btn"/',
            $modal,
            'Download Official Form must use the maroon .portal-btn styling.'
        );

        // The outline variant is no longer used inside this modal.
        $this->assertDoesNotMatchRegularExpression(
            '/id="officialFormDownloadBtn"[^>]*portal-btn-outline/',
            $modal
        );

        // The submit button keeps its maroon styling.
        $this->assertStringContainsString('id="submitProofBtn" disabled', $modal);
        $this->assertMatchesRegularExpression('/class="btn portal-btn"\s+id="submitProofBtn"/', $modal);
    }

    // ==================================================================
    // Notifications
    // ==================================================================

    public function test_notification_bulk_actions_are_simplified_to_mark_all_as_read(): void
    {
        $student = $this->createStudent('2024-0001');
        $this->createNotification('2024-0001', 'First notification');

        $html = $this->actingAs($student, 'student')
            ->get(route('student.notifications'))
            ->assertOk()
            ->getContent();

        // Select All and both selection-based buttons are gone.
        $this->assertStringNotContainsString('Select All', $html);
        $this->assertStringNotContainsString('selectAllNotifications', $html);
        $this->assertStringNotContainsString('markSelectedReadBtn', $html);
        $this->assertStringNotContainsString('markSelectedUnreadBtn', $html);
        $this->assertStringNotContainsString('Mark Selected as Read', $html);
        $this->assertStringNotContainsString('Mark Selected as Unread', $html);

        // Only Mark All as Read remains.
        $this->assertStringContainsString('Mark All as Read', $html);
        $this->assertStringContainsString('markAllReadBtn', $html);

        // Individual notifications remain listed and openable.
        $this->assertStringContainsString('First notification', $html);
    }

    public function test_mark_all_as_read_remains_functional(): void
    {
        $student = $this->createStudent('2024-0001');
        $this->createNotification('2024-0001', 'Unread one');
        $this->createNotification('2024-0001', 'Unread two');

        $this->actingAs($student, 'student')
            ->post(route('student.notifications.status'), ['action' => 'mark_all_read'])
            ->assertRedirect();

        $this->assertSame(0, Notification::where('student_number', '2024-0001')->where('is_read', false)->count());
    }

    public function test_mark_all_as_read_is_scoped_to_the_authenticated_student(): void
    {
        $student = $this->createStudent('2024-0001');
        $this->createNotification('2024-0001', 'Mine');
        $this->createNotification('2024-9999', 'Someone else');

        $this->actingAs($student, 'student')
            ->post(route('student.notifications.status'), ['action' => 'mark_all_read'])
            ->assertRedirect();

        $this->assertSame(
            0,
            Notification::where('student_number', '2024-0001')->where('is_read', false)->count()
        );
        $this->assertSame(
            1,
            Notification::where('student_number', '2024-9999')->where('is_read', false)->count(),
            'Another student\'s notifications must not be affected.'
        );
    }

    // ==================================================================
    // Profile
    // ==================================================================

    public function test_profile_displays_the_student_email(): void
    {
        $student = $this->createStudent('2024-0001');

        $this->actingAs($student, 'student')
            ->get(route('student.profile'))
            ->assertOk()
            ->assertSee('2024-0001@example.com');
    }

    public function test_profile_does_not_expose_another_students_email(): void
    {
        $student = $this->createStudent('2024-0001');
        $this->createStudent('2024-0002');

        $html = $this->actingAs($student, 'student')
            ->get(route('student.profile'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('2024-0001@example.com', $html);
        $this->assertStringNotContainsString('2024-0002@example.com', $html);
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    /**
     * The Summary by Violation region only (between its heading and the
     * Individual Violations Log), so assertions about the summary cannot be
     * satisfied by the log table.
     */
    private function summaryRegion(string $html): string
    {
        $start = strpos($html, 'Summary by Violation');
        $end = strpos($html, 'Individual Violations Log');

        if ($start === false) {
            return '';
        }

        return $end === false ? substr($html, $start) : substr($html, $start, $end - $start);
    }

    /**
     * The dashboard's "Your Violation Records" preview card only — from its
     * heading up to the next dashboard card, so the handbook (which lists every
     * violation type) cannot satisfy or break these assertions.
     */
    private function violationPreviewRegion(string $html): string
    {
        $start = strpos($html, 'Your Violation Records');

        if ($start === false) {
            return '';
        }

        $end = strpos($html, 'Approaching Deadlines', $start);

        return $end === false ? substr($html, $start) : substr($html, $start, $end - $start);
    }

    private function createAdmin(string $email): Admin
    {
        return Admin::create([
            'username' => $email,
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
            DB::table('student_info_tbl')->insert([
                'user_id' => $user->user_id,
                'program_id' => null,
                'year_id' => null,
                'section_id' => null,
            ]);
        }

        return $user;
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

    private function createDisciplinarySanction(int $typeId, string $offense, string $sanction): void
    {
        DisciplinarySanction::create([
            'violation_type_id' => $typeId,
            'offense_level' => $offense,
            'disciplinary_sanction' => $sanction,
        ]);
    }

    private function createViolation(string $studentNumber, int $typeId, $date): Violation
    {
        return Violation::create([
            'student_number' => $studentNumber,
            'violation_type' => $typeId,
            'violation_date' => $date,
            'description' => 'Test violation.',
            'recorder_type' => 'admin',
            'recorder_name' => 'Admin One',
            'status' => Violation::STATUS_CONFIRMED,
        ]);
    }

    private function createNotification(string $studentNumber, string $message): Notification
    {
        return Notification::create([
            'student_number' => $studentNumber,
            'recipient_type' => 'student',
            'type' => 'violation',
            'notification_type' => 'violation',
            'message' => $message,
            'is_read' => false,
            'created_at' => now(),
        ]);
    }

    private function createTables(): void
    {
        $tables = [
            'admins' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('username')->nullable();
                $table->string('email');
                $table->string('password');
                $table->boolean('mfa_totp_enabled')->default(false);
            },
            'security' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('email');
                $table->string('password');
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
            'users_tbl' => function (Blueprint $table): void {
                $table->increments('user_id');
                $table->string('student_number')->nullable();
                $table->string('first_name')->nullable();
                $table->string('middle_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('name_suffix')->nullable();
                $table->string('email')->nullable();
                $table->string('idp_email')->nullable();
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
                $table->boolean('ladderized')->default(false);
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
                $table->string('status', 20)->nullable();
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
            'sanction_requests_tbl' => function (Blueprint $table): void {
                $table->increments('request_id');
                $table->string('student_number');
                $table->unsignedInteger('violation_type_id');
                $table->dateTime('request_date')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('status')->nullable();
                $table->unsignedInteger('approved_by_admin_id')->nullable();
                $table->dateTime('approved_at')->nullable();
            },
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
            'sanction_proofs_tbl' => function (Blueprint $table): void {
                $table->increments('proof_id');
                $table->string('proof_code', 32)->nullable();
                $table->unsignedBigInteger('record_id')->nullable();
                $table->string('student_number', 50)->nullable();
                $table->string('disk_path', 500)->nullable();
                $table->string('original_name', 255)->nullable();
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->char('sha256_hash', 64)->nullable();
                $table->timestamp('created_at')->nullable();
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
            'audit_logs' => function (Blueprint $table): void {
                $table->increments('audit_log_id');
                $table->string('actor_type')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('action');
                $table->string('module')->nullable();
                $table->text('description')->nullable();
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

        if (! Schema::hasTable('violation_category_tbl') === false) {
            // Seed the single category the helpers reference.
            if (DB::table('violation_category_tbl')->count() === 0) {
                DB::table('violation_category_tbl')->insert([
                    'violation_category_id' => 1,
                    'category_name' => 'Major Offenses',
                ]);
            }
        }
    }
}
