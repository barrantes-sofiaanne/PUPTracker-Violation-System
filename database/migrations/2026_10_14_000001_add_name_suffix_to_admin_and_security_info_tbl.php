<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admin_info_tbl') && !Schema::hasColumn('admin_info_tbl', 'name_suffix')) {
            Schema::table('admin_info_tbl', function (Blueprint $table): void {
                $table->string('name_suffix', 20)->nullable()->after('lastname');
            });
        }

        if (Schema::hasTable('security_info_tbl') && !Schema::hasColumn('security_info_tbl', 'name_suffix')) {
            Schema::table('security_info_tbl', function (Blueprint $table): void {
                $table->string('name_suffix', 20)->nullable()->after('lastname');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('security_info_tbl') && Schema::hasColumn('security_info_tbl', 'name_suffix')) {
            Schema::table('security_info_tbl', function (Blueprint $table): void {
                $table->dropColumn('name_suffix');
            });
        }

        if (Schema::hasTable('admin_info_tbl') && Schema::hasColumn('admin_info_tbl', 'name_suffix')) {
            Schema::table('admin_info_tbl', function (Blueprint $table): void {
                $table->dropColumn('name_suffix');
            });
        }
    }
};
