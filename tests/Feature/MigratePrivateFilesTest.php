<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase as BaseTestCase;

/**
 * Exercises storage:migrate-private-files.
 *
 * The command copies confidential files (violation evidence, sanction proofs,
 * announcement attachments) from a legacy/local disk to a private
 * S3-compatible disk. In tests we use two real local disks (source + target)
 * so no network/R2 credentials are required, and we verify:
 *   - files are copied to the target at the SAME relative path,
 *   - DB storage paths are preserved,
 *   - source originals are NOT deleted,
 *   - the command is safely re-runnable (skips already-migrated objects).
 */
class MigratePrivateFilesTest extends BaseTestCase
{
    private string $sourceRoot = '';

    private string $targetRoot = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();

        $this->sourceRoot = sys_get_temp_dir() . '/migrate_src_' . uniqid();
        $this->targetRoot = sys_get_temp_dir() . '/migrate_tgt_' . uniqid();

        config()->set('filesystems.disks.migrate_src', [
            'driver' => 'local',
            'root' => $this->sourceRoot,
        ]);
        config()->set('filesystems.disks.migrate_tgt', [
            'driver' => 'local',
            'root' => $this->targetRoot,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ([
            'violation_evidence_tbl',
            'student_sanction_records_tbl',
            'sanction_proofs_tbl',
            'announcement_attachments',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        foreach ([$this->sourceRoot ?? null, $this->targetRoot ?? null] as $dir) {
            if ($dir && is_dir($dir)) {
                $this->rrmdir($dir);
            }
        }

        parent::tearDown();
    }

    public function test_migrates_evidence_proofs_and_attachments_to_target_disk(): void
    {
        $source = Storage::disk('migrate_src');
        $target = Storage::disk('migrate_tgt');

        // Seed source objects + DB rows.
        $source->put('violation-evidence/1/EVD-2026-000001.jpg', 'evidence-bytes');
        DB::table('violation_evidence_tbl')->insert([
            'evidence_id' => 1,
            'violation_id' => 1,
            'disk_path' => 'violation-evidence/1/EVD-2026-000001.jpg',
            'evidence_code' => 'EVD-2026-000001',
            'original_name' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'capture_method' => 'camera',
        ]);

        $source->put('sanction-proofs/5/proof.pdf', 'proof-bytes');
        DB::table('student_sanction_records_tbl')->insert([
            'record_id' => 5,
            'student_number' => '2024-0001',
            'proof_path' => 'sanction-proofs/5/proof.pdf',
            'proof_original_name' => 'proof.pdf',
        ]);
        $source->put('sanction-proofs/5/history.pdf', 'history-bytes');
        DB::table('sanction_proofs_tbl')->insert([
            'proof_id' => 10,
            'record_id' => 5,
            'disk_path' => 'sanction-proofs/5/history.pdf',
            'status' => 'submitted',
        ]);

        $source->put('announcements/2026/09/abc.pdf', 'attach-bytes');
        DB::table('announcement_attachments')->insert([
            'attachment_id' => 3,
            'announcement_id' => 1,
            'original_filename' => 'notice.pdf',
            'stored_filename' => 'abc.pdf',
            'storage_disk' => 'local',
            'storage_path' => 'announcements/2026/09/abc.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $exit = Artisan::call('storage:migrate-private-files', [
            '--source' => 'migrate_src',
            '--target' => 'migrate_tgt',
        ]);

        $this->assertSame(0, $exit);

        // Objects exist on the target at the SAME relative paths.
        $this->assertTrue($target->exists('violation-evidence/1/EVD-2026-000001.jpg'));
        $this->assertTrue($target->exists('sanction-proofs/5/proof.pdf'));
        $this->assertTrue($target->exists('sanction-proofs/5/history.pdf'));
        $this->assertTrue($target->exists('announcements/2026/09/abc.pdf'));

        // Source originals are preserved (never deleted).
        $this->assertTrue($source->exists('violation-evidence/1/EVD-2026-000001.jpg'));
        $this->assertTrue($source->exists('sanction-proofs/5/proof.pdf'));
        $this->assertTrue($source->exists('announcements/2026/09/abc.pdf'));

        // DB paths are preserved and content matches.
        $this->assertSame('evidence-bytes', $target->get('violation-evidence/1/EVD-2026-000001.jpg'));
        $this->assertSame('proof-bytes', $target->get('sanction-proofs/5/proof.pdf'));

        // Announcement metadata records the target disk now.
        $this->assertSame('migrate_tgt', DB::table('announcement_attachments')->where('attachment_id', 3)->value('storage_disk'));
        $this->assertSame('violation-evidence/1/EVD-2026-000001.jpg', DB::table('violation_evidence_tbl')->where('evidence_id', 1)->value('disk_path'));
    }

    public function test_migration_is_safely_rerunnable_and_skips_existing(): void
    {
        $source = Storage::disk('migrate_src');
        $target = Storage::disk('migrate_tgt');

        $source->put('violation-evidence/2/EVD-2026-000002.jpg', 'evidence-2');
        DB::table('violation_evidence_tbl')->insert([
            'evidence_id' => 2,
            'violation_id' => 2,
            'disk_path' => 'violation-evidence/2/EVD-2026-000002.jpg',
            'evidence_code' => 'EVD-2026-000002',
        ]);

        // First run migrates it.
        Artisan::call('storage:migrate-private-files', [
            '--source' => 'migrate_src',
            '--target' => 'migrate_tgt',
        ]);
        $this->assertTrue($target->exists('violation-evidence/2/EVD-2026-000002.jpg'));

        // Second run is a no-op for that object (no duplicate/overwrite error).
        $exit = Artisan::call('storage:migrate-private-files', [
            '--source' => 'migrate_src',
            '--target' => 'migrate_tgt',
        ]);
        $this->assertSame(0, $exit);
        $this->assertSame('evidence-2', $target->get('violation-evidence/2/EVD-2026-000002.jpg'));
        $this->assertTrue($source->exists('violation-evidence/2/EVD-2026-000002.jpg'));
    }

    public function test_rejects_same_source_and_target(): void
    {
        $exit = Artisan::call('storage:migrate-private-files', [
            '--source' => 'migrate_src',
            '--target' => 'migrate_src',
        ]);

        $this->assertSame(1, $exit);
    }

    private function createTables(): void
    {
        foreach ([
            'violation_evidence_tbl' => function (Blueprint $table): void {
                $table->increments('evidence_id');
                $table->string('evidence_code', 32)->nullable();
                $table->unsignedBigInteger('violation_id')->nullable();
                $table->string('disk_path', 500)->nullable();
                $table->string('original_name', 255)->nullable();
                $table->string('mime_type', 120)->nullable();
                $table->string('capture_method', 20)->nullable()->default('upload');
            },
            'student_sanction_records_tbl' => function (Blueprint $table): void {
                $table->increments('record_id');
                $table->string('student_number')->nullable();
                $table->string('proof_path', 500)->nullable();
                $table->string('proof_original_name', 255)->nullable();
            },
            'sanction_proofs_tbl' => function (Blueprint $table): void {
                $table->increments('proof_id');
                $table->unsignedBigInteger('record_id')->nullable();
                $table->string('disk_path', 500)->nullable();
                $table->string('status', 20)->default('submitted');
            },
            'announcement_attachments' => function (Blueprint $table): void {
                $table->increments('attachment_id');
                $table->unsignedBigInteger('announcement_id')->nullable();
                $table->string('original_filename', 255)->nullable();
                $table->string('stored_filename', 255)->nullable();
                $table->string('storage_disk', 120)->nullable();
                $table->string('storage_path', 500)->nullable();
                $table->string('mime_type', 120)->nullable();
            },
        ] as $table => $closure) {
            if (! Schema::hasTable($table)) {
                Schema::create($table, $closure);
            }
        }
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
