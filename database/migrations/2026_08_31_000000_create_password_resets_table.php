<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-use, short-lived password reset tokens.
     *
     * The reset token itself is never stored in plaintext — only its hash.
     * The record is bound to the account's primary key (account_id), NOT the
     * email, so the recovery attempt is identified by the token alone.
     */
    public function up(): void
    {
        Schema::create('password_resets', function (Blueprint $table) {
            $table->id();
            $table->string('guard', 20);
            $table->unsignedBigInteger('account_id');
            $table->string('token_hash', 255);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['guard', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_resets');
    }
};
