<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $program_id
 * @property string|null $program_name
 * @property string|null $degree_type
 */
class Program extends Model
{
    protected $table = 'program_tbl';

    protected $primaryKey = 'program_id';

    public $timestamps = false;

    protected $fillable = [
        'program_name',
        'degree_type',
    ];

    public function normalizedDegreeType(): ?string
    {
        $classified = match (strtolower(trim((string) ($this->degree_type ?? '')))) {
            'bachelor', "bachelor's", 'bachelors', 'bachelor degree', "bachelor's degree" => "Bachelor's",
            'diploma', 'diploma degree' => 'Diploma',
            default => null,
        };

        if ($classified !== null) {
            return $classified;
        }

        // Fallback: infer from the program name/abbreviation when classification
        // is not populated yet. Programs whose abbreviation starts with "B" are
        // Bachelor's degrees; "D" are Diploma programs.
        $name = strtoupper(trim((string) $this->program_name));
        $firstLetter = $name[0] ?? '';

        if (in_array($firstLetter, ['B'], true)) {
            return "Bachelor's";
        }

        if (in_array($firstLetter, ['D'], true)) {
            return 'Diploma';
        }

        return null;
    }

    public function graduatingYear(): ?int
    {
        return match ($this->normalizedDegreeType()) {
            "Bachelor's" => 4,
            'Diploma' => 3,
            default => null,
        };
    }

    public function students(): HasMany
    {
        return $this->hasMany(
            StudentInfo::class,
            'program_id',
            'program_id'
        );
    }
}