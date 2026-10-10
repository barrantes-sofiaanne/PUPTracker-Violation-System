<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('violation_evidence_tbl')) {
            return;
        }

        if (! Schema::hasColumn('violation_evidence_tbl', 'access_key')) {
            return;
        }

        DB::table('violation_evidence_tbl')
            ->where(function ($query): void {
                $query->whereNull('access_key')
                    ->orWhere('access_key', '');
            })
            ->orderBy('evidence_id')
            ->chunkById(100, function ($evidences): void {
                foreach ($evidences as $evidence) {
                    DB::table('violation_evidence_tbl')
                        ->where('evidence_id', $evidence->evidence_id)
                        ->update([
                            'access_key' => bin2hex(random_bytes(32)),
                        ]);
                }
            }, 'evidence_id');
    }

    public function down(): void
    {
        // Existing access keys should not be removed on rollback.
    }
};