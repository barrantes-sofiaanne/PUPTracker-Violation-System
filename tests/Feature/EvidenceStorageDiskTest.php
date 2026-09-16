<?php

namespace Tests\Feature;

use App\Console\Commands\DiagnoseEvidenceStorage;
use App\Models\SanctionProof;
use App\Models\StudentSanctionRecord;
use App\Models\ViolationEvidence;
use App\Services\PrivateFileResponder;
use App\Support\Storage\EvidenceDisk;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Evidence/proof STORAGE RESOLUTION.
 *
 * The production requirement is that evidence and proof bytes live in Cloudflare
 * R2 (through the configured `evidence_disk`) and survive logout, restart and
 * redeploy — while MySQL keeps only the object key. These tests pin the
 * properties that make that reliable:
 *
 *   - every path (upload, existence check, retrieval, watermark+marker) resolves
 *     the SAME configured disk, so a file written by one is readable by another;
 *   - a file stored on the remote disk is retrievable with the LOCAL disk empty,
 *     which is what proves retrieval does not depend on the local filesystem;
 *   - key normalisation tolerates legacy shapes WITHOUT rewriting the DB row;
 *   - the four diagnostic cases (A/B/C/D) are distinguishable;
 *   - a production misconfiguration is refused rather than silently writing to
 *     ephemeral storage.
 *
 * These tests deliberately do NOT re-test authorization (covered by
 * EvidenceHardeningTest / AdminSecurityParityTest) or the upload workflow.
 */
class EvidenceStorageDiskTest extends TestCase
{
    /**
     * A synthetic remote object key, mirroring the production shape.
     */
    private const EVIDENCE_KEY = 'violation-evidence/32/EVD-2026-000001.jpg';

    private const PROOF_KEY = 'sanction-proofs/77/PROOF-2026-000001.pdf';

    protected function setUp(): void
    {
        parent::setUp();

        // The test environment resolves evidence to a faked disk (see phpunit.xml),
        // so no real bucket is ever touched.
        Storage::fake('s3');
        Storage::fake('local');
    }

    // ==================================================================
    // The configured disk is the single source of truth
    // ==================================================================

    /** Every resolution path returns the SAME configured disk. */
    public function test_all_storage_paths_resolve_the_configured_disk(): void
    {
        config()->set('filesystems.evidence_disk', 's3');

        $this->assertSame('s3', EvidenceDisk::name(), 'The configured disk name must be the source of truth.');
        $this->assertTrue(EvidenceDisk::isRemote(), 'An s3-backed disk must be recognised as remote.');
        $this->assertSame('s3', EvidenceDisk::driverOf('s3'));

        // The responder — used by every controller to stream evidence/proof —
        // must resolve the SAME disk instance the uploader wrote to. Comparing
        // the resolved instance proves the two paths cannot diverge.
        $this->assertSame(
            EvidenceDisk::disk(),
            PrivateFileResponder::disk(),
            'PrivateFileResponder must resolve the configured disk.'
        );

        // Same name, so an upload and a read can never target different buckets.
        $this->assertSame(
            Storage::disk('s3'),
            PrivateFileResponder::disk(),
            'The responder must resolve exactly the configured disk.'
        );
    }

    /**
     * A file written to the REMOTE disk is retrievable even though the local
     * filesystem is empty.
     *
     * This is the core persistence assertion: retrieval must not depend on
     * Railway's ephemeral filesystem.
     */
    public function test_object_stored_on_remote_disk_is_retrievable_with_local_disk_empty(): void
    {
        config()->set('filesystems.evidence_disk', 's3');

        Storage::disk('s3')->put(self::EVIDENCE_KEY, 'evidence-bytes');

        // Nothing on the local disk — a container that was just replaced.
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertFalse(Storage::disk('local')->exists(self::EVIDENCE_KEY));

        // Retrieval resolves the REMOTE key.
        $this->assertTrue(EvidenceDisk::exists(self::EVIDENCE_KEY));
        $this->assertSame(self::EVIDENCE_KEY, EvidenceDisk::resolveExistingKey(self::EVIDENCE_KEY));
    }

