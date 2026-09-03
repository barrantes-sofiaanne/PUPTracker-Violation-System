<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hardening of the multi-file evidence store (evidence protection workflow).
 *
 * Adds a human-readable, unique evidence code (EVD-YYYY-NNNNNN) plus capture
 * metadata so every evidence row can be watermarked, traced, and audited:
 *
 *   - evidence_code        unique display/storage code, e.g. EVD-2026-000001
 *   - capture_method       'camera' (controlled capture) | 'upload' (PDF etc.)
 *   - watermarked          whether the stored image has the baked watermark
 *   - captured_at          when the photo was captured (camera path)
 *   - ip_address           audit metadata (never proof of identity)
 *   - user_agent           audit metadata
 *
 * The storage path is private (storage/app/private or S3-equivalent on
 * Railway). File names on disk are derived from the evidence_code; the
 * original filename is kept only as display metadata.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('violation_evidence_tbl')) {
            $columns = array_map('strtolower', Schema::getColumnListing('violation_evidence_tbl'));

            if (! in_array('evidence_code', $columns, true)) {
                Schema::table('violation_evidence_tbl', function (Blueprint $table): void {
                    $table->string('evidence_code', 32)->nullable()->unique()->after('evidence_id');
                });
            }

            if (! in_array('capture_method', $columns, true)) {
                Schema::table('violation_evidence_tbl', function (Blueprint $table): void {
                    $table->string('capture_method', 20)->nullable()->default('upload')->after('mime_type');
                });
            }

            if (! in_array('watermarked', $columns, true)) {
                Schema::table('violation_evidence_tbl', function (Blueprint $table): void {
                    $table->boolean('watermarked')->default(false)->after('capture_method');
                });
            }

            if (! in_array('captured_at', $columns, true)) {
                Schema::table('violation_evidence_tbl', function (Blueprint $table): void {
                    $table->timestamp('captured_at')->nullable()->after('watermarked');
                });
            }

            if (! in_array('ip_address', $columns, true)) {
                Schema::table('violation_evidence_tbl', function (Blueprint $table): void {
                    $table->string('ip_address', 64)->nullable()->after('file_size');
                });
            }

            if (! in_array('user_agent', $columns, true)) {
                Schema::table('violation_evidence_tbl', function (Blueprint $table): void {
                    $table->string('user_agent', 500)->nullable()->after('ip_address');
                });
            }
        }

        if (! Schema::hasTable('evidence_security_events_tbl')) {
            Schema::create('evidence_security_events_tbl', function (Blueprint $table): void {
                $table->increments('event_id');
                $table->unsignedBigInteger('violation_id')->nullable()->index();
                $table->unsignedBigInteger('evidence_id')->nullable()->index();
                $table->string('event_type', 80)->index();
                $table->string('severity', 20)->default('info');
                $table->string('actor_type', 20)->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->text('meta')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('evidence_security_events_tbl')) {
            Schema::dropIfExists('evidence_security_events_tbl');
        }

        if (Schema::hasTable('violation_evidence_tbl')) {
            $columns = array_map('strtolower', Schema::getColumnListing('violation_evidence_tbl'));

            foreach (['evidence_code', 'capture_method', 'watermarked', 'captured_at', 'ip_address', 'user_agent'] as $column) {
                if (in_array($column, $columns, true)) {
                    Schema::table('violation_evidence_tbl', function (Blueprint $table) use ($column): void {
                        $table->dropColumn($column);
                    });
                }
            }
        }
    }
};
