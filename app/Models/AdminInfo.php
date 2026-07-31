<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminInfo extends Model
{
    protected $table = 'admin_info_tbl';

    protected $primaryKey = 'admin_id';

    public $timestamps = false;

    public $incrementing = false;

    protected $fillable = [
        'admin_id',
        'Position',
        'firstname',
        'middlename',
        'lastname',
        'status_id',
        'role_id',
    ];
}