<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        return match (strtolower(trim((string) ($this->degree_type ?? '')))) {
            'bachelor', "bachelor's", 'bachelors', 'bachelor degree', "bachelor's degree" => "Bachelor's",
            'diploma', 'diploma degree' => 'Diploma',
            default => null,
        };
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