<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentSanctionRecord extends Model
{
    use HasFactory;

    protected $table = 'student_sanction_records_tbl';

    protected $primaryKey = 'record_id';

    public $timestamps = false;

    protected $fillable = [
        'student_number',
        'violation_id',
        'assigned_sanction_id',
        'status',
        'date_assigned',
        'completed_at'
    ];

    protected $casts = [
        'date_assigned' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function violation(): BelongsTo
    {
        return $this->belongsTo(
            Violation::class,
            'violation_id',
            'violation_id'
        );
    }

    public function assignedSanction(): BelongsTo
    {
        return $this->belongsTo(
            DisciplinarySanction::class,
            'assigned_sanction_id',
            'disciplinary_sanction_id'
        );
    }
}