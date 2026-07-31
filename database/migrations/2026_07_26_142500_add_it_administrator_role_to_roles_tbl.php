<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('roles_tbl')) {
            return;
        }

        $exists = DB::table('roles_tbl')
            ->whereRaw('LOWER(roles_name) = ?', ['it administrator'])
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('roles_tbl')->insert([
            'roles_name' => 'IT Administrator',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('roles_tbl')) {
            return;
        }

        $roleIds = DB::table('roles_tbl')
            ->whereRaw('LOWER(roles_name) = ?', ['it administrator'])
            ->pluck('roles_id');

        if ($roleIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('users_tbl')) {
            $isInUse = DB::table('users_tbl')
                ->whereIn('roles_id', $roleIds)
                ->exists();

            if ($isInUse) {
                return;
            }
        }

        DB::table('roles_tbl')
            ->whereIn('roles_id', $roleIds)
            ->delete();
    }
};
