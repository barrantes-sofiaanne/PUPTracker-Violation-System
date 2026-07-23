public function student()
{
    return $this->belongsTo(
        Student::class,
        'student_id',
        'student_id'
    );
}

public function violationType()
{
    return $this->belongsTo(
        ViolationType::class,
        'violation_type_id',
        'violation_type_id'
    );
}

public function sanction()
{
    return $this->belongsTo(
        Sanction::class,
        'sanction_id',
        'sanction_id'
    );
}

public function recordedBy()
{
    return $this->belongsTo(
        User::class,
        'recorded_by',
        'id'
    );
}