<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-submission proof-of-completion history for disciplinary sanctions.
 *
 * Previously a sanction record held only the LATEST proof (single proof_path).
 * This table keeps EVERY submission so the audit trail is preserved:
 *   Proof #1 -> rejected (retained)
 *   Proof #2 -> accepted
 *
 * The sanction record's proof_* columns remain as the pointer to the CURRENT
 * pending submission; this table holds the full history + review outcome.
 *
 * Columns:
 *   - proof_id             PK
 *   - record_id            FK -> student_sanction_records_tbl.record_id
 *   - disk_path            storage-relative path (private disk)
 *   - original_name        original upload filename
 *   - mime_type            stored mime
 *   - file_size            bytes
 *   - notes                student's optional note
 *   - status               submitted | accepted | rejected
 *   - reviewed_by_admin_id admins.id who reviewed (nullable while pending)
 *   - reviewed_at          when reviewed
 *   - review_remarks       acceptance note / rejection reason
 *   - submitted_at         when the student submitted this proof
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sanction_proofs_tbl')) {
            return;
        }

        Schema::create('sanction_proofs_tbl', function (Blueprint $table): void {
            $table->increments('proof_id');
            $table->unsignedBigInteger('record_id')->nullable()->index();
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
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sanction_proofs_tbl')) {
            return;
        }

        Schema::dropIfExists('sanction_proofs_tbl');
    }
};
