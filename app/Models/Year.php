<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Year extends Model
{
    protected $table = 'year_tbl';

    protected $primaryKey = 'year_id';

    public $timestamps = false;

    protected $fillable = [
        'year',
    ];

public function students(): HasMany
{
    return $this->hasMany(
        StudentInfo::class,
        'year_id',
        'year_id'
    );
}
}