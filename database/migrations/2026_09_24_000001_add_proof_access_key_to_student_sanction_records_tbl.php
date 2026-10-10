<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_sanction_records_tbl')) {
            return;
        }

        if (! Schema::hasColumn(
            'student_sanction_records_tbl',
            'proof_access_key'
        )) {
            Schema::table('student_sanction_records_tbl', function (Blueprint $table): void {
                $table->string('proof_access_key', 64)
                    ->nullable()
                    ->unique()
                    ->after('proof_path');
            });
        }

        /*
         * Give existing proof records a random access key.
         */
        DB::table('student_sanction_records_tbl')
            ->whereNotNull('proof_path')
            ->where('proof_path', '!=', '')
            ->whereNull('proof_access_key')
            ->select('record_id')
            ->orderBy('record_id')
            ->chunkById(100, function ($records): void {
                foreach ($records as $record) {
                    DB::table('student_sanction_records_tbl')
                        ->where('record_id', $record->record_id)
                        ->whereNull('proof_access_key')
                        ->update([
                            'proof_access_key' => bin2hex(random_bytes(32)),
                        ]);
                }
            }, 'record_id');
    }

    public function down(): void
    {
        if (
            ! Schema::hasTable('student_sanction_records_tbl') ||
            ! Schema::hasColumn(
                'student_sanction_records_tbl',
                'proof_access_key'
            )
        ) {
            return;
        }

        Schema::table('student_sanction_records_tbl', function (Blueprint $table): void {
            $table->dropUnique(['proof_access_key']);
            $table->dropColumn('proof_access_key');
        });
    }
};