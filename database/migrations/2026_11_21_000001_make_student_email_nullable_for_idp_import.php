<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Student roster imports do not collect email addresses. The PUP IDP supplies
 * the verified email when a student links their identity, so users_tbl.email
 * must allow NULL until that first successful link.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users_tbl') || ! Schema::hasColumn('users_tbl', 'email')) {
            return;
        }

        Schema::table('users_tbl', function (Blueprint $table): void {
            $table->string('email', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Intentionally no-op. Student records may legitimately remain
        // unlinked to the PUP IDP and therefore have a NULL email.
    }
};
