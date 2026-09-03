<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add HTTP-context columns to audit_logs.
 *
 * The centralized AuditLogService records who/what/when plus (where justified
 * for security accountability) the request IP and user agent. The original
 * audit_logs table only stored actor/action/module/description/created_at, so
 * these two nullable columns are added as the minimum required extension.
 *
 * Data minimization: only ip_address and user_agent are captured; full request
 * bodies are never logged. Columns are nullable and only populated when an
 * HTTP request context is passed to the service.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        if (! Schema::hasColumn('audit_logs', 'ip_address')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->string('ip_address', 64)->nullable()->after('description');
            });
        }

        if (! Schema::hasColumn('audit_logs', 'user_agent')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->string('user_agent', 500)->nullable()->after('ip_address');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        if (Schema::hasColumn('audit_logs', 'user_agent')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->dropColumn('user_agent');
            });
        }

        if (Schema::hasColumn('audit_logs', 'ip_address')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->dropColumn('ip_address');
            });
        }
    }
};
