<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_reports', function (Blueprint $table) {
            $table->id('report_id');
            $table->unsignedBigInteger('program_id');
            $table->string('academic_year')->nullable();
            $table->string('report_type')->default('full'); // full | violations | outstanding | manual_review
            $table->unsignedBigInteger('generated_by_admin_id')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->string('report_status')->default('generated'); // generated | ai_failed | ai_pending
            $table->json('statistics_json')->nullable();
            $table->json('violation_breakdown')->nullable();
            $table->json('repeated_violations')->nullable();
            $table->json('outstanding_students')->nullable();
            $table->json('manual_review_students')->nullable();
            $table->text('ai_narrative')->nullable();
            $table->string('ai_model')->nullable();
            $table->boolean('ai_failed')->default(false);
            $table->timestamps();

            $table->index(['program_id', 'academic_year']);
            $table->index(['generated_by_admin_id']);
            $table->index(['generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_reports');
    }
};
