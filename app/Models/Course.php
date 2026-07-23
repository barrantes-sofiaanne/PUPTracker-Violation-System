<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $table = 'program_tbl';

    protected $primaryKey = 'program_id';

    public $timestamps = false;

    protected $fillable = [
        'program_name',
    ];

    public function getCourseNameAttribute(): string
    {
        return (string) $this->program_name;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'program_id', 'program_id');
    }
}