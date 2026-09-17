<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfa_trusted_devices', function (Blueprint $table) {
            $table->id();

            // Authentication guard this trusted device belongs to.
            // Supported values: admin, security.
            $table->string('guard', 20);

            // ID of the Admin/OSS Staff or Security account.
            $table->unsignedBigInteger('user_id');

            // SHA-256 hash of the random browser token.
            // The raw token is never stored in the database.
            $table->string('token_hash', 64)->unique();

            // Trusted-device validity window.
            $table->timestamp('expires_at');

            $table->timestamps();

            $table->index(
                ['guard', 'user_id'],
                'mfa_trusted_devices_guard_user_index'
            );

            $table->index(
                'expires_at',
                'mfa_trusted_devices_expires_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfa_trusted_devices');
    }
};