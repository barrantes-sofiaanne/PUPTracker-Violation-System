<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentStatus extends Model
{
    protected $table = 'student_status_tbl';

    protected $primaryKey = 'student_status_id';

    public $timestamps = false;

    protected $fillable = [
        'status_name',
    ];

    public function students(): HasMany
    {
        return $this->hasMany(
            StudentInfo::class,
            'student_status_id',
            'student_status_id'
        );
    }
}