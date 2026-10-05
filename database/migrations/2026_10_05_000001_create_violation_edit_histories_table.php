<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('violation_edit_histories')) {
            return;
        }

        Schema::create('violation_edit_histories', function (Blueprint $table) {
            $table->id();

            /*
             * Foreign keys
             *
             * violation_tbl.violation_id = INT
             * admins.id = INT
             */
            $table->integer('violation_id');
            $table->integer('admin_id');

            /*
             * Original values before the correction.
             */
            $table->unsignedBigInteger('old_violation_type_id')->nullable();
            $table->dateTime('old_violation_date')->nullable();
            $table->text('old_description')->nullable();

            /*
             * New values after the correction.
             */
            $table->unsignedBigInteger('new_violation_type_id')->nullable();
            $table->dateTime('new_violation_date')->nullable();
            $table->text('new_description')->nullable();

            /*
             * Required explanation for the correction.
             */
            $table->text('reason');

            $table->timestamps();

            $table->index('violation_id');
            $table->index('admin_id');

            $table->foreign('violation_id')
                ->references('violation_id')
                ->on('violation_tbl')
                ->cascadeOnDelete();

            $table->foreign('admin_id')
                ->references('id')
                ->on('admins')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('violation_edit_histories');
    }
};