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

        $programIds = collect(explode(',', (string) env('BACHELOR_PROGRAM_IDS', '')))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter(fn (int $id): bool => $id > 0)
            ->values();

        if ($programIds->isNotEmpty()) {
            DB::table('program_tbl')
                ->whereIn('program_id', $programIds->all())
                ->update(['degree_type' => 'Bachelor']);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('program_tbl') || ! Schema::hasColumn('program_tbl', 'degree_type')) {
            return;
        }

        $programIds = collect(explode(',', (string) env('BACHELOR_PROGRAM_IDS', '')))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter(fn (int $id): bool => $id > 0)
            ->values();

        if ($programIds->isNotEmpty()) {
            DB::table('program_tbl')
                ->whereIn('program_id', $programIds->all())
                ->where('degree_type', 'Bachelor')
                ->update(['degree_type' => null]);
        }
    }
};
