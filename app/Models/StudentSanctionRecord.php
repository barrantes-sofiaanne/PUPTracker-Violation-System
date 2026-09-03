<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $record_id
 * @property string|null $student_number
 * @property int|null $violation_id
 * @property int|null $assigned_sanction_id
 * @property int|null $assigned_by_admin_id
 * @property string|null $status            Pending | In Progress | Overdue | Completed
 * @property \Illuminate\Support\Carbon|null $deadline_date
 * @property \Illuminate\Support\Carbon|null $date_assigned
 * @property \Illuminate\Support\Carbon|null $date_completed
 * @property string|null $proof_path
 * @property string|null $proof_original_name
 * @property \Illuminate\Support\Carbon|null $proof_submitted_at
 * @property string|null $proof_notes
 * @property int|null $completion_reviewed_by_admin_id
 * @property \Illuminate\Support\Carbon|null $completion_reviewed_at
 * @property string|null $completion_remarks
 */
class StudentSanctionRecord extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'Pending';
    public const STATUS_IN_PROGRESS = 'In Progress';
    public const STATUS_OVERDUE = 'Overdue';
    public const STATUS_COMPLETED = 'Completed';

    protected $table = 'student_sanction_records_tbl';

    protected $primaryKey = 'record_id';

    public $timestamps = false;

    protected $fillable = [
        'student_number',
        'violation_id',
        'assigned_sanction_id',
        'assigned_by_admin_id',
        'status',
        'deadline_date',
        'date_assigned',
        'date_completed',
        'proof_path',
        'proof_original_name',
        'proof_submitted_at',
        'proof_notes',
        'completion_reviewed_by_admin_id',
        'completion_reviewed_at',
        'completion_remarks',
    ];

    protected $casts = [
        'deadline_date' => 'datetime',
        'date_assigned' => 'datetime',
        'date_completed' => 'datetime',
        'proof_submitted_at' => 'datetime',
        'completion_reviewed_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Proof-of-completion workflow
    |--------------------------------------------------------------------------
    |
    | A student who has satisfied a pending sanction may upload a proof of
    | completion. The admin then accepts it (record → Completed) or rejects it
    | with a reason (record stays open for the student to resubmit).
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the sanction is still open (not yet completed). A student may
     * submit a proof for any open sanction (Pending, In Progress, Overdue).
     */
    public function getIsOpenAttribute(): bool
    {
        return strcasecmp((string) $this->status, self::STATUS_COMPLETED) !== 0;
    }

    /**
     * Whether a proof has been submitted and is awaiting admin review on an
     * open (non-completed) sanction.
     */
    public function getHasPendingProofAttribute(): bool
    {
        return $this->proof_path !== null
            && $this->proof_path !== ''
            && $this->is_open;
    }

    public function getProofReviewerAttribute()
    {
        if (! $this->relationLoaded('completionReviewer') && $this->completion_reviewed_by_admin_id) {
            $this->load('completionReviewer');
        }

        return $this->completionReviewer;
    }

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

    public function student(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'student_number',
            'student_number'
        );
    }

    public function completionReviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'completion_reviewed_by_admin_id', 'id');
    }

    /**
     * Full history of proof-of-completion submissions (newest first).
     */
    public function proofs(): HasMany
    {
        return $this->hasMany(SanctionProof::class, 'record_id', 'record_id')
            ->orderByDesc('submitted_at')
            ->orderByDesc('proof_id');
    }
}