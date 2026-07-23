<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    'email',
    'gender_id',
    'status_id',
    'roles_id',
    'password_hash',
    'reset_token_hash',
    'reset_token_expires_at',
    'new_until',
    'mfa_totp_secret',
    'mfa_totp_enabled',
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
}