<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_info_tbl') || ! Schema::hasColumn('student_info_tbl', 'program_id')) {
            return;
        }

        Schema::table('student_info_tbl', function (Blueprint $table): void {
            $table->integer('program_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('student_info_tbl') || ! Schema::hasColumn('student_info_tbl', 'program_id')) {
            return;
        }

        // Do not attempt to restore NOT NULL if imported records now have no program.
        $hasNullPrograms = DB::table('student_info_tbl')
            ->whereNull('program_id')
            ->exists();

        if ($hasNullPrograms) {
            throw new RuntimeException(
                'Cannot roll back make_student_program_nullable: student_info_tbl contains NULL program_id values. Assign a valid program to those records before rolling back.'
            );
        }

        Schema::table('student_info_tbl', function (Blueprint $table): void {
            $table->integer('program_id')->nullable(false)->change();
        });
    }
};
