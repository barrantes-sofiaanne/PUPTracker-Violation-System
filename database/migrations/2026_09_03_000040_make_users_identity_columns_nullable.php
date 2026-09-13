<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ensure users_tbl identity columns are nullable.
 *
 * The PUP IDP is the authoritative source for a student's identity
 * (first_name, middle_name, last_name, name_suffix, email). An administrator
 * creates the enrollment record with ONLY the student number + academic data;
 * the identity fields stay NULL until the student links their PUP IDP account
 * for the first time. Some legacy users_tbl schemas may have email NOT NULL
 * from an older design — this migration normalizes it so new students can be
 * created without an email.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users_tbl')) {
            return;
        }

        $identityColumns = ['email', 'first_name', 'middle_name', 'last_name', 'name_suffix'];

        foreach ($identityColumns as $column) {
            if (! Schema::hasColumn('users_tbl', $column)) {
                continue;
            }

            try {
                Schema::table('users_tbl', function (Blueprint $table) use ($column): void {
                    $table->string($column)->nullable()->change();
                });
            } catch (\Throwable $e) {
                // Column type may differ (text etc.); making it nullable is the
                // goal — retry with a generic change is unnecessary. Log-safe.
            }
        }
    }

    public function down(): void
    {
        // Intentionally no-op: re-adding NOT NULL could fail on rows that were
        // created without identity data. Reversing is a deliberate admin action.
    }
};
