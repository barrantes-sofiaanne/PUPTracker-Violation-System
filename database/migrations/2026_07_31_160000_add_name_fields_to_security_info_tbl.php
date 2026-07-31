<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('security_info_tbl')) {
            return;
        }

        Schema::table('security_info_tbl', function (Blueprint $table) {
            if (!Schema::hasColumn('security_info_tbl', 'firstname')) {
                $table->string('firstname', 100)->nullable()->after('security_id');
            }

            if (!Schema::hasColumn('security_info_tbl', 'middlename')) {
                $table->string('middlename', 100)->nullable()->after('firstname');
            }

            if (!Schema::hasColumn('security_info_tbl', 'lastname')) {
                $table->string('lastname', 100)->nullable()->after('middlename');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('security_info_tbl')) {
            return;
        }

        Schema::table('security_info_tbl', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('security_info_tbl', 'lastname')) {
                $columns[] = 'lastname';
            }

            if (Schema::hasColumn('security_info_tbl', 'middlename')) {
                $columns[] = 'middlename';
            }

            if (Schema::hasColumn('security_info_tbl', 'firstname')) {
                $columns[] = 'firstname';
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
