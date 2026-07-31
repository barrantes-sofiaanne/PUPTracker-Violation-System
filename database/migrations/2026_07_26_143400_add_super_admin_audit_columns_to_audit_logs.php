<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('audit_logs')) {
            return;
        }

        $needsActorType = !Schema::hasColumn('audit_logs', 'actor_type');
        $needsActorId = !Schema::hasColumn('audit_logs', 'actor_id');
        $needsAction = !Schema::hasColumn('audit_logs', 'action');
        $needsModule = !Schema::hasColumn('audit_logs', 'module');
        $needsDescription = !Schema::hasColumn('audit_logs', 'description');

        if (
            $needsActorType ||
            $needsActorId ||
            $needsAction ||
            $needsModule ||
            $needsDescription
        ) {
            Schema::table('audit_logs', function (Blueprint $table) use (
                $needsActorType,
                $needsActorId,
                $needsAction,
                $needsModule,
                $needsDescription
            ): void {
                if ($needsActorType) {
                    $table->string('actor_type')->nullable()->after('created_at');
                }

                if ($needsActorId) {
                    $table->unsignedBigInteger('actor_id')->nullable()->after('actor_type');
                }

                if ($needsAction) {
                    $table->string('action')->nullable()->after('actor_id');
                }

                if ($needsModule) {
                    $table->string('module')->nullable()->after('action');
                }

                if ($needsDescription) {
                    $table->text('description')->nullable()->after('module');
                }
            });
        }

        if (
            Schema::hasColumn('audit_logs', 'actor_type') &&
            Schema::hasColumn('audit_logs', 'user_role')
        ) {
            DB::table('audit_logs')
                ->whereNull('actor_type')
                ->whereNotNull('user_role')
                ->update([
                    'actor_type' => DB::raw('user_role'),
                ]);
        }

        if (
            Schema::hasColumn('audit_logs', 'actor_id') &&
            Schema::hasColumn('audit_logs', 'user_id')
        ) {
            DB::table('audit_logs')
                ->whereNull('actor_id')
                ->whereNotNull('user_id')
                ->update([
                    'actor_id' => DB::raw('user_id'),
                ]);
        }

        if (
            Schema::hasColumn('audit_logs', 'action') &&
            Schema::hasColumn('audit_logs', 'action_type')
        ) {
            DB::table('audit_logs')
                ->whereNull('action')
                ->whereNotNull('action_type')
                ->update([
                    'action' => DB::raw('action_type'),
                ]);
        }

        if (
            Schema::hasColumn('audit_logs', 'module') &&
            Schema::hasColumn('audit_logs', 'table_affected')
        ) {
            DB::table('audit_logs')
                ->whereNull('module')
                ->whereNotNull('table_affected')
                ->update([
                    'module' => DB::raw('table_affected'),
                ]);
        }

        if (
            Schema::hasColumn('audit_logs', 'description') &&
            Schema::hasColumn('audit_logs', 'new_data')
        ) {
            DB::table('audit_logs')
                ->whereNull('description')
                ->whereNotNull('new_data')
                ->update([
                    'description' => DB::raw('new_data'),
                ]);
        }

        if (
            Schema::hasColumn('audit_logs', 'description') &&
            Schema::hasColumn('audit_logs', 'old_data')
        ) {
            DB::table('audit_logs')
                ->whereNull('description')
                ->whereNotNull('old_data')
                ->update([
                    'description' => DB::raw('old_data'),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left empty to avoid dropping columns that may be required by other environments.
    }
};
