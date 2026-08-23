<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('program_tbl') || Schema::hasColumn('program_tbl', 'degree_type')) {
            return;
        }

        Schema::table('program_tbl', function (Blueprint $table): void {
            $table->string('degree_type', 30)->nullable()->after('program_name');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('program_tbl') && Schema::hasColumn('program_tbl', 'degree_type')) {
            Schema::table('program_tbl', function (Blueprint $table): void {
                $table->dropColumn('degree_type');
            });
        }
    }
};