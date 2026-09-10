<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the Community Service / Campus Service assignment fields to the
 * authoritative student sanction assignment record.
 *
 * When an administrator approves a sanction request whose resolved sanction is
 * a Community Service or Campus Service sanction, they must designate the
 * office where the student will render the service and the date the student is
 * authorized to start. Both values live on the SAME row that already backs the
 * admin sanction record, the student sanction details, and the Proof of
 * Completion table, so every surface reads one server-side source of truth.
 *
 * Columns added here (both nullable so existing rows and all other sanction
 * types are unaffected):
 *   - designated_office  office where the service is rendered
 *   - starting_date      date the student is authorized to start
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_sanction_records_tbl')) {
            return;
        }

        Schema::table('student_sanction_records_tbl', function (Blueprint $table): void {
            if (! Schema::hasColumn('student_sanction_records_tbl', 'designated_office')) {
                $table->string('designated_office', 255)->nullable()->after('deadline_date');
            }
            if (! Schema::hasColumn('student_sanction_records_tbl', 'starting_date')) {
                $table->date('starting_date')->nullable()->after('designated_office');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('student_sanction_records_tbl')) {
            return;
        }

        $columns = collect(['designated_office', 'starting_date'])
            ->filter(fn (string $column): bool => Schema::hasColumn('student_sanction_records_tbl', $column))
            ->all();

        if ($columns !== []) {
            Schema::table('student_sanction_records_tbl', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
