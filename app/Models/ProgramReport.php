<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores a generated Program Sanction Summary Report (history/audit layer).
 *
 * @property int $report_id
 * @property int|null $program_id
 * @property string|null $academic_year
 * @property string|null $report_type
 * @property string|null $report_mode
 * @property string|null $degree_type
 * @property int|null $graduating_year
 * @property array|null $admission_years
 * @property int|null $ladderized
 * @property array|null $additional_students
 * @property array|null $excluded_students
 * @property int|null $generated_by_admin_id
 * @property \Illuminate\Support\Carbon|null $generated_at
 * @property string|null $report_status
 * @property array|null $statistics_json
 * @property array|null $violation_breakdown
 * @property array|null $repeated_violations
 * @property array|null $outstanding_students
 * @property array|null $manual_review_students
 * @property string|null $ai_narrative
 * @property string|null $ai_model
 * @property bool|null $ai_failed
 * @property-read Program|null $program
 * @property-read Admin|null $generatedBy
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
        'ladderized',
        'additional_students',
        'excluded_students',
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
        // graduating_year is an INT (tinyint): for graduating reports it holds
        // the first/oldest admission year; the full admission-year list lives
        // in the admission_years JSON column.
        'graduating_year' => 'integer',
        'admission_years' => 'array',
        'additional_students' => 'array',
        'excluded_students' => 'array',
        'ladderized' => 'integer',
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
