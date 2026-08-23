<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('program_reports')) {
            return;
        }

        Schema::table('program_reports', function (Blueprint $table): void {
            if (! Schema::hasColumn('program_reports', 'report_mode')) {
                $table->string('report_mode')->default('regular')->after('report_type');
            }
            if (! Schema::hasColumn('program_reports', 'degree_type')) {
                $table->string('degree_type')->nullable()->after('report_mode');
            }
            if (! Schema::hasColumn('program_reports', 'graduating_year')) {
                $table->unsignedTinyInteger('graduating_year')->nullable()->after('degree_type');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('program_reports')) {
            return;
        }

        $columns = collect(['report_mode', 'degree_type', 'graduating_year'])
            ->filter(fn (string $column): bool => Schema::hasColumn('program_reports', $column))
            ->all();

        if ($columns !== []) {
            Schema::table('program_reports', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};