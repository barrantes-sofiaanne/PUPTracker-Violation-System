<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single-use password reset tokens.
 *
 * Tokens are stored hashed; the plaintext token is only ever sent to the
 * account holder via email. Each row is bound to a guard + account primary
 * key so the recovery attempt is identified by the token alone.
 */
class PasswordReset extends Model
{
    protected $table = 'password_resets';

    public $timestamps = false;

    protected $fillable = [
        'guard',
        'account_id',
        'token_hash',
        'expires_at',
        'used_at',
        'created_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function scopeUnused(Builder $query): Builder
    {
        return $query->whereNull('used_at');
    }

    public function scopeExpiresAfter(Builder $query, \DateTimeInterface $now): Builder
    {
        return $query->where('expires_at', '>', $now);
    }
}
