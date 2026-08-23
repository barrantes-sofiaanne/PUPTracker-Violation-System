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
        'behavior_trend',
        'trend_explanation',
        'pattern_type',
        'summary',
        'disciplinary_pattern',
        'reason_for_review',
        'recommended_action',
        'outstanding_sanctions',
        'supporting_evidence',
        'data_contradictions',
        'data_fingerprint',
        'is_outdated',
        'total_violations',
        'completed_sanctions',
        'pending_sanctions',
        'outstanding_sanctions_count',
        'ai_model',
        'ai_generated_at',
        'decision_status',
        'decision_remarks',
        'decision_by_admin_id',
        'decision_at',
    ];

    protected $casts = [
        'outstanding_sanctions' => 'array',
        'supporting_evidence' => 'array',
        'data_contradictions' => 'array',
        'ai_generated_at' => 'datetime',
        'decision_at' => 'datetime',
        'is_outdated' => 'boolean',
        'generated_by_admin_id' => 'integer',
        'decision_by_admin_id' => 'integer',
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

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'decision_by_admin_id', 'id');
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

    /**
     * Latest saved, non-outdated review for a student (for reuse/change detection).
     */
    public static function latestFreshFor(string $studentNumber, string $fingerprint): ?self
    {
        return static::query()
            ->where('student_number', $studentNumber)
            ->where('data_fingerprint', $fingerprint)
            ->where('is_outdated', false)
            ->latest('ai_generated_at')
            ->first();
    }

    /**
     * Mark any older reviews for this student as outdated (data changed).
     */
    public static function markOlderOutdated(string $studentNumber, int $keepReviewId): void
    {
        static::query()
            ->where('student_number', $studentNumber)
            ->where('review_id', '!=', $keepReviewId)
            ->where('is_outdated', false)
            ->update(['is_outdated' => true]);
    }
}
