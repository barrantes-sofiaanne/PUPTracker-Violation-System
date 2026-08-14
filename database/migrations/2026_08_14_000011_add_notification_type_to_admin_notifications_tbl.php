<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a minimal notification_type column to the legacy admin_notifications_tbl
     * so system-generated notifications (e.g. system_email_failure) can be
     * distinguished from regular ones without redesigning the table.
     */
    public function up(): void
    {
        if (! Schema::hasTable('admin_notifications_tbl')) {
            return;
        }

        if (Schema::hasColumn('admin_notifications_tbl', 'notification_type')) {
            return;
        }

        Schema::table('admin_notifications_tbl', function (Blueprint $table) {
            $table->string('notification_type')->nullable()->after('message');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_notifications_tbl')) {
            return;
        }

        if (! Schema::hasColumn('admin_notifications_tbl', 'notification_type')) {
            return;
        }

        Schema::table('admin_notifications_tbl', function (Blueprint $table) {
            $table->dropColumn('notification_type');
        });
    }
};
