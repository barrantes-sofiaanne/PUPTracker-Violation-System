<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Announcement attachments — the physical files live in private persistent
 * object/file storage (never MySQL BLOBs, never the public web root), while
 * this table stores only attachment metadata and the relationship to the
 * announcement.
 *
 * Relationship:
 *   announcements_tbl (1) ──< (N) announcement_attachments
 *
 * Security model:
 *   - original_filename is display-only metadata; it is never used as the
 *     physical storage filename (a random server-generated name is used).
 *   - storage_path is the storage-relative path on the private disk.
 *   - file_hash is a SHA-256 of the stored object (integrity/auditing).
 *   - uploaded_by references admins.id (the admin who uploaded the file).
 *
 * Column conventions mirror violation_evidence_tbl (increments PK, no DB-level
 * FK constraints — the codebase keeps FKs at the application layer to stay
 * compatible with the legacy hand-rolled test schema).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('announcement_attachments')) {
            return;
        }

        Schema::create('announcement_attachments', function (Blueprint $table): void {
            $table->increments('attachment_id');
            $table->unsignedBigInteger('announcement_id')->nullable()->index();
            $table->string('original_filename', 255)->nullable();
            $table->string('stored_filename', 255)->nullable();
            $table->string('storage_disk', 50)->nullable()->default('local');
            $table->string('storage_path', 500)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->char('file_hash', 64)->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable()->index();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('announcement_attachments')) {
            return;
        }

        Schema::dropIfExists('announcement_attachments');
    }
};
