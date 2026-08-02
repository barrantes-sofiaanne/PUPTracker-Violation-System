<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityProfile extends Model
{
    protected $table = 'security_info';

    protected $primaryKey = 'security_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'security_id',
        'firstname',
        'middlename',
        'lastname',
        'position',
        'status_id',
        'role_id',
    ];
}