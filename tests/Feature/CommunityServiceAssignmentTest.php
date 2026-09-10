<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DisciplinarySanction;
use App\Models\SanctionProof;
use App\Models\SanctionRequest;
use App\Models\StudentSanctionRecord;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Community Service / Campus Service assignment workflow.
 *
 * Covers:
 *  1.  Admin creates Community Service sanction with designated office + starting date.
 *  2.  Admin creates Campus Service sanction with designated office + starting date.
 *  3.  Community Service requires designated office.
 *  4.  Community Service requires starting date.
 *  5.  Campus Service requires designated office.
 *  6.  Campus Service requires starting date.
 *  7.  Other sanction types do not require these fields.
 *  8.  Approved Community Service assignment is visible to the student.
 *  9.  Approved Campus Service assignment is visible to the student.
 *  10. Designated office is displayed in the Proof of Completion table.
 *  11. Starting date is displayed in the Proof of Completion table.
 *  12. Student cannot modify designated office.
 *  13. Student cannot modify starting date.
 *  14. Student can submit proof for the assigned sanction.
 *  15. Uploaded proof does not automatically mark the sanction completed.
 *  16. Rejected proof follows the existing resubmission workflow.
 *  17. Another student cannot access another student's sanction/proof data.
 */
class CommunityServiceAssignmentTest extends TestCase
{
    // -----------------------------------------------------------------
    // 1 & 2. Admin creates CS / Campus Service with office + start date.
    // -----------------------------------------------------------------

