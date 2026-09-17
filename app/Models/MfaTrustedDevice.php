<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class MfaTrustedDevice extends Model
{
    protected $table = 'mfa_trusted_devices';

    protected $fillable = [
        'guard',
        'user_id',
        'token_hash',
        'expires_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'expires_at' => 'datetime',
    ];

    /**
     * Scope to trusted devices that have not expired.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Determine whether this trusted device is still valid.
     */
    public function isActive(): bool
    {
        return $this->expires_at instanceof Carbon
            && $this->expires_at->isFuture();
    }
}