<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $table = 'roles_tbl';

    protected $primaryKey = 'roles_id';

    public $timestamps = false;

    protected $fillable = [
        'roles_name',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'roles_id', 'roles_id');
    }
}