    public function test_admin_creates_community_service_with_office_and_starting_date(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');
        $this->createConfirmedViolation('2024-0001', 1);

        $request = $this->createSanctionRequest('2024-0001', 1);
        $start = now()->addDays(5)->toDateString();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.sanctions.requests.approve', $request), [
                'notification_date' => now()->toDateString(),
                'designated_office' => 'Office of Student Affairs',
                'starting_date' => $start,
            ])
            ->assertSessionHas('success');

        $record = StudentSanctionRecord::where('student_number', '2024-0001')->firstOrFail();
        $this->assertSame('Office of Student Affairs', $record->designated_office);
        $this->assertSame($start, $record->starting_date->toDateString());
        $this->assertTrue($record->is_service_sanction);
        $this->assertTrue($record->is_community_service);
    }

    public function test_admin_creates_campus_service_with_office_and_starting_date(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');
        $this->createStudent('2024-0002');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Campus service 20 hours');
        $this->createConfirmedViolation('2024-0002', 1);

        $request = $this->createSanctionRequest('2024-0002', 1);
        $start = now()->addDays(3)->toDateString();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.sanctions.requests.approve', $request), [
                'notification_date' => now()->toDateString(),
                'designated_office' => 'Campus Maintenance Office',
                'starting_date' => $start,
            ])
            ->assertSessionHas('success');

        $record = StudentSanctionRecord::where('student_number', '2024-0002')->firstOrFail();
        $this->assertSame('Campus Maintenance Office', $record->designated_office);
        $this->assertSame($start, $record->starting_date->toDateString());
        $this->assertTrue($record->is_service_sanction);
        $this->assertTrue($record->is_campus_service);
    }

    // -----------------------------------------------------------------
    // 3-6. CS / Campus Service require office + starting date.
    // -----------------------------------------------------------------

    public function test_community_service_requires_designated_office(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');
        $this->createConfirmedViolation('2024-0001', 1);

        $request = $this->createSanctionRequest('2024-0001', 1);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.sanctions.requests.approve', $request), [
                'notification_date' => now()->toDateString(),
                'starting_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('designated_office');

        $this->assertSame(0, StudentSanctionRecord::where('student_number', '2024-0001')->count());
    }

    public function test_community_service_requires_starting_date(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');
        $this->createConfirmedViolation('2024-0001', 1);

        $request = $this->createSanctionRequest('2024-0001', 1);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.sanctions.requests.approve', $request), [
                'notification_date' => now()->toDateString(),
                'designated_office' => 'Office of Student Affairs',
            ])
            ->assertSessionHasErrors('starting_date');

        $this->assertSame(0, StudentSanctionRecord::where('student_number', '2024-0001')->count());
    }

    public function test_campus_service_requires_designated_office(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');
        $this->createStudent('2024-0002');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Campus service 20 hours');
        $this->createConfirmedViolation('2024-0002', 1);

        $request = $this->createSanctionRequest('2024-0002', 1);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.sanctions.requests.approve', $request), [
                'notification_date' => now()->toDateString(),
                'starting_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('designated_office');
    }

    public function test_campus_service_requires_starting_date(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');
        $this->createStudent('2024-0002');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Campus service 20 hours');
        $this->createConfirmedViolation('2024-0002', 1);

        $request = $this->createSanctionRequest('2024-0002', 1);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.sanctions.requests.approve', $request), [
                'notification_date' => now()->toDateString(),
                'designated_office' => 'Campus Maintenance Office',
            ])
            ->assertSessionHasErrors('starting_date');
    }

    // -----------------------------------------------------------------
    // 7. Other sanction types do not require these fields.
    // -----------------------------------------------------------------

    public function test_other_sanction_type_does_not_require_service_fields(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'One month suspension');
        $this->createConfirmedViolation('2024-0001', 1);

        $request = $this->createSanctionRequest('2024-0001', 1);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.sanctions.requests.approve', $request), [
                'notification_date' => now()->toDateString(),
            ])
            ->assertSessionHas('success');

        $record = StudentSanctionRecord::where('student_number', '2024-0001')->firstOrFail();
        // Non-service sanctions carry no assignment data.
        $this->assertNull($record->designated_office);
        $this->assertNull($record->starting_date);
        $this->assertFalse($record->is_service_sanction);
    }

    public function test_non_service_fields_are_ignored_even_if_submitted(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Two weeks suspension');
        $this->createConfirmedViolation('2024-0001', 1);

        $request = $this->createSanctionRequest('2024-0001', 1);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.sanctions.requests.approve', $request), [
                'notification_date' => now()->toDateString(),
                'designated_office' => 'Should Be Ignored',
                'starting_date' => now()->toDateString(),
            ])
            ->assertSessionHas('success');

        $record = StudentSanctionRecord::where('student_number', '2024-0001')->firstOrFail();
        $this->assertNull($record->designated_office);
        $this->assertNull($record->starting_date);
    }

    // -----------------------------------------------------------------
    // 8 & 9. Approved assignment visible to the student.
    // -----------------------------------------------------------------

    public function test_approved_community_service_assignment_visible_to_student(): void
    {
        $this->createTables();
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');
        $this->createConfirmedViolation('2024-0001', 1);

        StudentSanctionRecord::create([
            'student_number' => '2024-0001',
            'violation_id' => 1,
            'assigned_sanction_id' => 1,
            'status' => 'In Progress',
            'date_assigned' => now(),
            'designated_office' => 'Office of Student Affairs',
            'starting_date' => '2026-09-15',
        ]);

        $response = $this->actingAs($student, 'student')->get(route('student.record'));
        $response->assertOk();
        $response->assertSee('Office of Student Affairs');
        $response->assertSee('Sep 15, 2026');
        $response->assertSee('40 hrs');
    }

    public function test_approved_campus_service_assignment_visible_to_student(): void
    {
        $this->createTables();
        $student = $this->createStudent('2024-0002');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Campus service 20 hours');
        $this->createConfirmedViolation('2024-0002', 1);

        StudentSanctionRecord::create([
            'student_number' => '2024-0002',
            'violation_id' => 1,
            'assigned_sanction_id' => 1,
            'status' => 'In Progress',
            'date_assigned' => now(),
            'designated_office' => 'Campus Maintenance Office',
            'starting_date' => '2026-10-01',
        ]);

        $response = $this->actingAs($student, 'student')->get(route('student.record'));
        $response->assertOk();
        $response->assertSee('Campus Maintenance Office');
        $response->assertSee('Oct 01, 2026');
        $response->assertSee('20 hrs');
    }

    // -----------------------------------------------------------------
    // 10 & 11. Office + starting date shown in Proof of Completion table.
    // -----------------------------------------------------------------

    public function test_designated_office_rendered_in_proof_of_completion_table(): void
    {
        $this->createTables();
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');
        $this->createConfirmedViolation('2024-0001', 1);

        StudentSanctionRecord::create([
            'student_number' => '2024-0001',
            'violation_id' => 1,
            'assigned_sanction_id' => 1,
            'status' => 'In Progress',
            'date_assigned' => now(),
            'designated_office' => 'Office of Student Affairs',
            'starting_date' => '2026-09-15',
        ]);

        $response = $this->actingAs($student, 'student')->get(route('student.record'));
        $response->assertOk();
        // Office appears in the Proof of Completion summary block.
        $response->assertSee('proof-service-summary', false);
        $response->assertSee('Office of Student Affairs');
    }

    public function test_starting_date_rendered_in_proof_of_completion_table(): void
    {
        $this->createTables();
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');
        $this->createConfirmedViolation('2024-0001', 1);

        StudentSanctionRecord::create([
            'student_number' => '2024-0001',
            'violation_id' => 1,
            'assigned_sanction_id' => 1,
            'status' => 'In Progress',
            'date_assigned' => now(),
            'designated_office' => 'Office of Student Affairs',
            'starting_date' => '2026-09-15',
        ]);

        $response = $this->actingAs($student, 'student')->get(route('student.record'));
        $response->assertOk();
        $response->assertSee('Sep 15, 2026');
    }

    // -----------------------------------------------------------------
    // 12 & 13. Student cannot modify office / starting date via the
    // proof submission (which is the only student write path).
    // -----------------------------------------------------------------

    public function test_student_cannot_modify_designated_office(): void
    {
        $this->createTables();
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');
        $this->createConfirmedViolation('2024-0001', 1);

        $record = $this->createServiceRecord('2024-0001', 'Office of Student Affairs', '2026-09-15');

        $this->actingAs($student, 'student')
            ->postJson(route('student.submit-proof'), [
                'record_id' => $record->record_id,
                'proof_file' => UploadedFile::fake()->image('proof.jpg', 640, 480),
                'proof_notes' => 'Done.',
                // A malicious student attempts to change the office.
                'designated_office' => 'HACKED OFFICE',
            ])
            ->assertOk();

        $record->refresh();
        $this->assertSame('Office of Student Affairs', $record->designated_office);
    }

    public function test_student_cannot_modify_starting_date(): void
    {
        $this->createTables();
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');
        $this->createConfirmedViolation('2024-0001', 1);

        $record = $this->createServiceRecord('2024-0001', 'Office of Student Affairs', '2026-09-15');

        $this->actingAs($student, 'student')
            ->postJson(route('student.submit-proof'), [
                'record_id' => $record->record_id,
                'proof_file' => UploadedFile::fake()->image('proof.jpg', 640, 480),
                'starting_date' => '1999-01-01',
            ])
            ->assertOk();

        $record->refresh();
        $this->assertSame('2026-09-15', $record->starting_date->toDateString());
    }

    // -----------------------------------------------------------------
    // 14 & 15. Student can submit proof; proof does NOT auto-complete.
    // -----------------------------------------------------------------

    public function test_student_can_submit_proof_and_sanction_stays_open(): void
    {
        $this->createTables();
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');
        $this->createConfirmedViolation('2024-0001', 1);

        $record = $this->createServiceRecord('2024-0001', 'Office of Student Affairs', '2026-09-15');

        $this->actingAs($student, 'student')
            ->postJson(route('student.submit-proof'), [
                'record_id' => $record->record_id,
                'proof_file' => UploadedFile::fake()->image('proof.jpg', 640, 480),
                'proof_notes' => 'Completed my community service.',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $record->refresh();
        // 14: proof recorded.
        $this->assertNotNull($record->proof_path);
        $this->assertTrue($record->has_pending_proof);
        // 15: NOT auto-completed.
        $this->assertNotSame('Completed', $record->status);
        $this->assertNull($record->date_completed);
    }

    // -----------------------------------------------------------------
    // 16. Rejected proof follows the existing resubmission workflow.
    // -----------------------------------------------------------------

    public function test_rejected_proof_clears_pending_state_for_resubmission(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');
        $this->createConfirmedViolation('2024-0001', 1);

        $record = $this->createServiceRecord('2024-0001', 'Office of Student Affairs', '2026-09-15');

        // Student submits proof.
        $this->actingAs($student, 'student')
            ->postJson(route('student.submit-proof'), [
                'record_id' => $record->record_id,
                'proof_file' => UploadedFile::fake()->image('proof.jpg', 640, 480),
            ])
            ->assertOk();

        $record->refresh();
        $this->assertTrue($record->has_pending_proof);

        // Admin rejects it with a reason.
        $this->actingAs($admin, 'admin')
            ->post(route('admin.sanctions.records.reject-proof', $record), [
                'reason' => 'Blurry document.',
            ])
            ->assertSessionHas('success');

        $record->refresh();
        // Rejection clears the pending proof so the student can resubmit,
        // but keeps the record open (never Completed) and retains the office.
        $this->assertNull($record->proof_path);
        $this->assertFalse($record->has_pending_proof);
        $this->assertSame('Blurry document.', $record->completion_remarks);
        $this->assertNotSame('Completed', $record->status);
        $this->assertSame('Office of Student Affairs', $record->designated_office);

        // Student can resubmit.
        $this->actingAs($student, 'student')
            ->postJson(route('student.submit-proof'), [
                'record_id' => $record->record_id,
                'proof_file' => UploadedFile::fake()->image('clearer.jpg', 640, 480),
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $record->refresh();
        $this->assertNotNull($record->proof_path);
        $this->assertTrue($record->has_pending_proof);
    }

    // -----------------------------------------------------------------
    // 17. Another student cannot access another student's data.
    // -----------------------------------------------------------------

    public function test_another_student_cannot_submit_proof_for_someone_elses_sanction(): void
    {
        $this->createTables();
        $owner = $this->createStudent('2024-0001');
        $intruder = $this->createStudent('2024-9999');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');
        $this->createConfirmedViolation('2024-0001', 1);

        $record = $this->createServiceRecord('2024-0001', 'Office of Student Affairs', '2026-09-15');

        // The intruder must NOT be able to submit a proof for the owner's record.
        $this->actingAs($intruder, 'student')
            ->postJson(route('student.submit-proof'), [
                'record_id' => $record->record_id,
                'proof_file' => UploadedFile::fake()->image('proof.jpg', 640, 480),
            ])
            ->assertStatus(404);

        $record->refresh();
        $this->assertNull($record->proof_path);
    }

    public function test_another_student_cannot_download_someone_elses_proof(): void
    {
        $this->createTables();
        $intruder = $this->createStudent('2024-9999');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');
        $this->createConfirmedViolation('2024-0001', 1);

        $record = $this->createServiceRecord('2024-0001', 'Office of Student Affairs', '2026-09-15');
        // Give the record a proof path so a download would otherwise be possible.
        $record->update(['proof_path' => 'sanction-proofs/' . $record->record_id . '/PROOF-2026-000001.pdf']);

        $this->actingAs($intruder, 'student')
            ->get(route('student.proof.download', $record))
            ->assertStatus(403);
    }

    // -----------------------------------------------------------------
    // Admin service-assignment correction.
    // -----------------------------------------------------------------

    public function test_admin_can_correct_service_assignment_before_completion(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');
        $this->createConfirmedViolation('2024-0001', 1);

        $record = $this->createServiceRecord('2024-0001', 'Old Office', '2026-09-15');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.sanctions.records.update-service-assignment', $record), [
                'designated_office' => 'Corrected Office',
                'starting_date' => '2026-09-20',
            ])
            ->assertSessionHas('success');

        $record->refresh();
        $this->assertSame('Corrected Office', $record->designated_office);
        $this->assertSame('2026-09-20', $record->starting_date->toDateString());

        // The change is recorded in the audit log.
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Update Service Assignment',
            'module' => 'Sanction Records',
        ]);
    }

    public function test_admin_cannot_edit_service_assignment_of_non_service_sanction(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'One month suspension');
        $this->createConfirmedViolation('2024-0001', 1);

        $record = StudentSanctionRecord::create([
            'student_number' => '2024-0001',
            'violation_id' => 1,
            'assigned_sanction_id' => 1,
            'status' => 'In Progress',
            'date_assigned' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.sanctions.records.update-service-assignment', $record), [
                'designated_office' => 'Some Office',
                'starting_date' => '2026-09-20',
            ])
            ->assertSessionHasErrors('sanction_record');

        $record->refresh();
        $this->assertNull($record->designated_office);
    }

    // -----------------------------------------------------------------
    // 18-21. Non-service sanctions do NOT take a proof of completion.
    // They are settled through a personal consultation at the Office of
    // Student Services.
    // -----------------------------------------------------------------

    public function test_non_service_sanction_requires_no_proof_of_completion(): void
    {
        $this->createTables();
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'One month suspension');
        $this->createConfirmedViolation('2024-0001', 1);

        $record = $this->createNonServiceRecord('2024-0001');

        $this->assertFalse($record->requires_proof_of_completion);
        $this->assertFalse($record->is_service_sanction);

        // The student sees the consultation notice and NO upload button.
        $content = $this->actingAs($student, 'student')
            ->get(route('student.record'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Please proceed to the Office of Student Services', $content);
        $this->assertStringContainsString('personal consultation with the head of the office', $content);
        // The notice element is rendered (not merely the stylesheet rule).
        $this->assertStringContainsString('<div class="consultation-notice small"', $content);
        $this->assertStringNotContainsString('class="btn portal-btn btn-sm submit-proof-btn"', $content);
    }

    public function test_service_sanction_still_requires_proof_of_completion(): void
    {
        $this->createTables();
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');
        $this->createConfirmedViolation('2024-0001', 1);

        $record = $this->createServiceRecord('2024-0001', 'Office of Student Affairs', '2026-09-15');

        $this->assertTrue($record->requires_proof_of_completion);

        $content = $this->actingAs($student, 'student')
            ->get(route('student.record'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('class="btn portal-btn btn-sm submit-proof-btn"', $content);
        // The consultation notice element is only rendered for non-service
        // sanctions (the CSS class itself is always present in the stylesheet).
        $this->assertStringNotContainsString('<div class="consultation-notice small"', $content);
    }

    public function test_student_cannot_submit_proof_for_non_service_sanction(): void
    {
        $this->createTables();
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'One month suspension');
        $this->createConfirmedViolation('2024-0001', 1);

        $record = $this->createNonServiceRecord('2024-0001');

        // A proof upload is rejected server-side even if the client posts one.
        $this->actingAs($student, 'student')
            ->postJson(route('student.submit-proof'), [
                'record_id' => $record->record_id,
                'proof_file' => UploadedFile::fake()->image('proof.jpg', 640, 480),
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $record->refresh();
        $this->assertNull($record->proof_path);
        $this->assertSame(0, SanctionProof::count());

        // The record stays open — it is settled by the consultation instead.
        $this->assertTrue($record->is_open);
    }

    public function test_admin_can_mark_non_service_sanction_completed_without_proof(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'One month suspension');
        $this->createConfirmedViolation('2024-0001', 1);

        $record = $this->createNonServiceRecord('2024-0001');

        // No proof exists, yet the admin can close the record after the
        // student's personal consultation.
        $this->actingAs($admin, 'admin')
            ->post(route('admin.sanctions.records.complete', $record))
            ->assertSessionHas('success');

        $record->refresh();
        $this->assertSame('Completed', $record->status);
        $this->assertNotNull($record->date_completed);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Mark Completed',
            'module' => 'Sanction Records',
        ]);

        $this->assertDatabaseHas('notifications_tbl', [
            'student_number' => '2024-0001',
            'notification_type' => 'sanction',
        ]);
    }

    public function test_service_sanction_still_cannot_be_completed_without_proof(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');
        $this->createConfirmedViolation('2024-0001', 1);

        $record = $this->createServiceRecord('2024-0001', 'Office of Student Affairs', '2026-09-15');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.sanctions.records.complete', $record))
            ->assertSessionHasErrors('sanction_record');

        $record->refresh();
        $this->assertSame('In Progress', $record->status);
        $this->assertNull($record->date_completed);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function createNonServiceRecord(string $studentNumber): StudentSanctionRecord
    {
        return StudentSanctionRecord::create([
            'student_number' => $studentNumber,
            'violation_id' => 1,
            'assigned_sanction_id' => 1,
            'status' => 'In Progress',
            'date_assigned' => now(),
        ]);
    }

    private function createServiceRecord(string $studentNumber, string $office, string $start): StudentSanctionRecord
    {
        return StudentSanctionRecord::create([
            'student_number' => $studentNumber,
            'violation_id' => 1,
            'assigned_sanction_id' => 1,
            'status' => 'In Progress',
            'date_assigned' => now(),
            'designated_office' => $office,
            'starting_date' => $start,
        ]);
    }

    private function createSanctionRequest(string $studentNumber, int $violationTypeId): SanctionRequest
    {
        return SanctionRequest::create([
            'student_number' => $studentNumber,
            'violation_type_id' => $violationTypeId,
            'request_date' => now(),
            'is_active' => true,
            'status' => 'Pending',
        ]);
    }

    private function createConfirmedViolation(string $studentNumber, int $violationTypeId): Violation
    {
        return Violation::create([
            'student_number' => $studentNumber,
            'violation_type' => $violationTypeId,
            'violation_date' => now(),
            'description' => 'Confirmed violation.',
            'recorder_type' => 'admin',
            'recorder_name' => 'Admin One',
            'status' => 'confirmed',
        ]);
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

    private function createTables(): void
    {
        foreach ([
            'admins' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('username')->nullable();
                $table->string('email');
                $table->string('password');
                $table->boolean('mfa_totp_enabled')->default(false);
            },
            'users_tbl' => function (Blueprint $table): void {
                $table->increments('user_id');
                $table->string('student_number')->nullable();
                $table->string('first_name')->nullable();
                $table->string('middle_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->string('password_hash')->nullable();
                $table->unsignedInteger('roles_id')->nullable();
                $table->unsignedInteger('status_id')->nullable();
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
                $table->text('notes')->nullable();
                $table->string('status', 20)->default('submitted');
                $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_remarks')->nullable();
                $table->timestamp('submitted_at')->nullable();
            },
            'notifications_tbl' => function (Blueprint $table): void {
                $table->increments('notification_id');
                $table->string('student_number')->nullable();
                $table->string('recipient_type', 20)->nullable();
                $table->unsignedBigInteger('recipient_id')->nullable();
                $table->string('notification_type', 50)->nullable();
                $table->string('title')->nullable();
                $table->text('message')->nullable();
                $table->string('target_type', 50)->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            },
            'admin_notifications_tbl' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('type', 50)->nullable();
                $table->string('title')->nullable();
                $table->text('message')->nullable();
                $table->string('target_type', 50)->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->unsignedBigInteger('recipient_id')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            },
            'audit_logs' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('actor_type', 20)->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('action', 100)->nullable();
                $table->string('module', 100)->nullable();
                $table->text('description')->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->timestamp('created_at')->nullable();
            },
            'evidence_security_events_tbl' => function (Blueprint $table): void {
                $table->increments('event_id');
                $table->string('event_type', 50)->nullable();
                $table->unsignedBigInteger('violation_id')->nullable();
                $table->unsignedBigInteger('evidence_id')->nullable();
                $table->string('severity', 20)->nullable();
                $table->string('actor_type', 20)->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->text('context')->nullable();
                $table->timestamp('created_at')->nullable();
            },
        ] as $name => $definition) {
            if (Schema::hasTable($name)) {
                continue;
            }
            Schema::create($name, $definition);
        }
    }
}
