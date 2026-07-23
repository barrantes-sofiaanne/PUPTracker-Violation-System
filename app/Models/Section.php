<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $table = 'section_tbl';

    protected $primaryKey = 'section_id';

    public $timestamps = false;

    protected $fillable = [
        'section_name',
    ];

    public function students(): HasMany
    {
        return $this->hasMany(
            StudentInfo::class,
            'section_id',
            'section_id'
        );
    }
}