<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications_tbl')) {
            return;
        }

        Schema::table('notifications_tbl', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications_tbl', 'title')) {
                $table->string('title')->nullable()->after('message');
            }
            if (! Schema::hasColumn('notifications_tbl', 'target_type')) {
                $table->string('target_type')->nullable()->after('notification_type');
            }
            if (! Schema::hasColumn('notifications_tbl', 'target_id')) {
                $table->unsignedBigInteger('target_id')->nullable()->after('target_type');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('notifications_tbl')) {
            return;
        }

        $columns = collect(['title', 'target_type', 'target_id'])
            ->filter(fn (string $column): bool => Schema::hasColumn('notifications_tbl', $column))
            ->all();

        if ($columns !== []) {
            Schema::table('notifications_tbl', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
