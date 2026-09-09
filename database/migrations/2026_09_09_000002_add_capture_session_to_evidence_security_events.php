<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correlate evidence security events with a camera/capture session.
 *
 * The browser monitor can now create a capture_session_id (e.g.
 * CAM-SESSION-YYYY-NNNNNN) when the camera portal opens, and every event
 * (CAMERA_OPENED, CAMERA_INITIALIZED, PAGE_BLURRED, POSSIBLE_SCREEN_CAPTURE,
 * PAGE_FOCUS_RESTORED, PHOTO_CAPTURED, EVIDENCE_SUBMITTED, CAPTURE_ABANDONED)
 * can reference the SAME session id. After submission the session is linked to
 * the evidence code(s), giving a much stronger audit trail than null-scoped
 * rows.
 *
 * This is additive and reversible; existing rows keep a NULL session id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('evidence_security_events_tbl')) {
            $cols = array_map('strtolower', Schema::getColumnListing('evidence_security_events_tbl'));

            if (! in_array('capture_session_id', $cols, true)) {
                Schema::table('evidence_security_events_tbl', function (Blueprint $table): void {
                    $table->string('capture_session_id', 64)->nullable()->index()->after('event_type');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('evidence_security_events_tbl')) {
            $cols = array_map('strtolower', Schema::getColumnListing('evidence_security_events_tbl'));

            if (in_array('capture_session_id', $cols, true)) {
                Schema::table('evidence_security_events_tbl', function (Blueprint $table): void {
                    $table->dropColumn('capture_session_id');
                });
            }
        }
    }
};
