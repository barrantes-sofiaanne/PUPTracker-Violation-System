<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
}