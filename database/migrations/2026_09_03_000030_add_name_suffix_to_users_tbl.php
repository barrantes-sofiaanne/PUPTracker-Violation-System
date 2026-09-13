<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add name_suffix to users_tbl.
 *
 * The PUP IDP is the authoritative source for a student's identity/name
 * information: first_name, middle_name, last_name and name_suffix are copied
 * from the IDP profile into the local users_tbl record when the student links
 * their account for the first time and refreshed on every subsequent login.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users_tbl')) {
            return;
        }

        if (! Schema::hasColumn('users_tbl', 'name_suffix')) {
            Schema::table('users_tbl', function (Blueprint $table): void {
                $table->string('name_suffix', 32)->nullable()->after('last_name');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users_tbl')) {
            return;
        }

        if (Schema::hasColumn('users_tbl', 'name_suffix')) {
            Schema::table('users_tbl', function (Blueprint $table): void {
                $table->dropColumn('name_suffix');
            });
        }
    }
};