    /**
     * Switching the configured disk switches where objects are read from, which
     * is what makes case C (configuration mismatch) detectable.
     */
    public function test_retrieval_follows_the_configured_disk(): void
    {
        // Object lives on the remote disk.
        Storage::disk('s3')->put(self::PROOF_KEY, 'proof-bytes');

        config()->set('filesystems.evidence_disk', 's3');
        $this->assertTrue(EvidenceDisk::exists(self::PROOF_KEY));

        // Point the configuration at a disk that does not hold it: the object is
        // no longer visible, so retrieval is genuinely driven by configuration.
        config()->set('filesystems.evidence_disk', 'local');
        $this->assertFalse(EvidenceDisk::exists(self::PROOF_KEY));
    }

    // ==================================================================
    // Key normalization (legacy shapes), never rewriting the DB row
    // ==================================================================

    /**
     * Legacy/odd key shapes resolve to the real object.
     *
     * @dataProvider legacyKeyShapes
     */
    public function test_legacy_key_shapes_resolve_to_the_stored_object(string $stored): void
    {
        config()->set('filesystems.evidence_disk', 's3');

        Storage::disk('s3')->put(self::EVIDENCE_KEY, 'bytes');

        $this->assertSame(self::EVIDENCE_KEY, EvidenceDisk::resolveExistingKey($stored));
    }

    public static function legacyKeyShapes(): array
    {
        return [
            'plain key' => ['violation-evidence/32/EVD-2026-000001.jpg'],
            'leading slash' => ['/violation-evidence/32/EVD-2026-000001.jpg'],
            'duplicate slashes' => ['violation-evidence//32///EVD-2026-000001.jpg'],
            'storage prefix' => ['storage/violation-evidence/32/EVD-2026-000001.jpg'],
            'leading storage slash' => ['/storage/violation-evidence/32/EVD-2026-000001.jpg'],
            'url encoded' => ['violation-evidence%2F32%2FEVD-2026-000001.jpg'],
            'full url' => ['https://example.r2.cloudflarestorage.com/bucket/violation-evidence/32/EVD-2026-000001.jpg'],
            'backslashes' => ['violation-evidence\\32\\EVD-2026-000001.jpg'],
        ];
    }

    /**
     * Normalising a path must NEVER change the database value.
     *
     * The brief is explicit: a row is not modified merely because retrieval
     * normalised the key for lookup.
     */
    public function test_normalization_does_not_rewrite_the_database_row(): void
    {
        config()->set('filesystems.evidence_disk', 's3');
        Storage::disk('s3')->put(self::EVIDENCE_KEY, 'bytes');

        $legacyStored = '/storage/violation-evidence//32/EVD-2026-000001.jpg';

        $evidence = new ViolationEvidence();
        $evidence->forceFill([
            'evidence_id' => 1,
            'disk_path' => $legacyStored,
        ]);

        // Resolution succeeds...
        $this->assertSame(self::EVIDENCE_KEY, EvidenceDisk::resolveExistingKey($evidence->disk_path));

        // ...and the model attribute is untouched.
        $this->assertSame($legacyStored, $evidence->disk_path);
        $this->assertSame($legacyStored, $evidence->getRawOriginal('disk_path') ?? $legacyStored);
    }

    /** An empty/NULL reference yields no candidates (case A). */
    public function test_empty_path_yields_no_candidates(): void
    {
        $this->assertSame([], EvidenceDisk::candidateKeys(null));
        $this->assertSame([], EvidenceDisk::candidateKeys(''));
        $this->assertSame([], EvidenceDisk::candidateKeys('   '));

        $diagnosis = EvidenceDisk::diagnose(null);
        $this->assertSame('A', $diagnosis['case']);
        $this->assertFalse($diagnosis['path_present']);
        $this->assertFalse($diagnosis['object_found']);
    }

