<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        // Create the table if it does not exist yet. Column lengths are
        // deliberately constrained so the composite index below stays within
        // MySQL's 3072-byte max key length (utf8mb4 = 4 bytes/char).
        if (! Schema::hasTable('email_incidents')) {
            Schema::create('email_incidents', function (Blueprint $table) {
                $table->id('incident_id');
                $table->string('incident_type', 50)->default('email_dispatch_failed');
                $table->string('email_type', 100)->nullable();
                $table->string('related_record_type', 100)->nullable();
                $table->string('related_record_id', 100)->nullable();
                $table->text('safe_error_message')->nullable();
                $table->string('status', 20)->default('failed'); // failed | acknowledged | resolved
                $table->unsignedBigInteger('acknowledged_by_admin_id')->nullable();
                $table->timestamp('acknowledged_at')->nullable();
                $table->unsignedBigInteger('resolved_by_admin_id')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('occurred_at')->nullable();
                $table->timestamps();
            });
        }

        // Recovery path: the very first Railway run created this table with the
        // original varchar(255) columns before the length fix was added, and the
        // index ALTER failed. Resize those existing columns so the composite
        // index will fit within MySQL's 3072-byte max key length.
        if (Schema::hasTable('email_incidents') && $this->isMySql()) {
            DB::statement(
                'ALTER TABLE `email_incidents`
                    MODIFY `incident_type` VARCHAR(50) NOT NULL DEFAULT \'email_dispatch_failed\',
                    MODIFY `email_type` VARCHAR(100) NULL,
                    MODIFY `related_record_type` VARCHAR(100) NULL,
                    MODIFY `related_record_id` VARCHAR(100) NULL,
                    MODIFY `status` VARCHAR(20) NOT NULL DEFAULT \'failed\''
            );
        }

        // Prevent notification spam: one open incident per operation.
        // Add the dedupe index separately so a partially-applied migration
        // (table created but index not) can be completed on re-run.
        if (Schema::hasTable('email_incidents') && ! $this->hasDedupeIndex()) {
            Schema::table('email_incidents', function (Blueprint $table) {
                $table->index(['email_type', 'related_record_type', 'related_record_id', 'status'], 'email_incident_dedupe_idx');
            });
        }
    }

    private function isMySql(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    /**
     * Check whether the dedupe index already exists.
     */
    private function hasDedupeIndex(): bool
    {
        try {
            return DB::table('information_schema.statistics')
                ->where('table_schema', DB::connection()->getDatabaseName())
                ->where('table_name', 'email_incidents')
                ->where('index_name', 'email_incident_dedupe_idx')
                ->exists();
        } catch (\Throwable) {
            // Non-MySQL driver (e.g. SQLite in local tests): assume it doesn't
            // exist so the index is (re)created by the schema builder.
            return false;
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_incidents');
    }
};
