<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $email
 * @property string $password
 * @property string|null $remember_token
 * @property string|null $mfa_totp_secret
 * @property bool $mfa_totp_enabled
 */
class Security extends Authenticatable
{
    protected $table = 'security';

    public $timestamps = false;

    protected $fillable = [
        'email',
        'password',
        'remember_token',
        'mfa_totp_secret',
        'mfa_totp_enabled',
        'mfa_backup_codes',
        'mfa_backup_codes_used',
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

    public function securityInfo(): HasOne
    {
        return $this->hasOne(SecurityInfo::class, 'security_id', 'id');
    }

    public function securityProfile(): HasOne
    {
        return $this->hasOne(SecurityProfile::class, 'security_id', 'id');
    }
}