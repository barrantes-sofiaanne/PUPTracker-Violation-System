<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive hardening of the evidence + sanction-proof stores (SHA-256 + codes).
 *
 * Violation evidence:
 *   - sha256_hash   integrity digest of the FINAL stored file (computed after
 *                   any server-side watermark pass). Never silently overwritten.
 *   - server_watermarked   set true only after the server baked a watermark into
 *                   the stored image (the client `watermarked` flag is NOT proof).
 *   - capture_session_id  correlates the evidence row to the camera/capture
 *                   session (CAM-SESSION-YYYY-NNNNNN) it was captured under.
 *
 * Sanction proofs:
 *   - proof_code    human-readable PROOF-YYYY-NNNNNN (display/audit only)
 *   - sha256_hash   integrity digest of the ORIGINAL uploaded file (never edited)
 *   - student_number  owning student (denormalized, for history display/audit)
 *
 * All additions are guarded, additive, and reversible. Existing rows are
 * preserved unchanged (hashes are back-filled lazily where possible).
 */
return new class extends Migration
{
    public function up(): void
    {
        $evidenceColumns = [
            'sha256_hash' => 'char(64)',
            'server_watermarked' => 'bool',
        ];

        if (Schema::hasTable('violation_evidence_tbl')) {
            $cols = array_map('strtolower', Schema::getColumnListing('violation_evidence_tbl'));

            if (! in_array('sha256_hash', $cols, true)) {
                Schema::table('violation_evidence_tbl', function (Blueprint $table): void {
                    $table->char('sha256_hash', 64)->nullable()->after('file_size');
                });
            }

            if (! in_array('server_watermarked', $cols, true)) {
                Schema::table('violation_evidence_tbl', function (Blueprint $table): void {
                    $table->boolean('server_watermarked')->default(false)->after('watermarked');
                });
            }

            if (! in_array('capture_session_id', $cols, true)) {
                Schema::table('violation_evidence_tbl', function (Blueprint $table): void {
                    $table->string('capture_session_id', 64)->nullable()->index()->after('evidence_code');
                });
            }
        }

        if (Schema::hasTable('sanction_proofs_tbl')) {
            $cols = array_map('strtolower', Schema::getColumnListing('sanction_proofs_tbl'));

            if (! in_array('proof_code', $cols, true)) {
                Schema::table('sanction_proofs_tbl', function (Blueprint $table): void {
                    $table->string('proof_code', 32)->nullable()->after('proof_id');
                });
            }

            if (! in_array('sha256_hash', $cols, true)) {
                Schema::table('sanction_proofs_tbl', function (Blueprint $table): void {
                    $table->char('sha256_hash', 64)->nullable()->after('file_size');
                });
            }

            if (! in_array('student_number', $cols, true)) {
                Schema::table('sanction_proofs_tbl', function (Blueprint $table): void {
                    $table->string('student_number', 50)->nullable()->index()->after('record_id');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sanction_proofs_tbl')) {
            $cols = array_map('strtolower', Schema::getColumnListing('sanction_proofs_tbl'));

            foreach (['proof_code', 'sha256_hash', 'student_number'] as $column) {
                if (in_array($column, $cols, true)) {
                    Schema::table('sanction_proofs_tbl', function (Blueprint $table) use ($column): void {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        if (Schema::hasTable('violation_evidence_tbl')) {
            $cols = array_map('strtolower', Schema::getColumnListing('violation_evidence_tbl'));

            foreach (['sha256_hash', 'server_watermarked', 'capture_session_id'] as $column) {
                if (in_array($column, $cols, true)) {
                    Schema::table('violation_evidence_tbl', function (Blueprint $table) use ($column): void {
                        $table->dropColumn($column);
                    });
                }
            }
        }
    }
};
