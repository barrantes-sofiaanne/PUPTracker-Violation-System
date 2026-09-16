<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('roles_tbl')) {
            return;
        }

        DB::table('roles_tbl')
            ->whereRaw('LOWER(roles_name) = ?', ['admin assistant'])
            ->update([
                'roles_name' => 'OSS Staff',
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('roles_tbl')) {
            return;
        }

        DB::table('roles_tbl')
            ->whereRaw('LOWER(roles_name) = ?', ['oss staff'])
            ->update([
                'roles_name' => 'Admin Assistant',
            ]);
    }
};