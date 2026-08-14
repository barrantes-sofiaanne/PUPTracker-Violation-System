<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id('maintenance_id');
            $table->date('maintenance_date');
            $table->string('maintenance_type');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('performed_by_admin_id')->nullable();

            // Dependency / framework / runtime / server / testing status.
            $table->string('dependency_status')->nullable();
            $table->string('php_runtime_status')->nullable();
            $table->string('server_status')->nullable();
            $table->string('testing_status')->nullable();

            // Overall result.
            $table->string('overall_status')->default('Completed');
            $table->text('notes')->nullable();

            // Post-maintenance report.
            $table->text('updates_identified')->nullable();
            $table->text('updates_applied')->nullable();
            $table->text('tests_performed')->nullable();
            $table->text('test_results')->nullable();
            $table->text('problems_found')->nullable();
            $table->text('corrective_actions')->nullable();

            // Checklist (10 items) as JSON booleans.
            $table->json('checklist_json')->nullable();

            $table->timestamps();

            $table->index(['maintenance_date']);
            $table->index(['maintenance_type']);
            $table->index(['performed_by_admin_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
    }
};
