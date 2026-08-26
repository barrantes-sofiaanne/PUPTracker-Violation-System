<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('backup_history')) {
            return;
        }

        Schema::create('backup_history', function (Blueprint $table) {
            $table->id();
            $table->string('backup_name')->index();
            $table->string('status')->default('success'); // success | failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('destination')->default('Cloudflare R2')->nullable();
            $table->unsignedBigInteger('backup_size')->nullable();
            $table->unsignedInteger('file_count')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_history');
    }
};
