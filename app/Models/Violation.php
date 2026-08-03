<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Admin;
use App\Models\Security;
use App\Models\ViolationType;

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
    ];

    protected $casts = [
        'violation_date' => 'datetime',
    ];

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
            ->count();

        return $this->formatOffenseLevel((int) $count);
    }

    public function getRecordedByDisplayAttribute(): string
    {
        if (!empty($this->recorder_name)) {
            if (str_starts_with($this->recorder_name, 'Security: ')) {
                $security = Security::where('email', substr($this->recorder_name, 10))
                    ->with(['securityInfo', 'securityProfile'])
                    ->first();

                $fullName = trim(implode(' ', array_filter([
                    $security?->securityInfo?->firstname ?? $security?->securityProfile?->firstname,
                    $security?->securityInfo?->middlename ?? $security?->securityProfile?->middlename,
                    $security?->securityInfo?->lastname ?? $security?->securityProfile?->lastname,
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