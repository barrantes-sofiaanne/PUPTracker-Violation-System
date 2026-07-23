<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserManagementHistory extends Model
{
    use HasFactory;

    protected $table = 'user_management_histories';

    protected $primaryKey = 'history_id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'performed_by',
        'action',
        'details',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
