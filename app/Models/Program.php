<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    protected $table = 'program_tbl';

    protected $primaryKey = 'program_id';

    public $timestamps = false;

    protected $fillable = [
        'program_name',
    ];

    public function students(): HasMany
    {
        return $this->hasMany(
            StudentInfo::class,
            'program_id',
            'program_id'
        );
    }
}