<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends backup_history with the fields required to support the backup policy:
 *
 *   - backup_type          full | incremental | daily_snapshot (see BackupHistory)
 *   - parent_backup_id     recovery-chain parent (the base FULL for an incremental)
 *   - checksum / checksum_algorithm
 *   - verified_at          when the object integrity check passed
 *   - storage_path         private object key inside the R2/backup disk
 *   - backup_size          already present; file_size is an alias of it
 *   - file_count           already present
 *   - binlog metadata      file/position start/end (TRUE incremental only)
 *   - error_message        already present
 *
 * This migration is additive and reversible; it never destroys existing rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('backup_history')) {
            return;
        }

        Schema::table('backup_history', function (Blueprint $table) {
            if (! Schema::hasColumn('backup_history', 'backup_type')) {
                $table->string('backup_type')->nullable()->index()->after('backup_name');
            }
            if (! Schema::hasColumn('backup_history', 'parent_backup_id')) {
                $table->unsignedBigInteger('parent_backup_id')->nullable()->index()->after('backup_type');
            }
            if (! Schema::hasColumn('backup_history', 'checksum')) {
                $table->string('checksum', 64)->nullable()->after('backup_size');
            }
            if (! Schema::hasColumn('backup_history', 'checksum_algorithm')) {
                $table->string('checksum_algorithm', 32)->nullable()->default('SHA-256')->after('checksum');
            }
            if (! Schema::hasColumn('backup_history', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('checksum_algorithm');
            }
            if (! Schema::hasColumn('backup_history', 'storage_path')) {
                $table->string('storage_path', 500)->nullable()->after('file_count');
            }
            if (! Schema::hasColumn('backup_history', 'binlog_file_start')) {
                $table->string('binlog_file_start', 255)->nullable()->after('storage_path');
            }
            if (! Schema::hasColumn('backup_history', 'binlog_position_start')) {
                $table->unsignedBigInteger('binlog_position_start')->nullable()->after('binlog_file_start');
            }
            if (! Schema::hasColumn('backup_history', 'binlog_file_end')) {
                $table->string('binlog_file_end', 255)->nullable()->after('binlog_position_start');
            }
            if (! Schema::hasColumn('backup_history', 'binlog_position_end')) {
                $table->unsignedBigInteger('binlog_position_end')->nullable()->after('binlog_file_end');
            }
            if (! Schema::hasColumn('backup_history', 'binlog_timestamp_start')) {
                $table->timestamp('binlog_timestamp_start')->nullable()->after('binlog_position_end');
            }
            if (! Schema::hasColumn('backup_history', 'binlog_timestamp_end')) {
                $table->timestamp('binlog_timestamp_end')->nullable()->after('binlog_timestamp_start');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('backup_history')) {
            return;
        }

        Schema::table('backup_history', function (Blueprint $table) {
            foreach ([
                'backup_type',
                'parent_backup_id',
                'checksum',
                'checksum_algorithm',
                'verified_at',
                'storage_path',
                'binlog_file_start',
                'binlog_position_start',
                'binlog_file_end',
                'binlog_position_end',
                'binlog_timestamp_start',
                'binlog_timestamp_end',
            ] as $column) {
                if (Schema::hasColumn('backup_history', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
