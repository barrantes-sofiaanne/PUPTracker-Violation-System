<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $request_id
 * @property string $student_number
 * @property int $violation_type_id
 * @property \Illuminate\Support\Carbon|null $request_date
 * @property bool $is_active
 * @property string|null $status            Pending | Approved | Declined
 * @property int|null $approved_by_admin_id
 * @property \Illuminate\Support\Carbon|null $approved_at
 */
class SanctionRequest extends Model
{
    use HasFactory;

    protected $table = 'sanction_requests_tbl';

    protected $primaryKey = 'request_id';

    public $timestamps = false;

  protected $fillable = [
    'student_number',
    'violation_type_id',
    'request_date',
    'is_active',
    'status',
    'approved_by_admin_id',
    'approved_at',
    'decline_reason',
'is_resubmission',
];

    protected $casts = [
        'is_active' => 'boolean',
        'request_date' => 'datetime',
        'approved_at' => 'datetime',
        'is_resubmission' => 'boolean',
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