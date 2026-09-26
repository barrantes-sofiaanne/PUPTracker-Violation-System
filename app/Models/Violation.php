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
 * @property string|int|null $violation_type
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
        'student_number',
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
    ];

    protected $casts = [
        'violation_date' => 'datetime',
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

    /**
     * A violation is "effective" (counts toward the record) once confirmed.
     * Admin-recorded or legacy rows without an explicit status also count.
     */
    public function getIsEffectiveAttribute(): bool
    {
        $status = strtolower(trim((string) ($this->attributes['status'] ?? '')));

        return $status !== self::STATUS_PENDING && $status !== self::STATUS_DENIED;
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

    public function getViolationTypeDisplayAttribute(): string
    {
        $resolved = $this->resolvedViolationType();
        if (!empty($resolved?->violation_type)) {
            return $resolved->violation_type;
        }

        $raw = trim((string) $this->violation_type);
        if ($raw !== '' && !ctype_digit($raw)) {
            return $raw;
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
        if (!empty($this->attributes['offense_level'])) {
            return (string) $this->attributes['offense_level'];
        }

        if (empty($this->student_number) || empty($this->violation_id)) {
            return '';
        }

        $rawType = trim((string) $this->violation_type);
        if ($rawType === '') {
            return '';
        }

        $resolved = $this->resolvedViolationType();

        $count = self::where('student_number', $this->student_number)
            ->where('violation_id', '<=', $this->violation_id)
            ->where(function ($query) use ($rawType, $resolved) {
                $query->where('violation_type', $rawType);

                if ($resolved?->violation_type_id !== null) {
                    $query->orWhere('violation_type', (string) $resolved->violation_type_id);
                }

                if (!empty($resolved?->violation_type)) {
                    $query->orWhere('violation_type', $resolved->violation_type);
                }
            })
            // Only confirmed/legacy violations count toward the offense level;
            // pending (awaiting admin confirmation) and denied rows do not.
            ->effective()
            ->count();

        return $this->formatOffenseLevel((int) $count);
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
        if ($this->relationLoaded('violationType') && $this->violationType) {
            return $this->violationType;
        }

        $raw = trim((string) $this->violation_type);
        if ($raw === '') {
            return null;
        }

        if (ctype_digit($raw)) {
            return ViolationType::with('violationCategory')->find((int) $raw);
        }

        return ViolationType::with('violationCategory')
            ->where('violation_type', $raw)
            ->first();
    }

    private function formatOffenseLevel(int $count): string
    {
        if ($count <= 0) {
            return '';
        }

        if ($count === 1) {
            return '1st Offense';
        }

        if ($count === 2) {
            return '2nd Offense';
        }

        if ($count === 3) {
            return '3rd Offense';
        }

        $suffix = match (true) {
            $count % 100 >= 11 && $count % 100 <= 13 => 'th',
            $count % 10 === 1 => 'st',
            $count % 10 === 2 => 'nd',
            $count % 10 === 3 => 'rd',
            default => 'th',
        };

        return $count . $suffix . ' Offense';
    }
}