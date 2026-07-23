<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_management_histories')) {
            return;
        }

        Schema::create('user_management_histories', function (Blueprint $table) {
            $table->id('history_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->string('action');
            $table->text('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_management_histories');
    }
};
