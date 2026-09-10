<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `base_backup_name` to backup_history.
 *
 * `parent_backup_id` already records the recovery-chain parent as an FK to a
 * backup_history row. That is ideal when the base FULL is known to PUPTracker,
 * but the external Railway backup job reports incrementals by NAME and may not
 * have a resolvable local id (e.g. reporting out of order, or the base was
 * recorded under a legacy schema). `base_backup_name` stores the human-readable
 * name of the FULL the incremental depends on, which is what an operator needs
 * to select the correct restore base.
 *
 * For FULL / daily_snapshot backups this column stays NULL.
 *
 * Purely additive and reversible; existing rows are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('backup_history')) {
            return;
        }

        Schema::table('backup_history', function (Blueprint $table): void {
            if (! Schema::hasColumn('backup_history', 'base_backup_name')) {
                $table->string('base_backup_name', 255)->nullable()->index()->after('parent_backup_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('backup_history')) {
            return;
        }

        Schema::table('backup_history', function (Blueprint $table): void {
            if (Schema::hasColumn('backup_history', 'base_backup_name')) {
                $table->dropColumn('base_backup_name');
            }
        });
    }
};