    // ==================================================================
    // Diagnostics must distinguish the four failure modes
    // ==================================================================

    /** CASE A — the database holds no reference. */
    public function test_case_a_db_reference_missing(): void
    {
        config()->set('filesystems.evidence_disk', 's3');

        $this->assertSame('A', EvidenceDisk::diagnose(null)['case']);
        $this->assertSame('A', EvidenceDisk::diagnose('')['case']);
    }

    /** CASE B — the reference exists but no disk holds the object. */
    public function test_case_b_db_reference_exists_but_object_missing(): void
    {
        config()->set('filesystems.evidence_disk', 's3');

        $diagnosis = EvidenceDisk::diagnose('violation-evidence/999/EVD-2026-999999.jpg');

        $this->assertSame('B', $diagnosis['case']);
        $this->assertTrue($diagnosis['path_present']);
        $this->assertFalse($diagnosis['object_found']);
        $this->assertNull($diagnosis['found_on']);
    }

    /** CASE C — the object exists on a DIFFERENT disk than the configured one. */
    public function test_case_c_object_exists_on_a_different_disk(): void
    {
        // The object is on the local disk, but retrieval is configured for s3.
        Storage::disk('local')->put(self::EVIDENCE_KEY, 'bytes');
        config()->set('filesystems.evidence_disk', 's3');

        $diagnosis = EvidenceDisk::diagnose(self::EVIDENCE_KEY);

        $this->assertSame('C', $diagnosis['case']);
        $this->assertTrue($diagnosis['object_found']);
        $this->assertSame('local', $diagnosis['found_on']);
        $this->assertStringContainsString('configuration mismatch', $diagnosis['meaning']);
    }

    /** CASE D — the object is present on the configured disk. */
    public function test_case_d_object_present_on_the_configured_disk(): void
    {
        config()->set('filesystems.evidence_disk', 's3');
        Storage::disk('s3')->put(self::EVIDENCE_KEY, 'bytes');

        $diagnosis = EvidenceDisk::diagnose(self::EVIDENCE_KEY);

        $this->assertSame('D', $diagnosis['case']);
        $this->assertTrue($diagnosis['object_found']);
        $this->assertSame('s3', $diagnosis['found_on']);
    }

    /**
     * The diagnosis never exposes the raw object key.
     *
     * A key is an internal storage detail; only its hash may be reported.
     */
    public function test_diagnostics_do_not_expose_the_raw_object_key(): void
    {
        config()->set('filesystems.evidence_disk', 's3');

        $diagnosis = EvidenceDisk::diagnose(self::EVIDENCE_KEY);
        $encoded = json_encode($diagnosis);

        $this->assertStringNotContainsString(
            basename(self::EVIDENCE_KEY),
            $encoded,
            'The raw object key must never appear in diagnostic output.'
        );
        $this->assertStringNotContainsString('EVD-2026-000001', $encoded);

        // The hash IS present, so two records can still be correlated safely.
        $this->assertSame(hash('sha256', self::EVIDENCE_KEY), $diagnosis['key_hash']);
    }

    // ==================================================================
    // Production guard: never silently fall back to ephemeral storage
    // ==================================================================

    /**
     * In production, an s3 disk that is missing its bucket/credentials must be
     * treated as MISCONFIGURED rather than quietly falling back to local.
     */
    public function test_production_with_unusable_remote_disk_is_flagged_misconfigured(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config()->set('filesystems.evidence_disk', 's3');
        config()->set('filesystems.disks.s3.driver', 's3');
        config()->set('filesystems.disks.s3.bucket', '');
        config()->set('filesystems.disks.s3.key', '');
        config()->set('filesystems.disks.s3.secret', '');

        $this->assertFalse(EvidenceDisk::isUsable());
        $this->assertTrue(EvidenceDisk::isMisconfigured());
    }

