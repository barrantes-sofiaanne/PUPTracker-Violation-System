<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('announcements_tbl')) {
            return;
        }

        if (!Schema::hasColumn('announcements_tbl', 'show_on_login')) {
            Schema::table('announcements_tbl', function (Blueprint $table): void {
                $table->boolean('show_on_login')
                    ->default(false)
                    ->after('attachment_path');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('announcements_tbl')) {
            return;
        }

        if (Schema::hasColumn('announcements_tbl', 'show_on_login')) {
            Schema::table('announcements_tbl', function (Blueprint $table): void {
                $table->dropColumn('show_on_login');
            });
        }
    }
};
