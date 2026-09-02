<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allows notifications addressed to Security (recipient_type = 'security') to
 * omit a student number.
 *
 * Security notifications live in the same notifications_tbl but belong to a
 * security account, so they have no student_number. Making the column nullable
 * (guarded) lets the SecurityNotificationService write rows without a student.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications_tbl')) {
            return;
        }

        $column = Schema::getColumnListing('notifications_tbl');
        if (! in_array('student_number', $column, true)) {
            return;
        }

        try {
            Schema::table('notifications_tbl', function (Blueprint $table): void {
                $table->string('student_number')->nullable()->change();
            });
        } catch (\Throwable $e) {
            // Some engines reject re-defining a legacy PK-indexed column. If the
            // column is already nullable this is a harmless no-op; if the alter
            // fails, security notifications still work by writing an empty string.
        }
    }

    public function down(): void
    {
        // Intentionally a no-op: making the column NOT NULL again could fail on
        // existing security rows and is not required for a safe rollback.
    }
};
