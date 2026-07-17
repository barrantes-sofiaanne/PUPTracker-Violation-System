<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SanctionRequest extends Model
{
    use HasFactory;

    protected $table = 'sanction_requests_tbl';

    protected $primaryKey = 'request_id';

    public $timestamps = false;

  protected $fillable = [
    'student_number',
    'violation_type_id'
];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function violationType(): BelongsTo
    {
        return $this->belongsTo(
            ViolationType::class,
            'violation_type_id',
            'violation_type_id'
        );
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'student_number',
            'student_number'
        );
    }
}