<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the admission year(s) used for a graduating program report.
     *
     * The first 4 digits of a student number = admission year (e.g. 2022-XXXX).
     * A graduating report may cover multiple admission years (returnees), so
     * this stores the selected list as JSON.
     */
    public function up(): void
    {
        if (Schema::hasTable('program_reports') && ! Schema::hasColumn('program_reports', 'admission_years')) {
            Schema::table('program_reports', function (Blueprint $table): void {
                $table->json('admission_years')->nullable()->after('graduating_year');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('program_reports') && Schema::hasColumn('program_reports', 'admission_years')) {
            Schema::table('program_reports', function (Blueprint $table): void {
                $table->dropColumn('admission_years');
            });
        }
    }
};
