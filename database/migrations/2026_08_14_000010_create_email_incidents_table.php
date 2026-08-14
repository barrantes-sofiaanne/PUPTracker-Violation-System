<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Risk No. 7 — Incident Reporting & Response.
     *
     * Records definitive email dispatch failures. Super Admin only.
     * This table NEVER stores credentials, SMTP passwords, or tokens.
     */
    public function up(): void
    {
        if (Schema::hasTable('email_incidents')) {
            return;
        }

        Schema::create('email_incidents', function (Blueprint $table) {
            $table->id('incident_id');
            $table->string('incident_type')->default('email_dispatch_failed');
            $table->string('email_type')->nullable();
            $table->string('related_record_type')->nullable();
            $table->string('related_record_id')->nullable();
            $table->text('safe_error_message')->nullable();
            $table->string('status')->default('failed'); // failed | acknowledged | resolved
            $table->unsignedBigInteger('acknowledged_by_admin_id')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->unsignedBigInteger('resolved_by_admin_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            // Prevent notification spam: one open incident per operation.
            $table->index(['email_type', 'related_record_type', 'related_record_id', 'status'], 'email_incident_dedupe_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_incidents');
    }
};
