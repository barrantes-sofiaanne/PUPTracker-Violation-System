<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Violation extends Model
{
    protected $table = 'violation_tbl';

    protected $primaryKey = 'violation_id';

    public $timestamps = false;

    protected $fillable = [
        'student_number',
        'violation_type',
        'violation_date',
        'description',
        'recorder_id',
    ];

    protected $casts = [
        'violation_date' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Student
    |--------------------------------------------------------------------------
    */

    public function student(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'student_number',
            'student_number'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Violation Type
    |--------------------------------------------------------------------------
    */

    public function violationType(): BelongsTo
    {
        return $this->belongsTo(
            ViolationType::class,
            'violation_type',
            'violation_type'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Recorder
    |--------------------------------------------------------------------------
    */

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'recorder_id',
            'user_id'
        );
    }
}