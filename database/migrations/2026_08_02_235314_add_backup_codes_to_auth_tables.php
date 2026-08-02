<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addBackupCodeColumns('users_tbl');
        $this->addBackupCodeColumns('admins');
        $this->addBackupCodeColumns('security');
    }

    public function down(): void
    {
        $this->dropBackupCodeColumns('users_tbl');
        $this->dropBackupCodeColumns('admins');
        $this->dropBackupCodeColumns('security');
    }

    private function addBackupCodeColumns(string $table): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            if (!Schema::hasColumn($table, 'mfa_backup_codes')) {
                $blueprint->json('mfa_backup_codes')->nullable()->comment('Array of hashed backup codes for account recovery');
            }

            if (!Schema::hasColumn($table, 'mfa_backup_codes_used')) {
                $blueprint->json('mfa_backup_codes_used')->nullable()->default('[]')->comment('Track which backup codes have been used');
            }
        });
    }

    private function dropBackupCodeColumns(string $table): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            if (Schema::hasColumn($table, 'mfa_backup_codes_used')) {
                $blueprint->dropColumn('mfa_backup_codes_used');
            }

            if (Schema::hasColumn($table, 'mfa_backup_codes')) {
                $blueprint->dropColumn('mfa_backup_codes');
            }
        });
    }
};
