<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('violation_evidence_tbl')) {
            return;
        }

        if (! Schema::hasColumn('violation_evidence_tbl', 'access_key')) {
            Schema::table('violation_evidence_tbl', function (Blueprint $table): void {
                $table->string('access_key', 64)
                    ->nullable()
                    ->unique()
                    ->after('evidence_id');
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('violation_evidence_tbl') &&
            Schema::hasColumn('violation_evidence_tbl', 'access_key')
        ) {
            Schema::table('violation_evidence_tbl', function (Blueprint $table): void {
                $table->dropColumn('access_key');
            });
        }
    }
};