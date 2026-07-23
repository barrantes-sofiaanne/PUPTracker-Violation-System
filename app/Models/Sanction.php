public function violationType()
{
    return $this->belongsTo(
        ViolationType::class,
        'violation_type_id',
        'violation_type_id'
    );
}