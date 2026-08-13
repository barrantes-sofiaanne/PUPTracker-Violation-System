<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_graduation_reviews', function (Blueprint $table) {
            $table->id('review_id');
            $table->string('student_number');
            $table->unsignedBigInteger('generated_by_admin_id')->nullable();
            $table->string('review_status')->nullable();
            $table->text('summary')->nullable();
            $table->text('disciplinary_pattern')->nullable();
            $table->text('reason_for_review')->nullable();
            $table->text('recommended_action')->nullable();
            $table->json('outstanding_sanctions')->nullable();
            $table->unsignedInteger('total_violations')->default(0);
            $table->unsignedInteger('completed_sanctions')->default(0);
            $table->unsignedInteger('pending_sanctions')->default(0);
            $table->unsignedInteger('outstanding_sanctions_count')->default(0);
            $table->string('ai_model')->nullable();
            $table->timestamp('ai_generated_at')->nullable();
            $table->timestamps();

            $table->index(['student_number']);
            $table->index(['review_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_graduation_reviews');
    }
};
