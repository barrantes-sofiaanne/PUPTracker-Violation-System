<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('violation_correction_requests', function (Blueprint $table) {
            $table->string('request_type', 20)->default('correction')->after('student_number');
            $table->json('deletion_evidence')->nullable()->after('reason');
            $table->integer('original_violation_id')->nullable()->after('violation_id');
        });

        // Keep an approved deletion request and its review history after the
        // violation itself is removed. The original ID is retained separately.
        Schema::table('violation_correction_requests', function (Blueprint $table) {
            $table->dropForeign('vcr_violation_fk');
            $table->integer('violation_id')->nullable()->change();
            $table->foreign('violation_id', 'vcr_violation_fk')
                ->references('violation_id')->on('violation_tbl')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('violation_correction_requests', function (Blueprint $table) {
            $table->dropForeign('vcr_violation_fk');
            $table->foreign('violation_id', 'vcr_violation_fk')
                ->references('violation_id')->on('violation_tbl')->cascadeOnDelete();
            $table->integer('violation_id')->nullable(false)->change();
            $table->dropColumn(['request_type', 'deletion_evidence', 'original_violation_id']);
        });
    }
};