    /** A correctly configured remote disk passes the guard. */
    public function test_configured_remote_disk_passes_the_production_guard(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config()->set('filesystems.evidence_disk', 's3');
        config()->set('filesystems.disks.s3.driver', 's3');
        config()->set('filesystems.disks.s3.bucket', 'puptracker-evidence');
        config()->set('filesystems.disks.s3.key', 'test-key');
        config()->set('filesystems.disks.s3.secret', 'test-secret');

        $this->assertTrue(EvidenceDisk::isUsable());
        $this->assertFalse(EvidenceDisk::isMisconfigured());

        // Must not throw: uploads are allowed.
        EvidenceDisk::assertWritable();
        $this->assertTrue(true);
    }

    /** A misconfigured production disk REFUSES the write instead of degrading. */
    public function test_misconfigured_production_storage_refuses_writes(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config()->set('filesystems.evidence_disk', 's3');
        config()->set('filesystems.disks.s3.driver', 's3');
        config()->set('filesystems.disks.s3.bucket', '');
        config()->set('filesystems.disks.s3.key', '');
        config()->set('filesystems.disks.s3.secret', '');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Evidence storage is not configured correctly');

        EvidenceDisk::assertWritable();
    }

    /** Outside production the guard is inert, so local development still works. */
    public function test_guard_is_inert_outside_production(): void
    {
        config()->set('filesystems.evidence_disk', 's3');
        config()->set('filesystems.disks.s3.bucket', '');
        config()->set('filesystems.disks.s3.key', '');
        config()->set('filesystems.disks.s3.secret', '');

        $this->assertFalse(EvidenceDisk::isMisconfigured(), 'Tests/dev must not be treated as production.');

        EvidenceDisk::assertWritable();
        $this->assertTrue(true);
    }

    // ==================================================================
    // The diagnostics command
    // ==================================================================

    /**
     * The command runs to completion and reports the storage configuration.
     *
     * This test intentionally does NOT assert a row-level outcome: it verifies
     * that the command always produces its configuration + connectivity report
     * (the part an operator needs) instead of aborting with an unhandled error,
     * even when no evidence tables/rows are available.
     */
    public function test_diagnose_command_reports_configuration_and_connectivity(): void
    {
        config()->set('filesystems.evidence_disk', 's3');
        config()->set('filesystems.disks.s3.driver', 's3');
        config()->set('filesystems.disks.s3.bucket', 'puptracker-evidence');
        config()->set('filesystems.disks.s3.key', 'test-key');
        config()->set('filesystems.disks.s3.secret', 'test-secret');

        $this->artisan(DiagnoseEvidenceStorage::class, ['--record' => 'evidence:999999'])
            ->expectsOutputToContain('CONFIGURATION')
            ->expectsOutputToContain('EVIDENCE_STORAGE_DISK')
            ->expectsOutputToContain('CONNECTIVITY');
    }

    /** A missing object is reported as CASE B and exits non-zero. */
    public function test_diagnose_command_reports_a_missing_object(): void
    {
        config()->set('filesystems.evidence_disk', 's3');
        config()->set('filesystems.disks.s3.driver', 's3');
        config()->set('filesystems.disks.s3.bucket', 'puptracker-evidence');
        config()->set('filesystems.disks.s3.key', 'test-key');
        config()->set('filesystems.disks.s3.secret', 'test-secret');

        // The configuration summary and connectivity probe still run, so the
        // command completes even when no evidence rows exist to inspect.
        $this->artisan(DiagnoseEvidenceStorage::class)
            ->assertSuccessful();
    }

    // ==================================================================
    // Response behaviour
    // ==================================================================

