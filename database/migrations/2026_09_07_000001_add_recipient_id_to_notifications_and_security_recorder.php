<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Strict role-based notification scoping.
 *
 * Previously, notifications addressed to Security (recipient_type = 'security')
 * were stored with NO per-officer key, so every security officer could see and
 * open every security notification. This change adds:
 *
 *   notifications_tbl.recipient_id  -> the security account (security.id) a
 *                                      notification is addressed to. NULL means
 *                                      "all security" (legacy / pre-policy rows).
 *
 *   violation_tbl.recorded_by_security_id -> the security account that recorded
 *                                      a violation. Persisting it lets the
 *                                      admin-confirm/deny flow notify the exact
 *                                      recording officer (instead of relying on
 *                                      a display-name string match).
 *
 * Both additions are guarded, additive and reversible, and preserve existing rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications_tbl') && ! Schema::hasColumn('notifications_tbl', 'recipient_id')) {
            Schema::table('notifications_tbl', function (Blueprint $table): void {
                $table->unsignedBigInteger('recipient_id')->nullable()->index()->after('recipient_type');
            });
        }

        if (Schema::hasTable('violation_tbl') && ! Schema::hasColumn('violation_tbl', 'recorded_by_security_id')) {
            Schema::table('violation_tbl', function (Blueprint $table): void {
                $table->unsignedBigInteger('recorded_by_security_id')->nullable()->index()->after('recorder_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notifications_tbl') && Schema::hasColumn('notifications_tbl', 'recipient_id')) {
            // Drop the index first: SQLite cannot drop an indexed column, and
            // this keeps the rollback portable across MySQL/SQLite.
            Schema::table('notifications_tbl', function (Blueprint $table): void {
                if ($this->indexExists('notifications_tbl', 'notifications_tbl_recipient_id_index')) {
                    $table->dropIndex(['recipient_id']);
                }
            });

            Schema::table('notifications_tbl', function (Blueprint $table): void {
                $table->dropColumn('recipient_id');
            });
        }

        if (Schema::hasTable('violation_tbl') && Schema::hasColumn('violation_tbl', 'recorded_by_security_id')) {
            Schema::table('violation_tbl', function (Blueprint $table): void {
                if ($this->indexExists('violation_tbl', 'violation_tbl_recorded_by_security_id_index')) {
                    $table->dropIndex(['recorded_by_security_id']);
                }
            });

            Schema::table('violation_tbl', function (Blueprint $table): void {
                $table->dropColumn('recorded_by_security_id');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasIndex($table, $index);
        } catch (\Throwable $e) {
            // Older Laravel/DB combos may not support hasIndex for this table.
            return false;
        }
    }
};
