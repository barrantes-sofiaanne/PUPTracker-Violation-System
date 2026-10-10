<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ViolationCorrectionRequest extends Model
{
    protected $table = 'violation_correction_requests';

    protected $primaryKey = 'request_id';

    protected $fillable = [
        'violation_id',
        'student_number',

        'requested_violation_type_id',
        'requested_violation_date',
        'requested_description',

        'reason',

        'status',

        'reviewed_by_admin_id',
        'reviewed_at',
        'review_remarks',
        'request_type',
        'deletion_evidence',
        'original_violation_id',
    ];

    protected $casts = [
        'requested_violation_date' => 'datetime',
        'reviewed_at' => 'datetime',
        'deletion_evidence' => 'array',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const TYPE_CORRECTION = 'correction';
    public const TYPE_DELETION = 'deletion';

    public function isDeletionRequest(): bool
    {
        return ($this->request_type ?? self::TYPE_CORRECTION) === self::TYPE_DELETION;
    }

    public function violation(): BelongsTo
    {
        return $this->belongsTo(
            Violation::class,
            'violation_id',
            'violation_id'
        );
    }

    public function requestedViolationType(): BelongsTo
    {
        return $this->belongsTo(
            ViolationType::class,
            'requested_violation_type_id',
            'violation_type_id'
        );
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(
            Admin::class,
            'reviewed_by_admin_id',
            'id'
        );
    }
}