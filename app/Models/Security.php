<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Security extends Authenticatable
{
    protected $table = 'security';

    public $timestamps = false;

    protected $fillable = [
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
}