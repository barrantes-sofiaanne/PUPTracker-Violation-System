<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('violation_correction_requests')) {
            return;
        }

        Schema::create('violation_correction_requests', function (Blueprint $table) {
            $table->id('request_id');

            $table->unsignedBigInteger('violation_id');

            $table->string('student_number', 50);

            /*
             * Requested values.
             * These DO NOT directly modify violation_tbl.
             */
            $table->unsignedBigInteger('requested_violation_type_id')->nullable();

            $table->dateTime('requested_violation_date')->nullable();

            $table->text('requested_description')->nullable();

            /*
             * Explanation supplied by the student.
             */
            $table->text('reason');

            /*
             * pending | approved | rejected
             */
            $table->string('status', 20)->default('pending');

            /*
             * Administrator who reviewed the request.
             */
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();

            $table->dateTime('reviewed_at')->nullable();

            $table->text('review_remarks')->nullable();

            $table->timestamps();

            $table->index('violation_id');
            $table->index('student_number');
            $table->index('status');
            $table->index('reviewed_by_admin_id');

            $table->foreign('violation_id')
                ->references('violation_id')
                ->on('violation_tbl')
                ->cascadeOnDelete();

            $table->foreign('reviewed_by_admin_id')
                ->references('id')
                ->on('admins')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('violation_correction_requests');
    }
};