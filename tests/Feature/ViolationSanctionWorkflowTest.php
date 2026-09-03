<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\DisciplinarySanction;
use App\Models\Notification;
use App\Models\SanctionRequest;
use App\Models\Security;
use App\Models\StudentSanctionRecord;
use App\Models\Violation;
use App\Models\ViolationType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * End-to-end coverage for:
 *   1. Security records a violation → status 'pending' + admin notified.
 *   2. Admin confirms → violation becomes effective + security/student notified.
 *   3. Admin denies (with reason) → denied + excluded from effective counts.
 *   4. Student requests sanction → admin approves with deadline.
 *   5. Student submits proof of completion → admin notified.
 *   6. Admin accepts proof → record Completed + student notified.
 *   7. Admin rejects proof (with reason) → record stays Pending, student can resubmit.
 */
class ViolationSanctionWorkflowTest extends TestCase
{
    public function test_security_recorded_violation_is_pending_and_notifies_admin(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');
        $security = $this->createSecurity('security@example.com', 'Officer', 'One');
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');

        $this->actingAs($security, 'security')
            ->postJson(route('security.violations.store'), [
                'student_number' => '2024-0001',
                'violation_type_id' => 1,
                'violation_date' => now()->format('Y-m-d H:i:s'),
                'description' => 'Caught using a phone.',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $violation = Violation::where('student_number', '2024-0001')->first();
        $this->assertNotNull($violation);
        $this->assertSame('pending', $violation->status);

        $this->assertDatabaseHas('admin_notifications_tbl', [
            'notification_type' => 'violation_confirmation',
            'target_type' => 'violation',
            'target_id' => $violation->violation_id,
        ]);

        // Student is NOT notified before the admin confirms.
        $this->assertDatabaseMissing('notifications_tbl', [
            'student_number' => '2024-0001',
            'notification_type' => 'violation',
        ]);
    }

    public function test_security_recorded_violation_stores_evidence_privately(): void
    {
        $this->createTables();
        $security = $this->createSecurity('security@example.com', 'Officer', 'One');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');

        \Illuminate\Support\Facades\Storage::fake('local');

        $this->actingAs($security, 'security')
            ->post(route('security.violations.store'), [
                'student_number' => '2024-0001',
                'violation_type_id' => 1,
                'violation_date' => now()->format('Y-m-d H:i:s'),
                'description' => 'Caught using a phone.',
                'evidence' => [
                    \Illuminate\Http\UploadedFile::fake()->image('incident.jpg'),
                    \Illuminate\Http\UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf'),
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $violation = Violation::where('student_number', '2024-0001')->first();
        $this->assertNotNull($violation);

        $this->assertDatabaseHas('violation_evidence_tbl', [
            'violation_id' => $violation->violation_id,
            'original_name' => 'incident.jpg',
        ]);
        $this->assertDatabaseHas('violation_evidence_tbl', [
            'violation_id' => $violation->violation_id,
            'original_name' => 'doc.pdf',
        ]);

        $this->assertSame(2, $violation->evidence()->count());
    }

    public function test_overdue_sweep_flips_past_deadline_to_overdue(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');

        $violation = Violation::create([
            'student_number' => '2024-0001',
            'violation_type' => 1,
            'violation_date' => now()->subMonth(),
            'description' => 'Caught cheating.',
            'recorder_type' => 'admin',
            'recorder_name' => 'Admin One',
            'status' => 'confirmed',
        ]);

        $record = StudentSanctionRecord::create([
            'student_number' => '2024-0001',
            'violation_id' => $violation->violation_id,
            'assigned_sanction_id' => 1,
            'assigned_by_admin_id' => $admin->id,
            'status' => \App\Models\StudentSanctionRecord::STATUS_IN_PROGRESS,
            'deadline_date' => now()->subDays(3),
            'date_assigned' => now()->subMonth(),
        ]);

        $flipped = app(\App\Services\SanctionOverdueService::class)->sweep();

        $this->assertSame(1, $flipped);
        $this->assertSame(\App\Models\StudentSanctionRecord::STATUS_OVERDUE, $record->fresh()->status);

        // Student + admin notified once.
        $this->assertDatabaseHas('notifications_tbl', [
            'student_number' => '2024-0001',
            'title' => 'Sanction Overdue',
        ]);
        $this->assertDatabaseHas('admin_notifications_tbl', [
            'notification_type' => 'sanction_overdue',
            'target_id' => $record->record_id,
        ]);

        // Idempotent: second sweep does not re-notify.
        app(\App\Services\SanctionOverdueService::class)->sweep();
        $this->assertSame(
            1,
            \App\Models\Notification::where('student_number', '2024-0001')
                ->where('title', 'Sanction Overdue')->count()
        );
    }

    public function test_student_can_only_open_evidence_of_confirmed_owned_violation(): void
    {
        $this->createTables();
        $security = $this->createSecurity('security@example.com', 'Officer', 'One');
        $student = $this->createStudent('2024-0001');
        $this->createStudent('2024-0002');
        $this->createViolationType(1, 'Cheating');

        \Illuminate\Support\Facades\Storage::fake('local');
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        $disk->put('violation-evidence/1/EVD-2026-000001.jpg', 'fake-image-bytes');

        // Pending violation with evidence → owner cannot open yet.
        $pending = Violation::create([
            'student_number' => '2024-0001',
            'violation_type' => 1,
            'violation_date' => now(),
            'description' => 'Caught cheating.',
            'recorder_type' => 'security',
            'recorder_name' => 'Officer One',
            'status' => 'pending',
        ]);
        $pendingEvidence = \App\Models\ViolationEvidence::create([
            'violation_id' => $pending->violation_id,
            'disk_path' => 'violation-evidence/1/EVD-2026-000001.jpg',
            'original_name' => 'incident.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 100,
            'uploader_type' => 'security',
            'uploader_id' => $security->id,
        ]);

        $this->actingAs($student, 'student')
            ->get(route('student.violations.evidence', $pendingEvidence))
            ->assertForbidden();

        // Confirmed violation with evidence → owning student may open it.
        $confirmed = Violation::create([
            'student_number' => '2024-0001',
            'violation_type' => 1,
            'violation_date' => now(),
            'description' => 'Caught cheating.',
            'recorder_type' => 'security',
            'recorder_name' => 'Officer One',
            'status' => 'confirmed',
        ]);
        $confirmedEvidence = \App\Models\ViolationEvidence::create([
            'violation_id' => $confirmed->violation_id,
            'disk_path' => 'violation-evidence/1/EVD-2026-000001.jpg',
            'original_name' => 'incident.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 100,
            'uploader_type' => 'security',
            'uploader_id' => $security->id,
        ]);

        $this->actingAs($student, 'student')
            ->get(route('student.violations.evidence', $confirmedEvidence))
            ->assertOk();

        // A DIFFERENT student may NOT open evidence of someone else's violation.
        $other = \App\Models\User::where('student_number', '2024-0002')->first();
        $this->actingAs($other, 'student')
            ->get(route('student.violations.evidence', $confirmedEvidence))
            ->assertForbidden();
    }

    public function test_security_recorded_camera_evidence_gets_unique_code_and_metadata(): void
    {
        $this->createTables();
        $security = $this->createSecurity('security@example.com', 'Officer', 'One');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');

        \Illuminate\Support\Facades\Storage::fake('local');

        $this->actingAs($security, 'security')
            ->post(route('security.violations.store'), [
                'student_number' => '2024-0001',
                'violation_type_id' => 1,
                'violation_date' => now()->format('Y-m-d H:i:s'),
                'description' => 'Caught using a phone.',
                'evidence' => [
                    \Illuminate\Http\UploadedFile::fake()->image('photo.jpg'),
                ],
                'capture_methods' => ['camera'],
                'captured_ats' => [now()->toIso8601String()],
                'watermarked' => ['1'],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $evidence = \App\Models\ViolationEvidence::first();
        $this->assertNotNull($evidence);
        $this->assertNotNull($evidence->evidence_code);
        $this->assertMatchesRegularExpression('/^EVD-\d{4}-\d{6}$/', $evidence->evidence_code);
        $this->assertSame('camera', $evidence->capture_method);
        $this->assertTrue((bool) $evidence->watermarked);
        $this->assertNotNull($evidence->captured_at);
        $this->assertNotNull($evidence->ip_address);
        $this->assertNotNull($evidence->user_agent);
        // Disk path is derived from the evidence code (server-side), not the original name.
        $this->assertStringContainsString($evidence->evidence_code, $evidence->disk_path);

        // Uploaded event logged.
        $this->assertDatabaseHas('evidence_security_events_tbl', [
            'evidence_id' => $evidence->evidence_id,
            'event_type' => \App\Models\EvidenceSecurityEvent::EVIDENCE_UPLOADED,
            'actor_type' => 'security',
            'actor_id' => $security->id,
        ]);
    }

    public function test_security_event_endpoint_records_whitelisted_events_only(): void
    {
        $this->createTables();
        $security = $this->createSecurity('security@example.com', 'Officer', 'One');

        // Whitelisted event is recorded.
        $this->actingAs($security, 'security')
            ->postJson(route('security.violations.evidence-security-event'), [
                'event_type' => \App\Models\EvidenceSecurityEvent::CAMERA_PERMISSION_DENIED,
                'severity' => 'warning',
                'meta' => ['reason' => 'denied'],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('evidence_security_events_tbl', [
            'event_type' => \App\Models\EvidenceSecurityEvent::CAMERA_PERMISSION_DENIED,
            'severity' => 'warning',
            'actor_type' => 'security',
            'actor_id' => $security->id,
        ]);

        // Non-whitelisted events (e.g. a fake "SCREENSHOT_CONFIRMED") are rejected.
        $this->actingAs($security, 'security')
            ->postJson(route('security.violations.evidence-security-event'), [
                'event_type' => 'SCREENSHOT_CONFIRMED',
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('evidence_security_events_tbl', [
            'event_type' => 'SCREENSHOT_CONFIRMED',
        ]);
    }

    public function test_student_sanction_request_does_not_create_record_before_approval(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');

        // A confirmed violation exists so the request is valid.
        Violation::create([
            'student_number' => '2024-0001',
            'violation_type' => 1,
            'violation_date' => now(),
            'description' => 'Caught cheating.',
            'recorder_type' => 'admin',
            'recorder_name' => 'Admin One',
            'status' => 'confirmed',
        ]);

        $this->actingAs($student, 'student')
            ->postJson(route('student.request-sanction'), [
                'violation_type_id' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        // A pending SanctionRequest exists...
        $this->assertDatabaseHas('sanction_requests_tbl', [
            'student_number' => '2024-0001',
            'is_active' => 1,
        ]);

        // ...but NO StudentSanctionRecord is created before admin approval.
        $this->assertSame(0, \App\Models\StudentSanctionRecord::where('student_number', '2024-0001')->count());
    }

    public function test_admin_confirms_violation_and_it_becomes_effective(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');
        $security = $this->createSecurity('security@example.com', 'Officer', 'One');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');

        $violation = Violation::create([
            'student_number' => '2024-0001',
            'violation_type' => 1,
            'violation_date' => now(),
            'description' => 'Caught cheating.',
            'recorder_type' => 'security',
            'recorder_name' => 'Officer One',
            'status' => 'pending',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.violations.confirm', $violation))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('violation_tbl', [
            'violation_id' => $violation->violation_id,
            'status' => 'confirmed',
            'reviewed_by_admin_id' => $admin->id,
        ]);

        // Security + student notified.
        $this->assertDatabaseHas('notifications_tbl', [
            'recipient_type' => 'security',
            'target_type' => 'violation',
            'target_id' => $violation->violation_id,
        ]);
        $this->assertDatabaseHas('notifications_tbl', [
            'student_number' => '2024-0001',
            'notification_type' => 'violation',
            'target_id' => $violation->violation_id,
        ]);
    }

    public function test_admin_denies_violation_with_reason_and_effective_scope_excludes_it(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');
        $security = $this->createSecurity('security@example.com', 'Officer', 'One');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');

        $violation = Violation::create([
            'student_number' => '2024-0001',
            'violation_type' => 1,
            'violation_date' => now(),
            'description' => 'Caught cheating.',
            'recorder_type' => 'security',
            'recorder_name' => 'Officer One',
            'status' => 'pending',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.violations.deny', $violation), ['reason' => 'No evidence.'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('violation_tbl', [
            'violation_id' => $violation->violation_id,
            'status' => 'denied',
            'review_remarks' => 'No evidence.',
        ]);

        // The denied row is not counted as an effective violation.
        $this->assertSame(0, Violation::effective()->where('student_number', '2024-0001')->count());

        // Security notified with the reason.
        $this->assertDatabaseHas('notifications_tbl', [
            'recipient_type' => 'security',
            'notification_type' => 'violation',
            'target_id' => $violation->violation_id,
        ]);
    }

    public function test_deny_violation_requires_a_reason(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');

        $violation = Violation::create([
            'student_number' => '2024-0001',
            'violation_type' => 1,
            'violation_date' => now(),
            'description' => 'Caught cheating.',
            'recorder_type' => 'security',
            'recorder_name' => 'Officer One',
            'status' => 'pending',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.violations.deny', $violation), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseHas('violation_tbl', [
            'violation_id' => $violation->violation_id,
            'status' => 'pending',
        ]);
    }

    public function test_approve_sanction_request_sets_deadline_and_notifies_student(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');

        // A confirmed violation exists for this student.
        $violation = Violation::create([
            'student_number' => '2024-0001',
            'violation_type' => 1,
            'violation_date' => now(),
            'description' => 'Caught cheating.',
            'recorder_type' => 'admin',
            'recorder_name' => 'Admin One',
            'status' => 'confirmed',
        ]);

        $sanctionRequest = SanctionRequest::create([
            'student_number' => '2024-0001',
            'violation_type_id' => 1,
            'request_date' => now(),
            'is_active' => true,
            'status' => 'Pending',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.sanctions.requests.approve', $sanctionRequest), [
                'notification_date' => now()->format('Y-m-d'),
                'deadline_date' => now()->addMonth()->format('Y-m-d'),
            ])
            ->assertSessionHas('success');

        $record = StudentSanctionRecord::where('student_number', '2024-0001')->first();
        $this->assertNotNull($record);
        $this->assertNotNull($record->deadline_date);

        $this->assertDatabaseHas('notifications_tbl', [
            'student_number' => '2024-0001',
            'notification_type' => 'sanction',
        ]);
    }

    public function test_student_submits_proof_admin_accepts_and_record_completes(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');

        $violation = Violation::create([
            'student_number' => '2024-0001',
            'violation_type' => 1,
            'violation_date' => now(),
            'description' => 'Caught cheating.',
            'recorder_type' => 'admin',
            'recorder_name' => 'Admin One',
            'status' => 'confirmed',
        ]);

        $record = StudentSanctionRecord::create([
            'student_number' => '2024-0001',
            'violation_id' => $violation->violation_id,
            'assigned_sanction_id' => 1,
            'assigned_by_admin_id' => $admin->id,
            'status' => 'Pending',
            'deadline_date' => now()->addMonth(),
            'date_assigned' => now(),
        ]);

        $file = UploadedFile::fake()->create('proof.pdf', 50, 'application/pdf');

        $this->actingAs($student, 'student')
            ->post(route('student.submit-proof'), [
                'record_id' => $record->record_id,
                'proof_file' => $file,
                'proof_notes' => 'Completed my community service.',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $record->refresh();
        $this->assertNotNull($record->proof_path);
        $this->assertNotNull($record->proof_submitted_at);
        // Submitting a proof on a Pending (unassigned) sanction activates it.
        $this->assertSame('In Progress', $record->status);

        // Admin notified of the proof submission.
        $this->assertDatabaseHas('admin_notifications_tbl', [
            'notification_type' => 'completion_proof',
            'target_type' => 'sanction_record',
            'target_id' => $record->record_id,
        ]);

        // Admin accepts the proof.
        $this->actingAs($admin, 'admin')
            ->post(route('admin.sanctions.records.complete', $record))
            ->assertSessionHas('success');

        $record->refresh();
        $this->assertSame('Completed', $record->status);
        $this->assertNotNull($record->date_completed);

        // The accepted submission is retained in the proof history.
        $this->assertDatabaseHas('sanction_proofs_tbl', [
            'record_id' => $record->record_id,
            'status' => 'accepted',
        ]);

        $this->assertDatabaseHas('notifications_tbl', [
            'student_number' => '2024-0001',
            'notification_type' => 'sanction',
        ]);
    }

    public function test_admin_rejects_proof_with_reason_and_student_can_resubmit(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');

        $violation = Violation::create([
            'student_number' => '2024-0001',
            'violation_type' => 1,
            'violation_date' => now(),
            'description' => 'Caught cheating.',
            'recorder_type' => 'admin',
            'recorder_name' => 'Admin One',
            'status' => 'confirmed',
        ]);

        $record = StudentSanctionRecord::create([
            'student_number' => '2024-0001',
            'violation_id' => $violation->violation_id,
            'assigned_sanction_id' => 1,
            'assigned_by_admin_id' => $admin->id,
            'status' => 'Pending',
            'deadline_date' => now()->addMonth(),
            'date_assigned' => now(),
        ]);

        // Student submits a proof.
        $file = UploadedFile::fake()->create('proof.pdf', 50, 'application/pdf');
        $this->actingAs($student, 'student')
            ->post(route('student.submit-proof'), [
                'record_id' => $record->record_id,
                'proof_file' => $file,
                'proof_notes' => 'Completed.',
            ])
            ->assertOk();

        $record->refresh();
        $this->assertNotNull($record->proof_path);

        // Admin rejects with reason.
        $this->actingAs($admin, 'admin')
            ->post(route('admin.sanctions.records.reject-proof', $record), ['reason' => 'Incomplete proof.'])
            ->assertSessionHas('success');

        $record->refresh();
        // The sanction was already active (In Progress after proof submission),
        // so rejecting the proof keeps it In Progress for a resubmission.
        $this->assertSame('In Progress', $record->status);
        $this->assertSame('Incomplete proof.', $record->completion_remarks);

        // The current-proof pointers must be cleared so the student can submit
        // a NEW proof and the admin no longer sees the old file as pending.
        $this->assertNull($record->proof_path);
        $this->assertNull($record->proof_original_name);
        $this->assertNull($record->proof_submitted_at);
        $this->assertFalse($record->has_pending_proof);
        $this->assertTrue($record->is_open);

        // The rejected submission is retained in the proof history.
        $this->assertDatabaseHas('sanction_proofs_tbl', [
            'record_id' => $record->record_id,
            'status' => 'rejected',
            'review_remarks' => 'Incomplete proof.',
        ]);

        $this->assertDatabaseHas('notifications_tbl', [
            'student_number' => '2024-0001',
            'notification_type' => 'sanction',
        ]);

        // The student may now resubmit a fresh proof (record is open and no
        // pending proof is blocking the submission).
        $file2 = UploadedFile::fake()->create('proof2.pdf', 60, 'application/pdf');
        $this->actingAs($student, 'student')
            ->post(route('student.submit-proof'), [
                'record_id' => $record->record_id,
                'proof_file' => $file2,
                'proof_notes' => 'Resubmitted after rejection.',
            ])
            ->assertOk();

        $record->refresh();
        $this->assertNotNull($record->proof_path);
        $this->assertTrue($record->has_pending_proof);
        $this->assertSame('proof2.pdf', $record->proof_original_name);
    }

    public function test_reject_proof_requires_reason(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');

        $violation = Violation::create([
            'student_number' => '2024-0001',
            'violation_type' => 1,
            'violation_date' => now(),
            'description' => 'Caught cheating.',
            'recorder_type' => 'admin',
            'recorder_name' => 'Admin One',
            'status' => 'confirmed',
        ]);

        $record = StudentSanctionRecord::create([
            'student_number' => '2024-0001',
            'violation_id' => $violation->violation_id,
            'assigned_sanction_id' => 1,
            'assigned_by_admin_id' => $admin->id,
            'status' => 'Pending',
            'date_assigned' => now(),
        ]);

        $file = UploadedFile::fake()->create('proof.pdf', 50, 'application/pdf');
        $this->actingAs($student, 'student')
            ->post(route('student.submit-proof'), [
                'record_id' => $record->record_id,
                'proof_file' => $file,
            ])
            ->assertOk();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.sanctions.records.reject-proof', $record), ['reason' => ''])
            ->assertSessionHasErrors('reason');
    }

    public function test_admin_violation_confirmation_notification_opens_confirmation_queue(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');

        $violation = Violation::create([
            'student_number' => '2024-0001',
            'violation_type' => 1,
            'violation_date' => now(),
            'description' => 'Caught cheating.',
            'recorder_type' => 'security',
            'recorder_name' => 'Officer One',
            'status' => 'pending',
        ]);

        $notification = AdminNotification::create([
            'title' => 'Violation Awaiting Confirmation',
            'message' => 'A violation awaits confirmation.',
            'notification_type' => 'violation_confirmation',
            'target_type' => 'violation',
            'target_id' => $violation->violation_id,
            'recipient_type' => 'admin',
            'recipient_id' => $admin->id,
            'is_read' => false,
            'created_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.notifications.open', $notification))
            ->assertRedirectContains('violations/confirmations');

        $this->assertDatabaseHas('admin_notifications_tbl', [
            'admin_notification_id' => $notification->admin_notification_id,
            'is_read' => true,
        ]);
    }

    public function test_security_violation_notification_opens_student_violation_page(): void
    {
        $this->createTables();
        $admin = $this->createAdmin('admin@example.com');
        $security = $this->createSecurity('security@example.com', 'Officer', 'One');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');

        $violation = Violation::create([
            'student_number' => '2024-0001',
            'violation_type' => 1,
            'violation_date' => now(),
            'description' => 'Caught cheating.',
            'recorder_type' => 'security',
            'recorder_name' => 'Officer One',
            'status' => 'denied',
        ]);

        $notification = Notification::create([
            'student_number' => '',
            'message' => 'The violation you recorded was denied.',
            'title' => 'Violation Rejected',
            'notification_type' => 'violation',
            'recipient_type' => 'security',
            'target_type' => 'violation',
            'target_id' => $violation->violation_id,
            'is_read' => false,
            'created_at' => now(),
        ]);

        $this->actingAs($security, 'security')
            ->get(route('security.notifications.open', $notification))
            ->assertRedirectContains('security/violations/student/2024-0001');

        $this->assertDatabaseHas('notifications_tbl', [
            'notification_id' => $notification->notification_id,
            'is_read' => true,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Setup helpers (mirror the in-memory sqlite schema of other feature tests)
    |--------------------------------------------------------------------------
    */

    private function createAdmin(string $email): Admin
    {
        return Admin::create([
            'username' => $email,
            'email' => $email,
            'password' => Hash::make('Password123!'),
            'mfa_totp_enabled' => 0,
        ]);
    }

    private function createSecurity(string $email, string $first, string $last): Security
    {
        $security = Security::create([
            'email' => $email,
            'password' => Hash::make('Password123!'),
            'mfa_totp_enabled' => 0,
        ]);

        DB::table('security_info_tbl')->insert([
            'security_id' => $security->id,
            'firstname' => $first,
            'lastname' => $last,
        ]);
        DB::table('security_info')->insert([
            'security_id' => $security->id,
            'firstname' => $first,
            'lastname' => $last,
        ]);

        return $security;
    }

    private function createStudent(string $studentNumber)
    {
        return \App\Models\User::create([
            'student_number' => $studentNumber,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => $studentNumber . '@example.com',
            'password_hash' => Hash::make('Password123!'),
            'roles_id' => 2,
            'status_id' => 1,
        ]);
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
            'security' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('email');
                $table->string('password');
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
                $table->unsignedBigInteger('record_id')->nullable();
                $table->string('disk_path', 500)->nullable();
                $table->string('original_name', 255)->nullable();
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 20)->default('submitted');
                $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_remarks')->nullable();
                $table->timestamp('submitted_at')->nullable();
            },
            'violation_evidence_tbl' => function (Blueprint $table): void {
                $table->increments('evidence_id');
                $table->string('evidence_code', 32)->nullable();
                $table->unsignedBigInteger('violation_id')->nullable();
                $table->string('disk_path', 500)->nullable();
                $table->string('original_name', 255)->nullable();
                $table->string('mime_type', 120)->nullable();
                $table->string('capture_method', 20)->nullable()->default('upload');
                $table->boolean('watermarked')->default(false);
                $table->timestamp('captured_at')->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->string('uploader_type', 20)->nullable();
                $table->unsignedBigInteger('uploader_id')->nullable();
                $table->timestamp('created_at')->nullable();
            },
            'evidence_security_events_tbl' => function (Blueprint $table): void {
                $table->increments('event_id');
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
            'admin_notifications_tbl' => function (Blueprint $table): void {
                $table->increments('admin_notification_id');
                $table->text('message');
                $table->string('title')->nullable();
                $table->string('notification_type')->nullable();
                $table->string('target_type')->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->string('recipient_type')->nullable();
                $table->unsignedBigInteger('recipient_id')->nullable();
                $table->boolean('is_read')->default(false);
                $table->dateTime('created_at')->nullable();
            },
            'notifications_tbl' => function (Blueprint $table): void {
                $table->increments('notification_id');
                $table->string('student_number')->nullable();
                $table->text('message');
                $table->string('title')->nullable();
                $table->string('notification_type')->nullable();
                $table->string('recipient_type')->nullable();
                $table->string('target_type')->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->string('link')->nullable();
                $table->boolean('is_read')->default(false);
                $table->dateTime('created_at')->nullable();
            },
            'audit_logs' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('actor_type')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('action')->nullable();
                $table->string('module')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            },
        ] as $tableName => $closure) {
            if (! Schema::hasTable($tableName)) {
                Schema::create($tableName, $closure);
            }
        }

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
            ]);
        }

        if (DB::table('violation_category_tbl')->count() === 0) {
            DB::table('violation_category_tbl')->insert([
                ['violation_category_id' => 1, 'category_name' => 'Major'],
            ]);
        }
    }
}
