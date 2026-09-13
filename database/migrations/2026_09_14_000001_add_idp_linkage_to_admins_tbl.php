<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link a PUP IDP identity to an administrator account.
     *
     * Mirrors `users_tbl.idp_subject` / `idp_email` / `idp_connected_at`, which
     * the student IDP flow already uses. A NULL `idp_subject` means the account
     * has never signed in through the IDP; the binding is written on the first
     * successful IDP login (matched on the IDP-verified email).
     *
     * `admins` is a legacy table with no creating migration in this repo, so the
     * existing table/column state is checked first and the whole up() is
     * skipped when the table is absent — otherwise `migrate:fresh` on the
     * in-memory SQLite used by the test suite would abort here and every later
     * migration would silently never run.
     */
    public function up(): void
    {
        if (! Schema::hasTable('admins')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table): void {
            if (! Schema::hasColumn('admins', 'idp_subject')) {
                $table->string('idp_subject')->nullable()->unique()->after('email');
            }

            if (! Schema::hasColumn('admins', 'idp_email')) {
                $table->string('idp_email')->nullable()->after('idp_subject');
            }

            if (! Schema::hasColumn('admins', 'idp_connected_at')) {
                $table->timestamp('idp_connected_at')->nullable()->after('idp_email');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admins')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table): void {
            if (Schema::hasColumn('admins', 'idp_subject')) {
                $table->dropUnique(['idp_subject']);
                $table->dropColumn('idp_subject');
            }

            if (Schema::hasColumn('admins', 'idp_email')) {
                $table->dropColumn('idp_email');
            }

            if (Schema::hasColumn('admins', 'idp_connected_at')) {
                $table->dropColumn('idp_connected_at');
            }
        });
    }
};
