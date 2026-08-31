<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the Ladderized filter used for a graduating program report.
     *
     * For BSOA/BSIT programs the last digit of the student number encodes
     * ladderized (1) vs non-ladderized (0) status. This records which filter
     * was applied: null = both, 1 = ladderized only, 0 = non-ladderized only.
     */
    public function up(): void
    {
        if (Schema::hasTable('program_reports') && ! Schema::hasColumn('program_reports', 'ladderized')) {
            Schema::table('program_reports', function (Blueprint $table): void {
                $table->unsignedTinyInteger('ladderized')->nullable()->after('admission_years');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('program_reports') && Schema::hasColumn('program_reports', 'ladderized')) {
            Schema::table('program_reports', function (Blueprint $table): void {
                $table->dropColumn('ladderized');
            });
        }
    }
};
