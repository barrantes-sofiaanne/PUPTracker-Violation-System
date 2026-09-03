<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store explicitly-added student numbers for a graduating program report.
 *
 * A graduating cohort can be the UNION of:
 *   - students whose admission year (first 4 digits of student_number) is in
 *     the selected admission_years, AND
 *   - specific students added by searching (e.g. returnees / re-admits whose
 *     student number does not start with an admission year).
 *
 * This column stores the extra student numbers included in the report so the
 * generated report's history/view can show exactly which students were used.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('program_reports') && ! Schema::hasColumn('program_reports', 'additional_students')) {
            Schema::table('program_reports', function (Blueprint $table): void {
                $table->json('additional_students')->nullable()->after('ladderized');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('program_reports') && Schema::hasColumn('program_reports', 'additional_students')) {
            Schema::table('program_reports', function (Blueprint $table): void {
                $table->dropColumn('additional_students');
            });
        }
    }
};
