<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('announcements_tbl')) {
            return;
        }

        if (! Schema::hasColumn('announcements_tbl', 'login_modal_expires_at')) {
            Schema::table('announcements_tbl', function (Blueprint $table) {
                $table->timestamp('login_modal_expires_at')->nullable()->after('show_on_login');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('announcements_tbl')
            && Schema::hasColumn('announcements_tbl', 'login_modal_expires_at')) {
            Schema::table('announcements_tbl', function (Blueprint $table) {
                $table->dropColumn('login_modal_expires_at');
            });
        }
    }
};
