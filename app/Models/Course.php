<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $table = 'course_tbl';

    protected $primaryKey = 'course_id';

    public $timestamps = false;

    protected $fillable = [
        'course_name',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'course_id', 'course_id');
    }
}