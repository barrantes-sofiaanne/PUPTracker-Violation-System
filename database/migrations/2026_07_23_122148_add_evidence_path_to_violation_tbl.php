<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // `violation_tbl` is a LEGACY table that predates the migration history
        // (no migration creates it), so it is absent from a database built from
        // migrations alone — e.g. the in-memory SQLite schema used by
        // RefreshDatabase in tests. Without this guard the ALTER throws,
        // `migrate:fresh` aborts mid-run, and every later migration never
        // executes. Guarding matches the sibling migrations
        // (…_add_recorder_metadata_to_violation_tbl, …_add_confirmation_workflow…).
        if (! Schema::hasTable('violation_tbl')) {
            return;
        }

        Schema::table('violation_tbl', function (Blueprint $table) {
           $table->string('evidence_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('violation_tbl')) {
            return;
        }

        Schema::table('violation_tbl', function (Blueprint $table) {
            $table->dropColumn('evidence_path');
        });
    }
};
