<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

class Admin extends Authenticatable
{
    protected $table = 'admins';

    public $timestamps = false;

    protected $fillable = [
        'username',
        'email',
        'password',
        'mfa_totp_secret',
        'mfa_totp_enabled',
    ];

    protected $hidden = [
        'password',
        'mfa_totp_secret',
    ];

    protected $casts = [
        'mfa_totp_enabled' => 'boolean',
        'reset_token_expires_at' => 'datetime',
    ];

    public function getAuthPassword()
    {
        return $this->password;
    }

    public function recorder(): BelongsTo
{
    return $this->belongsTo(
        Admin::class,
        'recorder_id',
        'id'
    );
}
    public function adminInfo(): HasOne
{
    return $this->hasOne(
        AdminInfo::class,
        'admin_id',
        'id'
    );
}

    public function isItAdministrator(): bool
    {
        try {
            if (!Schema::hasTable('admin_info_tbl')) {
                return false;
            }

            $adminInfo = $this->adminInfo;

            $candidates = [
                $adminInfo?->position,
                $adminInfo?->Position,
                $adminInfo?->designation,
                $adminInfo?->role,
                $adminInfo?->title,
            ];

            foreach ($candidates as $candidate) {
                if (is_string($candidate) && strcasecmp(trim($candidate), 'IT Administrator') === 0) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable $e) {
            // If there's any error checking admin info, default to false
            \Illuminate\Support\Facades\Log::warning('Error checking admin info', [
                'admin_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}