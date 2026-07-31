<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('violation_tbl')) {
            return;
        }

        Schema::table('violation_tbl', function (Blueprint $table) {
            if (!Schema::hasColumn('violation_tbl', 'recorder_type')) {
                $table->string('recorder_type', 20)->nullable()->after('recorder_id');
            }

            if (!Schema::hasColumn('violation_tbl', 'recorder_name')) {
                $table->string('recorder_name', 150)->nullable()->after('recorder_type');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('violation_tbl')) {
            return;
        }

        Schema::table('violation_tbl', function (Blueprint $table) {
            $columnsToDrop = [];

            if (Schema::hasColumn('violation_tbl', 'recorder_name')) {
                $columnsToDrop[] = 'recorder_name';
            }

            if (Schema::hasColumn('violation_tbl', 'recorder_type')) {
                $columnsToDrop[] = 'recorder_type';
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
