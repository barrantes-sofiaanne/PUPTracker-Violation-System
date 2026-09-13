<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DisciplinarySanction;
use App\Models\EvidenceSecurityEvent;
use App\Models\SanctionProof;
use App\Models\Security;
use App\Models\StudentSanctionRecord;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationEvidence;
use App\Models\ViolationType;
use App\Services\EvidenceWatermarkService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Evidence + sanction-proof hardening tests.
 *
 * Covers the minimum-change improvements layered on the existing workflow:
 *   1. Server-side watermark (GD) bakes the watermark into image evidence and
 *      records server_watermarked + sha256_hash; documents are untouched.
 *   2. camera/upload evidence stores a unique EVD code and sha256.
 *   3. capture_session_id is persisted on the evidence row + correlated events.
 *   4. Sanction proof validation rejects DOC/GIF (JPG/JPEG/PNG/PDF only).
 *   5. Sanction proof stores PROOF code + sha256 + student_number and preserves
 *      the original bytes (never watermarked).
 *   6. Students can only view their OWN confirmed violation evidence.
 *   7. Students cannot create/replace/delete violation evidence (no routes),
 *      and a foreign student is 403.
 *
 * All values are synthetic test data.
 */
class EvidenceHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
    }

    public function test_security_camera_evidence_is_server_watermarked_with_sha256(): void
    {
        $security = $this->createSecurity('security@example.com', 'Officer', 'One');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');

        Storage::fake('local');
        $file = UploadedFile::fake()->image('photo.jpg', 320, 240);

        $this->actingAs($security, 'security')
            ->post(route('security.violations.store'), [
                'student_number' => '2024-0001',
                'violation_type_id' => 1,
                'violation_date' => now()->format('Y-m-d H:i:s'),
                'description' => 'Caught using a phone.',
                'evidence' => [$file],
                'capture_methods' => ['camera'],
                'captured_ats' => [now()->toIso8601String()],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $evidence = ViolationEvidence::firstOrFail();
        $this->assertMatchesRegularExpression('/^EVD-\d{4}-\d{6}$/', $evidence->evidence_code);
        $this->assertSame('camera', $evidence->capture_method);
        // The server baked the watermark (GD) — never trusts the client flag.
        $this->assertTrue((bool) $evidence->server_watermarked);
        $this->assertTrue((bool) $evidence->watermarked);
        $this->assertSame(64, strlen((string) $evidence->sha256_hash));

        // The stored bytes are a re-encoded image whose SHA-256 matches the DB.
        $disk = Storage::disk('local');
        $this->assertTrue($disk->exists($evidence->disk_path));
        $this->assertSame($evidence->sha256_hash, hash('sha256', (string) $disk->get($evidence->disk_path)));

        // The stored file is NOT byte-identical to the original fake upload
        // (the server re-encoded + watermarked it).
        $this->assertNotSame(hash('sha256', $file->getContent()), $evidence->sha256_hash);

        // Evidence uploaded event references the actor.
        $this->assertDatabaseHas('evidence_security_events_tbl', [
            'evidence_id' => $evidence->evidence_id,
            'event_type' => EvidenceSecurityEvent::EVIDENCE_UPLOADED,
            'actor_type' => 'security',
            'actor_id' => $security->id,
        ]);
    }

    public function test_pdf_evidence_is_preserved_byte_for_byte_with_sha256_and_not_watermarked(): void
    {
        $security = $this->createSecurity('security@example.com', 'Officer', 'One');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');

        Storage::fake('local');
        $file = UploadedFile::fake()->create('statement.pdf', 120, 'application/pdf');

        $this->actingAs($security, 'security')
            ->post(route('security.violations.store'), [
                'student_number' => '2024-0001',
                'violation_type_id' => 1,
                'violation_date' => now()->format('Y-m-d H:i:s'),
                'description' => 'Caught with printed notes.',
                'evidence' => [$file],
                'capture_methods' => ['upload'],
            ])
            ->assertOk();

        $evidence = ViolationEvidence::firstOrFail();
        $this->assertSame('upload', $evidence->capture_method);
        $this->assertFalse((bool) $evidence->server_watermarked);
        $this->assertSame(64, strlen((string) $evidence->sha256_hash));

        // PDF original bytes are preserved exactly (documents are never
        // re-encoded / watermarked).
        $disk = Storage::disk('local');
        $this->assertSame($evidence->sha256_hash, hash('sha256', $file->getContent()));
        $this->assertSame($evidence->sha256_hash, hash('sha256', (string) $disk->get($evidence->disk_path)));
    }

    public function test_capture_session_id_is_persisted_and_correlated_on_evidence(): void
    {
        $security = $this->createSecurity('security@example.com', 'Officer', 'One');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');

        Storage::fake('local');
        $file = UploadedFile::fake()->image('photo.jpg', 320, 240);
        $session = 'CAM-SESSION-2026-000123';

        $this->actingAs($security, 'security')
            ->post(route('security.violations.store'), [
                'student_number' => '2024-0001',
                'violation_type_id' => 1,
                'violation_date' => now()->format('Y-m-d H:i:s'),
                'description' => 'Caught with a phone.',
                'evidence' => [$file],
                'capture_methods' => ['camera'],
                'captured_ats' => [now()->toIso8601String()],
                'capture_session_id' => $session,
            ])
            ->assertOk();

        $evidence = ViolationEvidence::firstOrFail();
        $this->assertSame($session, $evidence->capture_session_id);

        // The EVIDENCE_UPLOADED event carries the same session id.
        $this->assertDatabaseHas('evidence_security_events_tbl', [
            'evidence_id' => $evidence->evidence_id,
            'capture_session_id' => $session,
            'event_type' => EvidenceSecurityEvent::EVIDENCE_UPLOADED,
        ]);
    }

    public function test_security_event_endpoint_persists_capture_session_id(): void
    {
        $security = $this->createSecurity('security@example.com', 'Officer', 'One');
        $session = 'CAM-SESSION-2026-000456';

        $this->actingAs($security, 'security')
            ->postJson(route('security.violations.evidence-security-event'), [
                'event_type' => EvidenceSecurityEvent::PAGE_BLURRED,
                'severity' => 'info',
                'capture_session_id' => $session,
            ])
            ->assertOk();

        $this->assertDatabaseHas('evidence_security_events_tbl', [
            'event_type' => EvidenceSecurityEvent::PAGE_BLURRED,
            'capture_session_id' => $session,
            'actor_type' => 'security',
            'actor_id' => $security->id,
        ]);
    }

    public function test_admin_camera_evidence_is_server_watermarked(): void
    {
        $admin = $this->createAdmin('admin@example.com');
        $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');

        Storage::fake('local');
        $file = UploadedFile::fake()->image('photo.jpg', 320, 240);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.violations.store'), [
                'student_number' => '2024-0001',
                'violation_type_id' => 1,
                'violation_date' => now()->format('Y-m-d H:i:s'),
                'description' => 'Caught cheating (admin).',
                'evidence' => [$file],
                'capture_methods' => ['camera'],
            ])
            ->assertOk();

        $evidence = ViolationEvidence::firstOrFail();
        $this->assertTrue((bool) $evidence->server_watermarked);
        $this->assertSame(64, strlen((string) $evidence->sha256_hash));
        $this->assertDatabaseHas('evidence_security_events_tbl', [
            'evidence_id' => $evidence->evidence_id,
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_student_proof_rejects_doc_and_gif(): void
    {
        $admin = $this->createAdmin('admin@example.com');
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');

        $violation = Violation::create([
            'student_number' => '2024-0001',
            'violation_type' => 1,
            'violation_date' => now(),
            'description' => 'Confirmed violation.',
            'recorder_type' => 'admin',
            'recorder_name' => 'Admin One',
            'status' => 'confirmed',
        ]);

        $record = StudentSanctionRecord::create([
            'student_number' => '2024-0001',
            'violation_id' => $violation->violation_id,
            'assigned_sanction_id' => 1,
            'assigned_by_admin_id' => $admin->id,
            'status' => StudentSanctionRecord::STATUS_IN_PROGRESS,
            'deadline_date' => now()->addMonth(),
            'date_assigned' => now(),
        ]);

        // DOC rejected.
        $doc = UploadedFile::fake()->create('proof.doc', 50, 'application/msword');
        $this->actingAs($student, 'student')
            ->postJson(route('student.submit-proof'), [
                'record_id' => $record->record_id,
                'proof_file' => $doc,
            ])
            ->assertStatus(422);

        // GIF rejected.
        $gif = UploadedFile::fake()->create('proof.gif', 50, 'image/gif');
        $this->actingAs($student, 'student')
            ->postJson(route('student.submit-proof'), [
                'record_id' => $record->record_id,
                'proof_file' => $gif,
            ])
            ->assertStatus(422);

        // No proof row was created by the rejected attempts.
        $this->assertSame(0, SanctionProof::count());
    }

    public function test_student_jpg_proof_stores_proof_code_sha256_and_preserves_original(): void
    {
        $admin = $this->createAdmin('admin@example.com');
        $student = $this->createStudent('2024-0001');
        $this->createViolationType(1, 'Cheating');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 40 hours');

        $violation = Violation::create([
            'student_number' => '2024-0001',
            'violation_type' => 1,
            'violation_date' => now(),
            'description' => 'Confirmed violation.',
            'recorder_type' => 'admin',
            'recorder_name' => 'Admin One',
            'status' => 'confirmed',
        ]);

        $record = StudentSanctionRecord::create([
            'student_number' => '2024-0001',
            'violation_id' => $violation->violation_id,
            'assigned_sanction_id' => 1,
            'assigned_by_admin_id' => $admin->id,
            'status' => StudentSanctionRecord::STATUS_IN_PROGRESS,
            'deadline_date' => now()->addMonth(),
            'date_assigned' => now(),
        ]);

        Storage::fake('local');
        $file = UploadedFile::fake()->image('signed-form.jpg', 640, 480);
        $originalSha = hash('sha256', $file->getContent());

        $this->actingAs($student, 'student')
            ->post(route('student.submit-proof'), [
                'record_id' => $record->record_id,
                'proof_file' => $file,
                'proof_notes' => 'Signed by office head.',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        // The original signed form is preserved byte-for-byte (never
        // re-encoded/watermarked) and carries PROOF code + SHA-256.
        $proof = SanctionProof::firstOrFail();
        $this->assertMatchesRegularExpression('/^PROOF-\d{4}-\d{6}$/', $proof->proof_code);
        $this->assertSame('2024-0001', $proof->student_number);
        $this->assertSame($originalSha, $proof->sha256_hash);

        $disk = Storage::disk('local');
        $this->assertTrue($disk->exists($proof->disk_path));
        $this->assertSame($originalSha, hash('sha256', (string) $disk->get($proof->disk_path)));

        // PROOF_SUBMITTED event recorded.
        $this->assertDatabaseHas('evidence_security_events_tbl', [
            'event_type' => EvidenceSecurityEvent::PROOF_SUBMITTED,
            'actor_type' => 'student',
        ]);
    }

    public function test_foreign_student_cannot_view_evidence_and_owner_only_after_confirm(): void
    {
        $security = $this->createSecurity('security@example.com', 'Officer', 'One');
        $owner = $this->createStudent('2024-0001');
        $this->createStudent('2024-0002');
        $this->createViolationType(1, 'Cheating');

        Storage::fake('local');
        Storage::disk('local')->put('violation-evidence/1/EVD-2026-000001.jpg', 'img');

        $confirmed = Violation::create([
            'student_number' => '2024-0001',
            'violation_type' => 1,
            'violation_date' => now(),
            'description' => 'Confirmed.',
            'recorder_type' => 'security',
            'recorder_name' => 'Officer One',
            'status' => 'confirmed',
        ]);
        $evidence = ViolationEvidence::create([
            'violation_id' => $confirmed->violation_id,
            'disk_path' => 'violation-evidence/1/EVD-2026-000001.jpg',
            'original_name' => 'evidence.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 100,
        ]);

        // Owner may open their confirmed evidence.
        $this->actingAs($owner, 'student')
            ->get(route('student.violations.evidence', $evidence))
            ->assertOk();

        // Foreign student → 403.
        $foreign = User::where('student_number', '2024-0002')->firstOrFail();
        $this->actingAs($foreign, 'student')
            ->get(route('student.violations.evidence', $evidence))
            ->assertForbidden();

        // STUDENT_EVIDENCE_VIEWED audit event recorded for the owner access.
        $this->assertDatabaseHas('evidence_security_events_tbl', [
            'event_type' => EvidenceSecurityEvent::STUDENT_EVIDENCE_VIEWED,
            'actor_type' => 'student',
        ]);
    }

    public function test_watermark_service_bakes_and_marks_when_gd_present(): void
    {
        $service = app(EvidenceWatermarkService::class);
        $bytes = $this->makeJpeg(320, 240);

        $result = $service->watermarkImage($bytes, 'EVD-2026-000001', '2024-0001', '2026-09-09 12:00:00');

        if (function_exists('imagecreatefromstring')) {
            $this->assertNotNull($result);
            $img = @imagecreatefromstring($result);
            $this->assertNotFalse($img);
            imagedestroy($img);
        } else {
            $this->assertNull($result);
        }
    }

    public function test_watermark_service_rejects_non_image_input(): void
    {
        $service = app(EvidenceWatermarkService::class);
        $this->assertNull($service->watermarkImage('this is not an image', 'EVD-2026-000001', '2024-0001', null));
    }

    // ------------------------------------------------------------------
    // Submit Proof of Completion modal (student record page)
    // ------------------------------------------------------------------

    private function createOpenSanctionRecordFor(string $studentNumber, string $violationLabel = 'Cheating', string $sanctionText = 'Community service 40 hours'): StudentSanctionRecord
    {
        $admin = $this->createAdmin('admin-modal@example.com');
        $this->createViolationType(1, $violationLabel);
        $this->createDisciplinarySanction(1, '1st Offense', $sanctionText);

        $violation = Violation::create([
            'student_number' => $studentNumber,
            'violation_type' => 1,
            'violation_date' => now(),
            'description' => 'Confirmed violation.',
            'recorder_type' => 'admin',
            'recorder_name' => 'Admin One',
            'status' => 'confirmed',
        ]);

        return StudentSanctionRecord::create([
            'student_number' => $studentNumber,
            'violation_id' => $violation->violation_id,
            'assigned_sanction_id' => 1,
            'assigned_by_admin_id' => $admin->id,
            'status' => StudentSanctionRecord::STATUS_IN_PROGRESS,
            'deadline_date' => now()->addMonth(),
            'date_assigned' => now(),
        ]);
    }

    public function test_record_page_renders_general_submit_proof_modal_with_sanction_details(): void
    {
        $this->createStudent('2024-0001');
        $record = $this->createOpenSanctionRecordFor('2024-0001');

        $content = $this->actingAs(User::where('student_number', '2024-0001')->first(), 'student')
            ->get(route('student.record'))
            ->assertOk()
            ->getContent();

        // General title + subtitle (NOT Community-Service-specific).
        $this->assertStringContainsString('Submit Proof of Completion', $content);
        $this->assertStringContainsString('Upload the official document that verifies completion of your assigned sanction.', $content);

        // Read-only sanction-details section labels.
        $this->assertStringContainsString('Sanction Details', $content);
        $this->assertStringContainsString('Violation Type', $content);
        $this->assertStringContainsString('Assigned Sanction', $content);
        $this->assertStringContainsString('Deadline', $content);
        $this->assertStringContainsString('Reference', $content);

        // The button carries the real, server-side display data.
        $this->assertStringContainsString('data-record-id="' . $record->record_id . '"', $content);
        $this->assertStringContainsString('data-sanction="Community service 40 hours"', $content);
        $this->assertStringContainsString('data-record-ref="Record #' . $record->record_id . '"', $content);
        $this->assertStringContainsString('data-violation="Cheating"', $content);
        // This test sanction is Community Service, so the community-service
        // flag must be set on the trigger button.
        $this->assertStringContainsString('data-community-service="1"', $content);

        // General instructions + sections.
        $this->assertStringContainsString('Prepare Your Proof', $content);
        $this->assertStringContainsString('Upload Proof', $content);
        $this->assertStringContainsString('Upload the document that verifies your completion of the assigned sanction.', $content);
        $this->assertStringContainsString('Submission Guidelines', $content);
        $this->assertStringContainsString('Additional Remarks (Optional)', $content);

        // The removed "Do not upload violation evidence" line must NOT appear.
        $this->assertStringNotContainsString('Do not upload violation evidence', $content);
    }

    public function test_record_page_modal_shows_community_service_official_form_link_only_for_community_service(): void
    {
        $this->createStudent('2024-0001');
        $this->createOpenSanctionRecordFor('2024-0001');

        $content = $this->actingAs(User::where('student_number', '2024-0001')->first(), 'student')
            ->get(route('student.record'))
            ->assertOk()
            ->getContent();

        // The official form link (Google Docs) is present in the modal DOM and
        // is shown only when the assigned sanction is community service.
        $this->assertStringContainsString('officialFormArea', $content);
        $this->assertStringContainsString('Need the official form?', $content);
        $this->assertStringContainsString('https://docs.google.com/document/d/1STRQ6AwNaSgHIjqt7vbxNFF7h_50xj84/edit', $content);
        $this->assertStringContainsString('data-community-service="1"', $content);
    }

    public function test_record_page_modal_shows_official_form_link_for_campus_service(): void
    {
        $this->createStudent('2024-0001');
        $this->createOpenSanctionRecordFor('2024-0001', 'Campus violation', 'Campus service 10 hours');

        $content = $this->actingAs(User::where('student_number', '2024-0001')->first(), 'student')
            ->get(route('student.record'))
            ->assertOk()
            ->getContent();

        // Campus Service sanctions must also receive the official form link.
        $this->assertStringContainsString('data-sanction="Campus service 10 hours"', $content);
        $this->assertStringContainsString('data-community-service="1"', $content);
        $this->assertStringContainsString('https://docs.google.com/document/d/1STRQ6AwNaSgHIjqt7vbxNFF7h_50xj84/edit', $content);
    }

    public function test_record_page_modal_has_confirmation_checkbox_and_review_notice_and_disabled_submit(): void
    {
        $this->createStudent('2024-0001');
        $this->createOpenSanctionRecordFor('2024-0001');

        $content = $this->actingAs(User::where('student_number', '2024-0001')->first(), 'student')
            ->get(route('student.record'))
            ->assertOk()
            ->getContent();

        // Required confirmation checkbox.
        $this->assertStringContainsString('id="proofConfirmCheck"', $content);
        $this->assertStringContainsString('true and complete proof of my sanction completion', $content);

        // Review-required messaging + no auto-completion note.
        $this->assertStringContainsString('Review Required', $content);
        $this->assertStringContainsString('Uploading a document does not automatically mark your sanction as completed.', $content);

        // Submit button renders disabled until a file + checkbox are provided
        // (client-side UX only — server still enforces everything).
        $this->assertStringContainsString('id="submitProofBtn" disabled', $content);

        // Accepted file types.
        $this->assertStringContainsString('accept=".jpg,.jpeg,.png,.pdf"', $content);
        $this->assertStringContainsString('JPG, JPEG, PNG, PDF • Maximum 5 MB', $content);
    }

    public function test_record_page_modal_does_not_hardcode_community_service_wording(): void
    {
        $this->createStudent('2024-0001');
        // A non-Community-Service sanction (counseling) to prove the modal is
        // general and the CS form link is NOT forced on for every sanction.
        $record = $this->createOpenSanctionRecordFor('2024-0001', 'Counseling referral', 'Counseling sessions');

        $content = $this->actingAs(User::where('student_number', '2024-0001')->first(), 'student')
            ->get(route('student.record'))
            ->assertOk()
            ->getContent();

        // The general modal must NOT be Community-Service-specific.
        $this->assertStringNotContainsString('Community Service Completion Form', $content);
        $this->assertStringNotContainsString('Sign your Community Service', $content);
        $this->assertStringNotContainsString('Do not upload violation evidence', $content);

        // A counseling sanction is NOT Community/Campus Service, so it takes no
        // proof of completion: the student is instead directed to the Office of
        // Student Services for a personal consultation, and no upload trigger
        // is rendered for that record.
        $this->assertStringNotContainsString('data-record-id="' . $record->record_id . '"', $content);
        $this->assertStringContainsString('Please proceed to the Office of Student Services', $content);
    }

    // ------------------------------------------------------------------
    // Student record: Summary by Violation behavior
    // ------------------------------------------------------------------

    /**
     * Create a violation type + disciplinary sanction config + a violation row
     * with the given status, WITHOUT creating any sanction record (so the type
     * is eligible for the Summary table when it should be).
     */
    private function createConfiguredViolation(
        string $studentNumber,
        int $typeId,
        string $violationLabel,
        string $sanctionText,
        string $violationStatus,
        string $description = 'Test violation',
    ): Violation {
        $this->createViolationType($typeId, $violationLabel);
        $this->createDisciplinarySanction($typeId, '1st Offense', $sanctionText);

        return Violation::create([
            'student_number' => $studentNumber,
            'violation_type' => $typeId,
            'violation_date' => now(),
            'description' => $description,
            'recorder_type' => 'admin',
            'recorder_name' => 'Admin One',
            'status' => $violationStatus,
        ]);
    }

    public function test_student_summary_shows_pending_violation_awaiting_confirmation(): void
    {
        $this->createStudent('2024-0001');
        // Sanctionable type with a PENDING (awaiting admin confirmation) violation.
        $this->createConfiguredViolation('2024-0001', 1, 'Cheating', 'Community service 40 hours', 'pending', 'Awaiting admin decision');

        $content = $this->actingAs(User::where('student_number', '2024-0001')->first(), 'student')
            ->get(route('student.record'))
            ->assertOk()
            ->getContent();

        // Pending rows appear in the Summary with a Pending Confirmation badge,
        // and no "Request Sanction" action is offered (not confirmed yet).
        $this->assertStringContainsString('Pending Confirmation', $content);
        $this->assertStringContainsString('Awaiting admin decision', $content);
        $this->assertStringNotContainsString('request-sanction-btn', $content);
    }

    public function test_student_summary_excludes_warning_only_violation(): void
    {
        $this->createStudent('2024-0001');
        // Warning-only type (sanction text contains "warning") → excluded from Summary.
        $this->createConfiguredViolation('2024-0001', 1, 'Littering', 'Warning', 'confirmed', 'Littered in hallway');

        $content = $this->actingAs(User::where('student_number', '2024-0001')->first(), 'student')
            ->get(route('student.record'))
            ->assertOk()
            ->getContent();

        // The warning-only type is excluded from the Summary table entirely, so
        // the summary is empty ("No outstanding violations found") and no
        // "No Request Needed" / request action is rendered for it.
        $this->assertStringContainsString('No outstanding violations found.', $content);
        $this->assertStringNotContainsString('request-sanction-btn', $content);
        $this->assertStringNotContainsString('No Request Needed', $content);
        // ...but the warning violation IS still listed in the Individual
        // Violations Log (now labelled "Confirmed").
        $this->assertStringContainsString('Littering', $content);
        $this->assertStringContainsString('Littered in hallway', $content);
        $this->assertStringContainsString('Confirmed', $content);
    }

    public function test_student_summary_removes_completed_sanction_violation(): void
    {
        $this->createStudent('2024-0001');
        $admin = $this->createAdmin('admin-complete@example.com');
        $this->createViolationType(1, 'Vandalism');
        $this->createDisciplinarySanction(1, '1st Offense', 'Community service 20 hours');

        $violation = Violation::create([
            'student_number' => '2024-0001',
            'violation_type' => 1,
            'violation_date' => now(),
            'description' => 'Vandalism resolved',
            'recorder_type' => 'admin',
            'recorder_name' => 'Admin One',
            'status' => 'confirmed',
        ]);

        // Completed sanction record.
        StudentSanctionRecord::create([
            'student_number' => '2024-0001',
            'violation_id' => $violation->violation_id,
            'assigned_sanction_id' => 1,
            'assigned_by_admin_id' => $admin->id,
            'status' => StudentSanctionRecord::STATUS_COMPLETED,
            'date_assigned' => now()->subMonth(),
            'date_completed' => now(),
        ]);

        $content = $this->actingAs(User::where('student_number', '2024-0001')->first(), 'student')
            ->get(route('student.record'))
            ->assertOk()
            ->getContent();

        // Completed sanction → the violation type is removed from the Summary
        // (summary is empty), no request action is offered, and the completed
        // sanction remains visible in the Sanction Record tab. The violation
        // itself stays in the Individual Violations Log.
        $this->assertStringContainsString('No outstanding violations found.', $content);
        $this->assertStringNotContainsString('request-sanction-btn', $content);
        $this->assertStringContainsString('Your Sanction Records', $content);
        $this->assertStringContainsString('Vandalism', $content);
    }

    public function test_student_summary_shows_actionable_confirmed_sanctionable_violation(): void
    {
        $this->createStudent('2024-0001');
        $this->createConfiguredViolation('2024-0001', 1, 'Cheating', 'Community service 40 hours', 'confirmed', 'Caught cheating');

        $content = $this->actingAs(User::where('student_number', '2024-0001')->first(), 'student')
            ->get(route('student.record'))
            ->assertOk()
            ->getContent();

        // Confirmed sanctionable violation → appears in Summary + Request Sanction offered.
        $this->assertStringContainsString('Caught cheating', $content);
        $this->assertStringContainsString('request-sanction-btn', $content);
        $this->assertStringContainsString('Request Sanction', $content);
    }

    public function test_student_log_includes_pending_and_confirmed_records(): void
    {
        $this->createStudent('2024-0001');
        $this->createConfiguredViolation('2024-0001', 1, 'Cheating', 'Community service 40 hours', 'pending', 'Pending record desc');
        $this->createConfiguredViolation('2024-0001', 2, 'Fighting', 'Suspension', 'confirmed', 'Confirmed record desc');

        $content = $this->actingAs(User::where('student_number', '2024-0001')->first(), 'student')
            ->get(route('student.record'))
            ->assertOk()
            ->getContent();

        // Both pending and confirmed records are listed in the Individual
        // Violations Log with their statuses. A confirmed (non-pending) row is
        // labelled "Confirmed".
        $this->assertStringContainsString('Pending record desc', $content);
        $this->assertStringContainsString('Confirmed record desc', $content);
        $this->assertStringContainsString('Pending Confirmation', $content);
        $this->assertStringContainsString('Confirmed', $content);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function makeJpeg(int $w, int $h): string
    {
        $im = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($im, 255, 255, 255);
        imagefilledrectangle($im, 0, 0, $w - 1, $h - 1, $white);
        ob_start();
        imagejpeg($im, null, 90);
        $out = (string) ob_get_clean();
        imagedestroy($im);

        return $out;
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

        return $security;
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
            'violation_evidence_tbl' => function (Blueprint $table): void {
                $table->increments('evidence_id');
                $table->string('evidence_code', 32)->nullable();
                $table->string('capture_session_id', 64)->nullable();
                $table->unsignedBigInteger('violation_id')->nullable();
                $table->string('disk_path', 500)->nullable();
                $table->string('original_name', 255)->nullable();
                $table->string('mime_type', 120)->nullable();
                $table->string('capture_method', 20)->nullable()->default('upload');
                $table->boolean('watermarked')->default(false);
                $table->boolean('server_watermarked')->default(false);
                $table->timestamp('captured_at')->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->char('sha256_hash', 64)->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->string('uploader_type', 20)->nullable();
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
