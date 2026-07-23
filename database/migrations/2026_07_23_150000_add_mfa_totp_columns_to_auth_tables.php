<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addMfaColumns('users_tbl');
        $this->addMfaColumns('admins');
        $this->addMfaColumns('security');
    }

    public function down(): void
    {
        $this->dropMfaColumns('users_tbl');
        $this->dropMfaColumns('admins');
        $this->dropMfaColumns('security');
    }

    private function addMfaColumns(string $table): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            if (!Schema::hasColumn($table, 'mfa_totp_secret')) {
                $blueprint->text('mfa_totp_secret')->nullable();
            }

            if (!Schema::hasColumn($table, 'mfa_totp_enabled')) {
                $blueprint->boolean('mfa_totp_enabled')->default(false);
            }
        });
    }

    private function dropMfaColumns(string $table): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            if (Schema::hasColumn($table, 'mfa_totp_enabled')) {
                $blueprint->dropColumn('mfa_totp_enabled');
            }

            if (Schema::hasColumn($table, 'mfa_totp_secret')) {
                $blueprint->dropColumn('mfa_totp_secret');
            }
        });
    }
};
