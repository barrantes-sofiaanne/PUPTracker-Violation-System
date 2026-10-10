<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('violation_tbl', function (Blueprint $table) {
            if (!Schema::hasColumn('violation_tbl', 'identity_verified')) {
                $table->boolean('identity_verified')
                    ->default(false)
                    ->after('description');
            }

            if (!Schema::hasColumn('violation_tbl', 'identity_verified_at')) {
                $table->timestamp('identity_verified_at')
                    ->nullable()
                    ->after('identity_verified');
            }

            if (!Schema::hasColumn('violation_tbl', 'identity_verified_by_type')) {
                $table->string('identity_verified_by_type', 30)
                    ->nullable()
                    ->after('identity_verified_at');
            }

            if (!Schema::hasColumn('violation_tbl', 'identity_verified_by_id')) {
                $table->integer('identity_verified_by_id')
                    ->nullable()
                    ->after('identity_verified_by_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('violation_tbl', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('violation_tbl', 'identity_verified')) {
                $columns[] = 'identity_verified';
            }

            if (Schema::hasColumn('violation_tbl', 'identity_verified_at')) {
                $columns[] = 'identity_verified_at';
            }

            if (Schema::hasColumn('violation_tbl', 'identity_verified_by_type')) {
                $columns[] = 'identity_verified_by_type';
            }

            if (Schema::hasColumn('violation_tbl', 'identity_verified_by_id')) {
                $columns[] = 'identity_verified_by_id';
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};