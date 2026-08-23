<?php

namespace App\Jobs;

use App\Models\AIGraduationReview;
use App\Models\User;
use App\Services\AIGraduationReviewService;
use App\Services\GraduationReviewScreeningService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One queue job per student. Each job screens the student, calls DeepSeek, and
 * saves the review independently — a failure for one student never stops the
 * rest of the batch.
 */
class GenerateStudentAIReview implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [10, 60, 300];

    public function __construct(
        public string $studentNumber,
        public ?int $requestedByAdminId = null,
    ) {
    }

    public function handle(
        GraduationReviewScreeningService $screening,
        AIGraduationReviewService $ai,
    ): void {
        $student = User::query()
            ->where('student_number', $this->studentNumber)
            ->where('roles_id', 2)
            ->first();

        if (! $student) {
            Log::warning('Student AI review job: student not found.', [
                'student_number' => $this->studentNumber,
            ]);

            return;
        }

        $data = $screening->screenStudent($student);

        // Reuse / skip when a fresh review already exists for the same
        // fingerprint (preserves the same-day reuse behavior + change detection).
        $existing = AIGraduationReview::latestFreshFor(
            $this->studentNumber,
            $data['data_fingerprint'],
        );

        if ($existing) {
            return;
        }

        if (! $ai->isAvailable()) {
            throw new \RuntimeException('AI graduation review is not configured.');
        }

        $result = $ai->generate($data);

        $review = AIGraduationReview::create([
            'student_number' => $this->studentNumber,
            'generated_by_admin_id' => $this->requestedByAdminId,
            'review_status' => $result['review_status'],
            'behavior_trend' => $result['behavior_trend'],
            'trend_explanation' => $result['trend_explanation'],
            'pattern_type' => $result['pattern_type'],
            'summary' => $result['summary'],
            'disciplinary_pattern' => $result['disciplinary_pattern'],
            'reason_for_review' => $result['reason_for_review'],
            'recommended_action' => $result['recommended_action'],
            'outstanding_sanctions' => $result['outstanding_sanctions'],
            'supporting_evidence' => $result['supporting_evidence'],
            'data_contradictions' => $result['data_contradictions'],
            'data_fingerprint' => $data['data_fingerprint'],
            'is_outdated' => false,
            'total_violations' => $data['total_violations'],
            'completed_sanctions' => $data['completed_sanctions'],
            'pending_sanctions' => $data['pending_sanctions'],
            'outstanding_sanctions_count' => $data['outstanding_sanctions'],
            'ai_model' => (string) config('graduation_review.ai.model'),
            'ai_generated_at' => now(),
        ]);

        // Any older reviews for the same student are now stale.
        AIGraduationReview::markOlderOutdated($this->studentNumber, (int) $review->review_id);

        Log::info('Student AI review generated via queue.', [
            'student_number' => $this->studentNumber,
            'review_id' => $review->review_id,
        ]);
    }

    /**
     * Handle a failed job so the batch keeps going.
     */
    public function failed(?Throwable $e): void
    {
        Log::error('Student AI review job failed.', [
            'student_number' => $this->studentNumber,
            'error' => $e?->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}
