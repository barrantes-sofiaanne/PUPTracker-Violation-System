<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks when a student finished FIRST-TIME registration / profile completion.
 *
 * The student IDP flow already records `idp_subject` (the permanent identity
 * link). This column records the separate fact that the student completed the
 * guided registration step, so the dashboard gate can be explicit rather than
 * inferred from an unrelated column's nullability.
 *
 * Additive and idempotent:
 *   - existing rows keep NULL,
 *   - existing LINKED students are backfilled to their `idp_connected_at` so
 *     current users are NOT locked out of the dashboard by this change,
 *   - no existing column is altered or dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users_tbl')) {
            return;
        }

        if (! Schema::hasColumn('users_tbl', 'profile_completed_at')) {
            Schema::table('users_tbl', function (Blueprint $table): void {
                $table->timestamp('profile_completed_at')
                    ->nullable()
                    ->after('idp_last_login_at');
            });
        }

        // Grandfather EXISTING linked accounts into the "completed" state.
        // Without this, every already-registered student would be sent back
        // through first-time registration on their next login.
        try {
            \Illuminate\Support\Facades\DB::table('users_tbl')
                ->whereNull('profile_completed_at')
                ->whereNotNull('idp_subject')
                ->update([
                    'profile_completed_at' => \Illuminate\Support\Facades\DB::raw('COALESCE(idp_connected_at, updated_at, created_at)'),
                ]);
        } catch (\Throwable $e) {
            // A backfill failure must not block the migration; the column still
            // exists and new registrations will set it.
            \Illuminate\Support\Facades\Log::warning('profile_completed_at backfill skipped.', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users_tbl') && Schema::hasColumn('users_tbl', 'profile_completed_at')) {
            Schema::table('users_tbl', function (Blueprint $table): void {
                $table->dropColumn('profile_completed_at');
            });
        }
    }
};
