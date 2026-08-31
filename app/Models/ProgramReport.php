<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores a generated Program Sanction Summary Report (history/audit layer).
 */
class ProgramReport extends Model
{
    protected $table = 'program_reports';

    protected $primaryKey = 'report_id';

    protected $fillable = [
        'program_id',
        'academic_year',
        'report_type',
        'report_mode',
        'degree_type',
        'graduating_year',
        'admission_years',
        'generated_by_admin_id',
        'generated_at',
        'report_status',
        'statistics_json',
        'violation_breakdown',
        'repeated_violations',
        'outstanding_students',
        'manual_review_students',
        'ai_narrative',
        'ai_model',
        'ai_failed',
    ];

    protected $casts = [
        'statistics_json' => 'array',
        'violation_breakdown' => 'array',
        'repeated_violations' => 'array',
        'outstanding_students' => 'array',
        'manual_review_students' => 'array',
        'generated_at' => 'datetime',
        'ai_failed' => 'boolean',
        // graduating_year holds the applied admission year(s): a single year
        // or a comma-joined list (e.g. "2022" or "2022, 2023") for graduating
        // reports, or the degree-type "year level" for regular reports.
        'graduating_year' => 'string',
        'admission_years' => 'array',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id', 'program_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'generated_by_admin_id', 'id');
    }
}
