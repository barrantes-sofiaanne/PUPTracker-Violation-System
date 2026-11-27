<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Admin;
use App\Models\Security;
use App\Models\ViolationType;

/**
 * @property int $violation_id
 * @property string|null $student_number
 * @property string|int|null $violation_type_id
 * @property \Illuminate\Support\Carbon|null $violation_date
 * @property string|null $description
 * @property int|null $recorder_id
 * @property string|null $recorder_type
 * @property string|null $recorder_name
 * @property string|null $status            pending | confirmed | denied
 * @property int|null $reviewed_by_admin_id
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property string|null $review_remarks
 * @property-read string $violation_type_display         Accessor: resolved violation type name
 * @property-read string $violation_category_display     Accessor: resolved category name (or N/A)
 * @property-read string $offense_level                 Accessor: formatted offense level (e.g. "1st Offense")
 * @property-read string $recorded_by_display           Accessor: human-readable recorder name
 */
class Violation extends Model
{
    protected $table = 'violation_tbl';

    protected $primaryKey = 'violation_id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'student_number',
        'violation_type_id',
        // Legacy column: historical callers store the type id or name here.
        // Kept mass-assignable so those records still resolve their type.
        'violation_type',
        'violation_date',
        'description',
        'recorder_id',
        'recorder_type',
        'recorder_name',
        'recorded_by_security_id',
        'status',
        'reviewed_by_admin_id',
        'reviewed_at',
        'review_remarks',
        'identity_verified',
'identity_verified_at',
'identity_verified_by_type',
'identity_verified_by_id',
    ];

protected $casts = [
    'violation_type_id' => 'integer',
    'violation_date' => 'datetime',
    'identity_verified' => 'boolean',
    'identity_verified_at' => 'datetime',
    'identity_verified_by_id' => 'integer',
    'reviewed_at' => 'datetime',
];

    /*
    |--------------------------------------------------------------------------
    | Confirmation workflow
    |--------------------------------------------------------------------------
    |
    | Violations recorded by Security start as 'pending' and are sent to an
    | administrator who either 'confirmed' or 'denied' them. Admin-recorded
    | violations are treated as confirmed immediately. Legacy rows (NULL)
    | remain effective for backward compatibility.
    |
    | Status values (lowercase): pending | confirmed | denied
    |-----------------------------------------------------------------------
    */

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_DENIED = 'denied';
public const STATUS_DELETED = 'deleted';
    /**
     * A violation is "effective" (counts toward the record) once confirmed.
     * Admin-recorded or legacy rows without an explicit status also count.
     */


public function getIsEffectiveAttribute(): bool
{
    $status = strtolower(
        trim((string) ($this->attributes['status'] ?? ''))
    );

    return ! in_array($status, [
        self::STATUS_PENDING,
        self::STATUS_DENIED,
        self::STATUS_DELETED,
    ], true);
}



    /**
     * Scope: ONLY violations that count toward the official record, dashboards,
     * reports, offense levels, and the student module. Pending (awaiting admin
     * confirmation) and denied rows are excluded.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEffective($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('status')
                ->orWhere('status', self::STATUS_CONFIRMED);
        });
    }

    /**
     * Scope: everything EXCEPT denied rows. Used where a recorder should still
     * see their own pending + confirmed recordings (e.g. the security module).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNotDenied($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('status')
                ->orWhereIn('status', [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
        });
    }

    /**
     * Scope: rows still awaiting admin confirmation (security-recorded only).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePendingConfirmation($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /*
    |--------------------------------------------------------------------------
    | Reviewer
    |--------------------------------------------------------------------------
    */

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id', 'id');
    }

    /**
     * Supporting evidence attached to this violation (private storage).
     */
    public function evidence(): HasMany
    {
        return $this->hasMany(ViolationEvidence::class, 'violation_id', 'violation_id');
    }

    public function editHistories(): HasMany
{
    return $this->hasMany(
        ViolationEditHistory::class,
        'violation_id',
        'violation_id'
    )->latest();
}

