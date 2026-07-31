<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityInfo extends Model
{
    protected $table = 'security_info_tbl';

    protected $fillable = [
        'security_id',
        'firstname',
        'middlename',
        'lastname',
        'contact_number',
        'address',
    ];
}
