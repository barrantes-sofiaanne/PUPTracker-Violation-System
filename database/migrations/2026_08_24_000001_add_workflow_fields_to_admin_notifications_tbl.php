<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_notifications_tbl')) {
            return;
        }

        Schema::table('admin_notifications_tbl', function (Blueprint $table): void {
            if (! Schema::hasColumn('admin_notifications_tbl', 'title')) {
                $table->string('title')->nullable()->after('message');
            }
            if (! Schema::hasColumn('admin_notifications_tbl', 'target_type')) {
                $table->string('target_type')->nullable()->after('notification_type');
            }
            if (! Schema::hasColumn('admin_notifications_tbl', 'target_id')) {
                $table->unsignedBigInteger('target_id')->nullable()->after('target_type');
            }
            if (! Schema::hasColumn('admin_notifications_tbl', 'recipient_type')) {
                $table->string('recipient_type')->nullable()->after('target_id');
            }
            if (! Schema::hasColumn('admin_notifications_tbl', 'recipient_id')) {
                $table->unsignedBigInteger('recipient_id')->nullable()->after('recipient_type');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_notifications_tbl')) {
            return;
        }

        $columns = collect(['title', 'target_type', 'target_id', 'recipient_type', 'recipient_id'])
            ->filter(fn (string $column): bool => Schema::hasColumn('admin_notifications_tbl', $column))
            ->all();

        if ($columns !== []) {
            Schema::table('admin_notifications_tbl', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};