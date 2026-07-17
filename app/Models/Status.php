<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Status extends Model
{
    protected $table = 'status_tbl';

    protected $primaryKey = 'status_id';

    public $timestamps = false;

    protected $fillable = [
        'status_name',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'status_id', 'status_id');
    }
}