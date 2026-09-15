<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PART 1 — ADMIN ASSISTANT role.
 *
 * The Admin Assistant is an "admin" account (it lives in `admins`, uses the
 * `admin` guard and the Admin module UI) that must have the SAME OPERATIONAL
 * CAPABILITIES as Security Personnel — student lookup, violation recording, the
 * evidence/photo workflow, violation history and their notifications.
 *
 * WHY A ROLE ROW AND NOT A NEW GUARD
 * ----------------------------------
 * There is no permission table in this project: authorisation IS the guard.
 * Creating a second authentication system for the assistant is explicitly
 * forbidden by the brief, so the role is expressed the ONLY way the existing
 * architecture expresses roles — a `roles_tbl` row reached from
 * `admin_info_tbl.role_id`. That is exactly how `IT Administrator` was added
 * (see 2026_07_26_142500_add_it_administrator_role_to_roles_tbl.php).
 *
 * The role name is the single source of truth: `Admin::isAdminAssistant()`
 * matches it case-insensitively, and `App\Support\Access\SecurityAccess`
 * decides which accounts may cross into the security-operational surfaces.
 *
 * The insert is guarded and idempotent, and `down()` refuses to delete a role
 * that is still referenced by an account, so no data is ever orphaned.
 */
return new class extends Migration
{
    public const ROLE_NAME = 'Admin Assistant';

    public function up(): void
    {
        if (! Schema::hasTable('roles_tbl')) {
            return;
        }

        $exists = DB::table('roles_tbl')
            ->whereRaw('LOWER(roles_name) = ?', [strtolower(self::ROLE_NAME)])
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('roles_tbl')->insert([
            'roles_name' => self::ROLE_NAME,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles_tbl')) {
            return;
        }

        $roleIds = DB::table('roles_tbl')
            ->whereRaw('LOWER(roles_name) = ?', [strtolower(self::ROLE_NAME)])
            ->pluck('roles_id');

        if ($roleIds->isEmpty()) {
            return;
        }

        // Never delete a role that is still assigned to an account.
        if (Schema::hasTable('admin_info_tbl')) {
            $inUse = DB::table('admin_info_tbl')
                ->whereIn('role_id', $roleIds)
                ->exists();

            if ($inUse) {
                return;
            }
        }

        DB::table('roles_tbl')
            ->whereIn('roles_id', $roleIds)
            ->delete();
    }
};
