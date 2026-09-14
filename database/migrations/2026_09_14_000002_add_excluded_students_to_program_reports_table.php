<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record which students an administrator removed from the graduating preview
     * for a report.
     *
     * This is an audit/record-keeping column only. Removing a student from the
     * preview never modifies or deletes any student record — the exclusion list
     * simply narrows the cohort used for that one report.
     *
     * Follows the same guarded pattern as the sibling `additional_students`
     * migration: `program_reports` is a legacy table, so the table/column state
     * is checked before altering, and the whole up() is skipped when the table
     * is absent (otherwise `migrate:fresh` on the in-memory SQLite used by the
     * test suite would abort here and every later migration would silently
     * never run).
     */
    public function up(): void
    {
        if (! Schema::hasTable('program_reports')) {
            return;
        }

        if (! Schema::hasColumn('program_reports', 'excluded_students')) {
            Schema::table('program_reports', function (Blueprint $table): void {
                $table->json('excluded_students')->nullable()->after('additional_students');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('program_reports')) {
            return;
        }

        if (Schema::hasColumn('program_reports', 'excluded_students')) {
            Schema::table('program_reports', function (Blueprint $table): void {
                $table->dropColumn('excluded_students');
            });
        }
    }
};
