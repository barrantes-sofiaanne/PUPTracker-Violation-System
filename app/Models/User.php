<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $user_id
 * @property string $student_number
 * @property string|null $first_name
 * @property string|null $middle_name
 * @property string|null $last_name
 * @property string|null $name_suffix
 * @property string|null $email
 * @property string|null $idp_subject
 * @property string|null $idp_email
 * @property \Illuminate\Support\Carbon|null $profile_completed_at
 * @property int|null $gender_id
 * @property int|null $status_id
 * @property int|null $roles_id
 * @property string|null $password_hash
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Table Information
     */
    protected $table = 'users_tbl';

    protected $primaryKey = 'user_id';

    public $incrementing = true;

    protected $keyType = 'int';

    /**
     * Mass Assignable Attributes
     */
protected $fillable = [
    'student_number',
    'first_name',
    'middle_name',
    'last_name',
    'name_suffix',
    'email',
    'idp_subject',
    'idp_email',
    'idp_connected_at',
    'idp_last_login_at',
    'profile_completed_at',
    'gender_id',
    'status_id',
    'roles_id',
    'password_hash',
    'reset_token_hash',
    'reset_token_expires_at',
    'new_until',
    'mfa_totp_secret',
    'mfa_totp_enabled',
    'mfa_backup_codes',
    'mfa_backup_codes_used',
];

    /**
     * Hidden Attributes
     */
    protected $hidden = [
        'password_hash',
        'reset_token_hash',
        'mfa_totp_secret',
    ];

    /**
     * Attribute Casting
     */
    protected function casts(): array
    {
        return [
            'created_at'               => 'datetime',
            'updated_at'               => 'datetime',
            'reset_token_expires_at'   => 'datetime',
            'new_until'                => 'datetime',
            'idp_connected_at'         => 'datetime',
            'idp_last_login_at'        => 'datetime',
            'profile_completed_at'     => 'datetime',
            'mfa_totp_secret'          => 'encrypted',
            'mfa_totp_enabled'         => 'boolean',
        ];
    }

    /**
     * Authentication
     * Laravel uses this column as the password.
     */
    public function getAuthPassword(): string
    {
        return (string) $this->password_hash;
    }

    /**
     * Disable Remember Me token.
     */
    public function getRememberTokenName(): string
    {
        return '';
    }

    /**
     * Has this student completed FIRST-TIME registration?
     *
     * The account is identity-linked through the PUP IDP (`idp_subject`), and
     * `profile_completed_at` records that the guided registration step was
     * finished. Both are required: a linked-but-not-registered account must
     * still be sent to the registration page.
     *
     * Pre-existing linked accounts are grandfathered by the migration, so this
     * returns true for them and they are never re-prompted.
     */
    public function hasCompletedProfile(): bool
    {
        return $this->profile_completed_at !== null;
    }

    /**
     * Fallback "Active" id when status_tbl cannot be read (legacy environments
     * and the in-memory test schema). Matches the seeded Active row.
     */
    public const FALLBACK_ACTIVE_STATUS_ID = 1;

    /**
     * Resolve the id of the "Active" account status.
     *
     * Read from `status_tbl.status_name` (case-insensitive) so the rule follows
     * the real reference data rather than assuming an id. Falls back to 1, which
     * is the seeded Active row in this project.
     */
    public static function activeStatusId(): int
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('status_tbl')) {
                return $cached = self::FALLBACK_ACTIVE_STATUS_ID;
            }

            $id = \Illuminate\Support\Facades\DB::table('status_tbl')
                ->whereRaw('LOWER(status_name) = ?', ['active'])
                ->value('status_id');

            return $cached = ($id !== null ? (int) $id : self::FALLBACK_ACTIVE_STATUS_ID);
        } catch (\Throwable $e) {
            return $cached = self::FALLBACK_ACTIVE_STATUS_ID;
        }
    }

    /**
     * Is this student's PUPTracker ACCOUNT active?
     *
     * IDP authentication proves identity, but it does NOT grant PUPTracker
     * access: the local account status is the authority. A deactivated account
     * must be denied the Student Module even though its IDP login succeeds.
     *
     * A NULL status is treated as active for backward compatibility with
     * pre-existing rows that never had a status set. Only an explicit
     * non-Active value denies access.
     */
    public function isActiveAccount(): bool
    {
        if ($this->status_id === null) {
            return true;
        }

        return (int) $this->status_id === self::activeStatusId();
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Course
     */
 

    /**
     * Year
     */


    /**
     * Section
     */

    /**
     * Gender
     */
    public function gender(): BelongsTo
    {
        return $this->belongsTo(
            Gender::class,
            'gender_id',
            'gender_id'
        );
    }

    /**
     * Status
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(
            Status::class,
            'status_id',
            'status_id'
        );
    }

    /**
     * Role
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(
            Role::class,
            'roles_id',
            'roles_id'
        );
    }

    /**
     * Student Violations
     *
     * Assumes violation_tbl stores student_number.
     */
    public function violations(): HasMany
    {
        return $this->hasMany(
            Violation::class,
            'student_number',
            'student_number'
        );
    }

    /**
     * Notifications
     *
     * Adjust this if your notifications table uses
     * another foreign key.
     */
   public function notifications(): HasMany
{
    return $this->hasMany(
        Notification::class,
        'student_number',
        'student_number'
    );
}

    /**
     * Announcements
     *
     * Usually announcements are global, so this
     * relationship may not be needed. Remove it
     * if announcements_tbl has no user_id column.
     */
 


public function studentInfo()
{
    return $this->hasOne(StudentInfo::class, 'user_id');
}

public function getProgramAttribute()
{
    return $this->studentInfo?->program;
}

public function getProgramIdAttribute()
{
    return $this->studentInfo?->program_id;
}

public function getYearAttribute()
{
    return $this->studentInfo?->year;
}

public function getYearIdAttribute()
{
    return $this->studentInfo?->year_id;
}

public function getSectionAttribute()
{
    return $this->studentInfo?->section;
}

public function getSectionIdAttribute()
{
    return $this->studentInfo?->section_id;
}

public function getStudentStatusAttribute()
{
    return $this->studentInfo?->studentStatus;
}

public function getStudentStatusIdAttribute()
{
    return $this->studentInfo?->student_status_id;
}

/**
 * Full name in "First Middle Last Suffix" order.
 * Example: Juan Dela Cruz Jr.
 */
public function getFullNameAttribute(): string
{
    return trim(implode(' ', array_filter([
        $this->first_name,
        $this->middle_name,
        $this->last_name,
        $this->name_suffix,
    ], fn ($part) => trim((string) $part) !== '')));
}

/**
 * Formal display name in "Last, First Middle Suffix" order.
 * Example: Cruz, Juan Dela Jr.
 */
public function getFormalNameAttribute(): string
{
    $given = trim(implode(' ', array_filter([
        $this->first_name,
        $this->middle_name,
    ], fn ($part) => trim((string) $part) !== '')));

    $suffix = trim((string) $this->name_suffix);
    $suffix = $suffix !== '' ? ', ' . $suffix : '';

    $last = trim((string) $this->last_name);

    if ($last === '' && $given === '') {
        return $suffix !== '' ? $suffix : '';
    }

    if ($last === '') {
        return $given . $suffix;
    }

    return trim($last . ', ' . $given . $suffix);
}
}