<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds proof-of-completion submission to student sanction records.
 *
 * When a student has satisfied a disciplinary sanction, they submit a proof
 * of completion (an uploaded file plus optional notes). The administrator then
 * either accepts the proof (marking the sanction Completed) or rejects it with
 * a reason so the student can resubmit.
 *
 * Columns added here (all nullable so existing rows are unaffected):
 *   - proof_path           stored file path of the submitted proof
 *   - proof_original_name  original uploaded filename (for display)
 *   - proof_submitted_at   when the student submitted the proof
 *   - proof_notes          student's optional note accompanying the proof
 *   - completion_reviewed_by_admin_id  admin who accepted/rejected the proof
 *   - completion_reviewed_at           when the proof was reviewed
 *   - completion_remarks               reject reason (or acceptance note)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_sanction_records_tbl')) {
            return;
        }

        Schema::table('student_sanction_records_tbl', function (Blueprint $table): void {
            if (! Schema::hasColumn('student_sanction_records_tbl', 'proof_path')) {
                $table->string('proof_path', 500)->nullable()->after('date_completed');
            }
            if (! Schema::hasColumn('student_sanction_records_tbl', 'proof_original_name')) {
                $table->string('proof_original_name', 255)->nullable()->after('proof_path');
            }
            if (! Schema::hasColumn('student_sanction_records_tbl', 'proof_submitted_at')) {
                $table->timestamp('proof_submitted_at')->nullable()->after('proof_original_name');
            }
            if (! Schema::hasColumn('student_sanction_records_tbl', 'proof_notes')) {
                $table->text('proof_notes')->nullable()->after('proof_submitted_at');
            }
            if (! Schema::hasColumn('student_sanction_records_tbl', 'completion_reviewed_by_admin_id')) {
                $table->unsignedBigInteger('completion_reviewed_by_admin_id')->nullable()->after('proof_notes');
            }
            if (! Schema::hasColumn('student_sanction_records_tbl', 'completion_reviewed_at')) {
                $table->timestamp('completion_reviewed_at')->nullable()->after('completion_reviewed_by_admin_id');
            }
            if (! Schema::hasColumn('student_sanction_records_tbl', 'completion_remarks')) {
                $table->text('completion_remarks')->nullable()->after('completion_reviewed_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('student_sanction_records_tbl')) {
            return;
        }

        $columns = collect([
            'proof_path',
            'proof_original_name',
            'proof_submitted_at',
            'proof_notes',
            'completion_reviewed_by_admin_id',
            'completion_reviewed_at',
            'completion_remarks',
        ])->filter(fn (string $column): bool => Schema::hasColumn('student_sanction_records_tbl', $column))
            ->all();

        if ($columns !== []) {
            Schema::table('student_sanction_records_tbl', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