public function getViolationTypeDisplayAttribute(): string
{
    $resolved = $this->resolvedViolationType();

    if (!empty($resolved?->violation_type)) {
        return $resolved->violation_type;
    }

    return 'Unknown Violation';
}

    public function getViolationCategoryDisplayAttribute(): string
    {
        $resolved = $this->resolvedViolationType();
        return (string) ($resolved?->violationCategory?->category_name ?? 'N/A');
    }

public function getOffenseLevelAttribute(): string
{
    // A stored label, when a legacy schema provides one, still wins.
    if (!empty($this->attributes['offense_level'])) {
        return (string) $this->attributes['offense_level'];
    }

    $userId = (int) ($this->user_id ?? 0);
    $violationId = (int) ($this->violation_id ?? 0);
    $violationTypeId = (int) ($this->violation_type_id ?? 0);

    // Legacy rows may carry the type only in the raw `violation_type` column
    // (numeric id or type name). Resolve it the same way the centralized
    // service does, so the displayed number matches the confirmation-queue
    // filter (whose SQL expression resolves the same bucket).
    if ($violationTypeId <= 0) {
        $violationTypeId = (int) ($this->resolvedViolationType()?->violation_type_id ?? 0);
    }

    if ($userId <= 0 || $violationId <= 0 || $violationTypeId <= 0) {
        return '';
    }

    // Denied / deleted rows remain in the history but never occupy a slot.
    $service = app(\App\Services\OffenseNumberService::class);

    if (! $service->statusCountsTowardOffense($this->status)) {
        return '';
    }

    return $service->offenseLevelFor($userId, $violationId, $violationTypeId);
}

    public function getRecordedByDisplayAttribute(): string
    {
        if (!empty($this->recorder_name)) {
            if (str_starts_with($this->recorder_name, 'Security: ')) {
                $security = Security::where('email', substr($this->recorder_name, 10))
                    ->with('securityInfo')
                    ->first();

                $fullName = trim(implode(' ', array_filter([
    $security?->securityInfo?->firstname,
    $security?->securityInfo?->middlename,
    $security?->securityInfo?->lastname,
], fn ($value) => !empty($value))));

                if ($fullName !== '') {
                    return $fullName;
                }
            }

            return $this->recorder_name;
        }

        $firstName = (string) ($this->recorder?->adminInfo?->firstname ?? '');
        $middleName = (string) ($this->recorder?->adminInfo?->middlename ?? '');
        $lastName = (string) ($this->recorder?->adminInfo?->lastname ?? '');
        $fullName = trim($firstName . ' ' . $middleName . ' ' . $lastName);

        if ($fullName !== '') {
            return $fullName;
        }

        if (!empty($this->recorder_id)) {
            return 'Admin #' . $this->recorder_id;
        }

        return '-';
    }

    /*
    |--------------------------------------------------------------------------
    | Student
    |--------------------------------------------------------------------------
    */

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
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
        'violation_type_id',
        'violation_type_id'
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
        Admin::class,
        'recorder_id',
        'id'
    );
}

/**
 * The security account that recorded this violation (security-recorded rows).
 * Null for admin-recorded or legacy rows. This is the reliable key used to
 * target notifications to the exact recording officer.
 */
public function recordingSecurity(): BelongsTo
{
    return $this->belongsTo(
        Security::class,
        'recorded_by_security_id',
        'id'
    );
}

public function resolvedViolationType(): ?ViolationType
{
    if ($this->relationLoaded('violationType') && $this->violationType !== null) {
        return $this->violationType;
    }

    if (!empty($this->violation_type_id)) {
        return ViolationType::with('violationCategory')
            ->find($this->violation_type_id);
    }

    // Backward compatibility for legacy rows that stored the type NAME (or a
    // numeric id) in the raw `violation_type` column instead of the FK.
    $raw = trim((string) ($this->attributes['violation_type'] ?? ''));

    if ($raw === '') {
        return null;
    }

    if (ctype_digit($raw)) {
        return ViolationType::with('violationCategory')->find((int) $raw);
    }

    return ViolationType::with('violationCategory')
        ->whereRaw('LOWER(TRIM(violation_type)) = ?', [mb_strtolower($raw)])
        ->first();
}

}