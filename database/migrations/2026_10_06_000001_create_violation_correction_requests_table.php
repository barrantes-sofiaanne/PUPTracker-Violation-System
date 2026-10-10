<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('violation_correction_requests', function (Blueprint $table) {
            $table->id('request_id');

            // Existing violation_tbl.violation_id is INT
            $table->integer('violation_id');

            $table->string('student_number', 50);

            // Existing violation_type_tbl.violation_type_id is INT
            $table->integer('requested_violation_type_id')->nullable();

            $table->dateTime('requested_violation_date')->nullable();

            $table->text('requested_description')->nullable();

            $table->text('reason');

            $table->string('status', 20)->default('pending');

            // Existing admins.id is INT
            $table->integer('reviewed_by_admin_id')->nullable();

            $table->dateTime('reviewed_at')->nullable();

            $table->text('review_remarks')->nullable();

            $table->timestamps();

            /*
             * Indexes
             */
            $table->index(
                ['student_number', 'status'],
                'vcr_student_status_index'
            );

            $table->index(
                ['violation_id', 'status'],
                'vcr_violation_status_index'
            );

            /*
             * Foreign keys
             */
            $table->foreign('violation_id', 'vcr_violation_fk')
                ->references('violation_id')
                ->on('violation_tbl')
                ->cascadeOnDelete();

            $table->foreign(
                'requested_violation_type_id',
                'vcr_violation_type_fk'
            )
                ->references('violation_type_id')
                ->on('violation_type_tbl')
                ->nullOnDelete();

            $table->foreign(
                'reviewed_by_admin_id',
                'vcr_admin_fk'
            )
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