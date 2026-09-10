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
 * @property string|null $designated_office Office where Community/Campus Service is rendered (service sanctions only)
 * @property \Illuminate\Support\Carbon|null $starting_date   Date the student is authorized to start the service (service sanctions only)
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

    /**
     * Shown to the student IN PLACE of the Proof of Completion upload for
     * every sanction that is NOT a Community Service / Campus Service
     * sanction. Those sanctions are settled through a personal consultation
     * with the head of the Office of Student Services, so no document upload
     * is required from the student.
     */
    public const CONSULTATION_NOTICE = 'Please proceed to the Office of Student Services '
        . 'to process this disciplinary sanction as it is required to have a personal consultation '
        . 'with the head of the office.';

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
        'designated_office',
        'starting_date',
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
        'starting_date' => 'date',
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
    | A student who has satisfied a Community Service / Campus Service sanction
    | may upload a proof of completion. The admin then accepts it (record →
    | Completed) or rejects it with a reason (record stays open for the student
    | to resubmit).
    |
    | Sanctions that are NOT Community/Campus Service do NOT take a proof:
    | the student is instead directed to the Office of Student Services for a
    | personal consultation and the administrator closes the record afterwards.
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
    | Community Service / Campus Service assignment
    |--------------------------------------------------------------------------
    |
    | Community Service and Campus Service sanctions additionally carry a
    | designated office and a starting date (both set by the administrator at
    | approval time and read-only to the student). These helpers centralize the
    | "is this a service sanction?" decision so the controllers, the student
    | views, and the Proof of Completion table all agree.
    |--------------------------------------------------------------------------
    */

    /**
     * The sanction description text (e.g. "Community service 40 hours").
     */
    public function getSanctionTextAttribute(): string
    {
        return (string) optional($this->assignedSanction)->disciplinary_sanction;
    }

    /**
     * Whether this record is a Community Service sanction.
     */
    public function getIsCommunityServiceAttribute(): bool
    {
        return str_contains(strtolower($this->sanction_text), 'community');
    }

    /**
     * Whether this record is a Campus Service sanction.
     */
    public function getIsCampusServiceAttribute(): bool
    {
        return str_contains(strtolower($this->sanction_text), 'campus service');
    }

    /**
     * Whether this record is either a Community Service or Campus Service
     * sanction — the only types that use designated_office/starting_date.
     */
    public function getIsServiceSanctionAttribute(): bool
    {
        return $this->is_community_service || $this->is_campus_service;
    }

    /**
     * Whether the student must submit a Proof of Completion for this sanction.
     *
     * ONLY Community Service and Campus Service sanctions require a proof
     * upload. Every other sanction (suspension, counseling referral, ...) is
     * processed through a personal consultation at the Office of Student
     * Services and must therefore NOT offer the student a proof submission.
     */
    public function getRequiresProofOfCompletionAttribute(): bool
    {
        return $this->is_service_sanction;
    }

    /**
     * Required service hours parsed from the sanction text, when stated
     * (e.g. "Community service 40 hours" -> 40). Null when not stated.
     */
    public function getRequiredHoursAttribute(): ?int
    {
        if (! preg_match('/\b(\d{1,3})\s*(?:hours?|hrs?)\b/i', $this->sanction_text, $matches)) {
            return null;
        }

        $hours = (int) $matches[1];

        return $hours > 0 ? $hours : null;
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