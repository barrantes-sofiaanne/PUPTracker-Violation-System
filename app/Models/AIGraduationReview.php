<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores a generated AI-assisted graduation disciplinary review.
 *
 * This is an assistance/audit layer ONLY. It never overwrites the original
 * disciplinary records and is not authoritative for graduation decisions.
 */
class AIGraduationReview extends Model
{
    protected $table = 'ai_graduation_reviews';

    protected $primaryKey = 'review_id';

    protected $fillable = [
        'student_number',
        'generated_by_admin_id',
        'review_status',
        'summary',
        'disciplinary_pattern',
        'reason_for_review',
        'recommended_action',
        'outstanding_sanctions',
        'total_violations',
        'completed_sanctions',
        'pending_sanctions',
        'outstanding_sanctions_count',
        'ai_model',
        'ai_generated_at',
    ];

    protected $casts = [
        'outstanding_sanctions' => 'array',
        'ai_generated_at' => 'datetime',
        'generated_by_admin_id' => 'integer',
        'total_violations' => 'integer',
        'completed_sanctions' => 'integer',
        'pending_sanctions' => 'integer',
        'outstanding_sanctions_count' => 'integer',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_number', 'student_number');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'generated_by_admin_id', 'id');
    }

    /**
     * Latest saved review for a student.
     */
    public static function latestFor(string $studentNumber): ?self
    {
        return static::query()
            ->where('student_number', $studentNumber)
            ->latest('ai_generated_at')
            ->first();
    }
}