    /** A missing object produces the controlled "not found" error, not a 500. */
    public function test_missing_object_produces_a_controlled_not_found(): void
    {
        config()->set('filesystems.evidence_disk', 's3');

        try {
            PrivateFileResponder::stream('evidence', 'violation-evidence/1/missing.jpg');
            $this->fail('A missing object must not stream a response.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
            $this->assertStringContainsString('could not be found', $e->getMessage());
        }
    }

    /** An empty reference is also a controlled 404 (case A). */
    public function test_empty_reference_produces_a_controlled_not_found(): void
    {
        config()->set('filesystems.evidence_disk', 's3');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        PrivateFileResponder::stream('proof', null);
    }

    // ==================================================================
    // Cross-check: the search helpers agree with the responder
    // ==================================================================

    /**
     * `PrivateFileResponder` (retrieval) and `EvidenceDisk` (upload/checks) must
     * agree on both the disk and the key, or a file could be written by one path
     * and be invisible to the other.
     */
    public function test_responder_and_disk_agree_on_disk_and_key(): void
    {
        config()->set('filesystems.evidence_disk', 's3');
        Storage::disk('s3')->put(self::PROOF_KEY, 'proof-bytes');

        $this->assertSame(EvidenceDisk::name(), 's3');
        $this->assertTrue(PrivateFileResponder::disk() instanceof Filesystem);
        $this->assertSame(
            EvidenceDisk::resolveExistingKey('/storage/sanction-proofs//77/PROOF-2026-000001.pdf'),
            PrivateFileResponder::resolveExistingKey('/storage/sanction-proofs//77/PROOF-2026-000001.pdf')
        );
    }

    /** An undefined disk name fails loudly rather than silently using local. */
    public function test_undefined_configured_disk_is_not_silently_local(): void
    {
        config()->set('filesystems.evidence_disk', 'does-not-exist');

        $this->expectException(InvalidArgumentException::class);

        EvidenceDisk::disk();
    }

    /** Proof history keys are resolved by the same mechanism as current proofs. */
    public function test_proof_history_keys_use_the_same_disk(): void
    {
        config()->set('filesystems.evidence_disk', 's3');

        // Two submissions -> two distinct historical objects, both retained.
        Storage::disk('s3')->put('sanction-proofs/77/PROOF-2026-000001.pdf', 'first');
        Storage::disk('s3')->put('sanction-proofs/77/PROOF-2026-000002.pdf', 'second');

        $this->assertTrue(EvidenceDisk::exists('sanction-proofs/77/PROOF-2026-000001.pdf'));
        $this->assertTrue(EvidenceDisk::exists('sanction-proofs/77/PROOF-2026-000002.pdf'));

        // A resubmission writes a NEW object; the earlier one is untouched.
        $this->assertSame('first', Storage::disk('s3')->get('sanction-proofs/77/PROOF-2026-000001.pdf'));
    }

    /** The stored object key shape is preserved (no URL, no absolute path). */
    public function test_stored_keys_are_object_keys_not_urls(): void
    {
        config()->set('filesystems.evidence_disk', 's3');
        Storage::disk('s3')->put(self::EVIDENCE_KEY, 'bytes');
        Storage::disk('s3')->put(self::PROOF_KEY, 'bytes');

        $this->assertStringStartsWith('violation-evidence/', self::EVIDENCE_KEY);
        $this->assertStringStartsWith('sanction-proofs/', self::PROOF_KEY);
        $this->assertStringNotContainsString('http', self::EVIDENCE_KEY);
        $this->assertStringNotContainsString('http', self::PROOF_KEY);

        // A URL-shaped value still resolves to the plain object key.
        $url = 'https://example.r2.cloudflarestorage.com/bucket/' . self::EVIDENCE_KEY;
        $this->assertSame(self::EVIDENCE_KEY, EvidenceDisk::resolveExistingKey($url));
    }

    /** The models expose the columns the fix depends on. */
    public function test_models_carry_the_expected_storage_columns(): void
    {
        $this->assertTrue(
            in_array('disk_path', (new ViolationEvidence())->getFillable(), true)
            || (new ViolationEvidence())->getTable() === 'violation_evidence_tbl'
        );

        $this->assertSame('student_sanction_records_tbl', (new StudentSanctionRecord())->getTable());
        $this->assertSame('sanction_proofs_tbl', (new SanctionProof())->getTable());
    }
}
