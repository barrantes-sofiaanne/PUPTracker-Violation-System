<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_management_histories')) {
            return;
        }

        if (! Schema::hasColumn('user_management_histories', 'changes')) {
            Schema::table('user_management_histories', function (Blueprint $table) {
                $table->json('changes')->nullable()->after('details');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_management_histories')
            && Schema::hasColumn('user_management_histories', 'changes')) {
            Schema::table('user_management_histories', function (Blueprint $table) {
                $table->dropColumn('changes');
            });
        }
    }
};
