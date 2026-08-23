<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('program_tbl') || ! Schema::hasColumn('program_tbl', 'degree_type')) {
            return;
        }

        // BSIT classification is an explicit reference-data decision. Other
        // programs remain unchanged until their official classification is confirmed.
        DB::table('program_tbl')
            ->where('program_name', 'BSIT')
            ->update(['degree_type' => 'Bachelor']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('program_tbl') || ! Schema::hasColumn('program_tbl', 'degree_type')) {
            return;
        }

        DB::table('program_tbl')
            ->where('program_name', 'BSIT')
            ->where('degree_type', 'Bachelor')
            ->update(['degree_type' => null]);
    }
};
