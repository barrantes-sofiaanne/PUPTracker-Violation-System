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
    ];

    protected $hidden = [
        'password',
    ];

    public function getAuthPassword()
    {
        return $this->password;
    }
}