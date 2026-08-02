<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users_tbl')) {
            return;
        }

        Schema::table('users_tbl', function (Blueprint $table): void {
            if (! Schema::hasColumn('users_tbl', 'idp_subject')) {
                $table->string('idp_subject')->nullable()->unique()->after('email');
            }

            if (! Schema::hasColumn('users_tbl', 'idp_email')) {
                $table->string('idp_email')->nullable()->after('idp_subject');
            }

            if (! Schema::hasColumn('users_tbl', 'idp_connected_at')) {
                $table->timestamp('idp_connected_at')->nullable()->after('idp_email');
            }

            if (! Schema::hasColumn('users_tbl', 'idp_last_login_at')) {
                $table->timestamp('idp_last_login_at')->nullable()->after('idp_connected_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users_tbl')) {
            return;
        }

        Schema::table('users_tbl', function (Blueprint $table): void {
            if (Schema::hasColumn('users_tbl', 'idp_last_login_at')) {
                $table->dropColumn('idp_last_login_at');
            }

            if (Schema::hasColumn('users_tbl', 'idp_connected_at')) {
                $table->dropColumn('idp_connected_at');
            }

            if (Schema::hasColumn('users_tbl', 'idp_email')) {
                $table->dropColumn('idp_email');
            }

            if (Schema::hasColumn('users_tbl', 'idp_subject')) {
                $table->dropUnique(['idp_subject']);
                $table->dropColumn('idp_subject');
            }
        });
    }
};