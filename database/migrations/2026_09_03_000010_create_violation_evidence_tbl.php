<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-file evidence for recorded violations (security uploads).
 *
 * Evidence is stored on the private local disk (storage/app/private) and is
 * never served from the public directory. Access is authorized per role:
 *   - Security can upload + view evidence for violations they recorded.
 *   - Admin can view evidence while reviewing/confirming a violation.
 *   - Student sees evidence on confirmed violations on their record (read-only).
 *
 * Columns:
 *   - evidence_id          PK
 *   - violation_id         FK -> violation_tbl.violation_id
 *   - disk_path            storage-relative path (private disk)
 *   - original_name        original upload filename (display only)
 *   - mime_type            stored mime (e.g. image/jpeg, application/pdf)
 *   - file_size            bytes
 *   - uploader_type        'security' | 'admin'
 *   - uploader_id          security.id or admins.id (depending on type)
 *   - created_at
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('violation_evidence_tbl')) {
            return;
        }

        Schema::create('violation_evidence_tbl', function (Blueprint $table): void {
            $table->increments('evidence_id');
            $table->unsignedBigInteger('violation_id')->nullable()->index();
            $table->string('disk_path', 500)->nullable();
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('uploader_type', 20)->nullable();
            $table->unsignedBigInteger('uploader_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('violation_evidence_tbl')) {
            return;
        }

        Schema::dropIfExists('violation_evidence_tbl');
    }
};
