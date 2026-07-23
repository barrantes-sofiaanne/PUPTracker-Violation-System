<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\StudentStatus;

class StudentInfo extends Model
{
    protected $table = 'student_info_tbl';

    protected $primaryKey = 'student_id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'program_id',
        'year_id',
        'section_id',
        'student_status_id',
        'ladderized'
    ];

public function user()
{
    return $this->belongsTo(User::class, 'user_id');
}

public function program()
{
    return $this->belongsTo(Program::class, 'program_id');
}

public function year()
{
    return $this->belongsTo(Year::class, 'year_id');
}

public function section()
{
    return $this->belongsTo(Section::class, 'section_id');
}

public function studentStatus()
{
    return $this->belongsTo(StudentStatus::class, 'student_status_id');
}
}