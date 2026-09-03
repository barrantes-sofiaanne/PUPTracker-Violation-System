<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a "remember token" column to the privileged (admin/security) auth tables.
 *
 * The 30-day "Remember me" feature for the Administrator and Security modules
 * uses Laravel's session-guard recaller cookie, which stores
 * `{user_id}|{remember_token}|{password-hash-HMAC}` in an encrypted cookie.
 * When a visitor returns with that cookie but no live session, the guard looks
 * the user up by ID and compares the stored token (EloquentUserProvider
 * retrieveByToken) before restoring the session.
 *
 * These tables previously had no remember_token column, so this migration adds
 * one (nullable, 100 chars — the standard Laravel size for a 60-char random
 * token plus headroom). Columns are only touched when the table exists.
 *
 * Security note: the remember token is deliberately rotated on explicit logout
 * (SessionGuard::logout) and on password reset (see ForgotPasswordController),
 * so a "Remember me" cookie cannot survive a password change.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addRememberToken('admins');
        $this->addRememberToken('security');
    }

    public function down(): void
    {
        $this->dropRememberToken('admins');
        $this->dropRememberToken('security');
    }

    private function addRememberToken(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (! Schema::hasColumn($table, 'remember_token')) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('remember_token', 100)->nullable();
            });
        }
    }

    private function dropRememberToken(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (Schema::hasColumn($table, 'remember_token')) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('remember_token');
            });
        }
    }
};
