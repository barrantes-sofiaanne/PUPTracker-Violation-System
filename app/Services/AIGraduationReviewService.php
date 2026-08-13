<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Server-side AI client for the AI-Assisted Graduation Disciplinary Review.
 *
 * Responsibilities:
 *  - Build a strictly-constrained system prompt.
 *  - Send ONLY the minimum necessary, non-sensitive student disciplinary data.
 *  - Require the AI to return a validated structured JSON object.
 *  - Never make the final graduation / Good Moral decision (that is a human job).
 *  - Fail gracefully so the deterministic screening always remains available.
 */
class AIGraduationReviewService
{
    protected const ALLOWED_STATUSES = [
        'no_immediate_concern',
        'manual_review_recommended',
        'outstanding_sanction_review',
    ];

    /**
     * Whether the AI provider is configured and enabled.
     */
    public function isAvailable(): bool
    {
        return (bool) config('graduation_review.ai.enabled', false)
            && config('graduation_review.ai.base_url') !== ''
            && config('graduation_review.ai.api_key') !== '';
    }

    /**
     * Build the system prompt that constrains AI behavior.
     */
    protected function systemPrompt(): string
    {
        return implode("\n", [
            'You are an AI assistant supporting a university Discipline Office reviewer.',
            'You analyze ONLY the disciplinary records provided to you. Never invent violations, sanctions, dates, or student information.',
            'Never infer sensitive facts that are not explicitly present in the provided data.',
            'You must NEVER make the final graduation clearance or Good Moral certificate decision. That decision belongs exclusively to the Discipline Office.',
            'You must clearly distinguish factual statements from your own observations.',
            'Recommend manual review when appropriate, especially when outstanding or pending sanctions exist.',
            'Remain neutral, professional, and respectful. Do not use insulting, judgmental, or stigmatizing language.',
            'The existence of a past violation alone must not be treated as proof that a student should be denied clearance.',
            'Identify repeated patterns only when they are supported by the provided records.',
            'Pay particular attention to unresolved or outstanding sanctions.',
            'Return ONLY a single valid JSON object with exactly these keys and no extra text: summary, review_status, outstanding_sanctions, disciplinary_pattern, reason_for_review, recommended_action.',
            'review_status must be exactly one of: no_immediate_concern, manual_review_recommended, outstanding_sanction_review.',
            'outstanding_sanctions must be an array of strings (may be empty).',
            'If the student has no disciplinary records at all, use review_status "no_immediate_concern" and an empty outstanding_sanctions array.',
            'Do not claim the student is automatically disqualified or automatically cleared.',
        ]);
    }

    /**
     * Generate an AI review for a student's deterministic screening result.
     *
     * @param  array  $screening  Result of GraduationReviewScreeningService::screenStudent()
     * @return array{
     *     summary: string,
     *     review_status: string,
     *     outstanding_sanctions: array,
     *     disciplinary_pattern: string,
     *     reason_for_review: string,
     *     recommended_action: string,
     * }
     *
     * @throws RuntimeException if AI unavailable, request fails, or response is invalid.
     */
    public function generate(array $screening): array
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('AI graduation review is not configured.');
        }

        $payload = $this->buildPromptPayload($screening);

        try {
            $response = Http::withToken((string) config('graduation_review.ai.api_key'))
                ->acceptJson()
                ->timeout((int) config('graduation_review.ai.timeout', 20))
                ->post(
                    $this->endpoint(),
                    $this->buildRequestBody($payload)
                );
        } catch (Throwable $e) {
            Log::warning('AI graduation review request failed.', [
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('AI graduation review request failed.');
        }

        if (! $response->successful()) {
            Log::warning('AI graduation review returned a non-success status.', [
                'status' => $response->status(),
            ]);

            throw new RuntimeException('AI graduation review service returned an error.');
        }

        return $this->parseResponse($response->body(), $screening);
    }

    /**
     * Build the compact, non-sensitive user message describing the student.
     */
    protected function buildPromptPayload(array $screening): array
    {
        return [
            'student_ref' => $screening['student_number'],
            'program' => $screening['program'],
            'year' => $screening['year'],
            'section' => $screening['section'],
            'total_violations' => $screening['total_violations'],
            'completed_sanctions' => $screening['completed_sanctions'],
            'pending_sanctions' => $screening['pending_sanctions'],
            'outstanding_sanctions' => $screening['outstanding_sanctions'],
            'most_recent_violation_date' => $screening['most_recent_violation_date'],
            'most_recent_violation_type' => $screening['most_recent_violation_type'],
            'repeated_violations_exist' => $screening['repeated_violations_exist'],
            'violations' => $screening['violations'],
            'sanction_records' => $screening['sanction_records'],
        ];
    }

    /**
     * Endpoint URL for the configured provider (OpenAI-compatible chat API).
     */
    protected function endpoint(): string
    {
        $base = rtrim((string) config('graduation_review.ai.base_url'), '/');

        return $base . '/chat/completions';
    }

    /**
     * Request body for an OpenAI-compatible chat completions API.
     */
    protected function buildRequestBody(array $payload): array
    {
        return [
            'model' => (string) config('graduation_review.ai.model', ''),
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => 'Here is the deterministic disciplinary screening data for one student. Analyze it and return the required JSON.' . PHP_EOL . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ],
        ];
    }

    /**
     * Parse and strictly validate the AI JSON response.
     *
     * @throws RuntimeException if the response is invalid JSON or has invalid status.
     */
    protected function parseResponse(string $rawBody, array $screening): array
    {
        try {
            $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new RuntimeException('AI returned an invalid JSON response.');
        }

        $content = $decoded['choices'][0]['message']['content'] ?? null;

        if (is_string($content)) {
            try {
                $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable $e) {
                // Fall through: try to use the outer decoded payload.
            }
        }

        $result = $this->validateResult($decoded, $screening);

        if ($result === null) {
            throw new RuntimeException('AI returned an invalid review structure.');
        }

        return $result;
    }

    /**
     * Validate/normalize the AI result against the strict schema.
     */
    protected function validateResult(?array $decoded, array $screening): ?array
    {
        if (! is_array($decoded)) {
            return null;
        }

        $status = trim((string) ($decoded['review_status'] ?? ''));

        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            return null;
        }

        // If an outstanding sanction exists, force the status to reflect it.
        if ((int) $screening['outstanding_sanctions'] > 0) {
            $status = 'outstanding_sanction_review';
        }

        $outstanding = $decoded['outstanding_sanctions'] ?? [];

        if (! is_array($outstanding)) {
            $outstanding = [];
        }

        return [
            'summary' => mb_substr(trim((string) ($decoded['summary'] ?? '')), 0, 4000),
            'review_status' => $status,
            'outstanding_sanctions' => array_values(array_filter(array_map(
                fn ($item) => mb_substr(trim((string) $item), 0, 500),
                $outstanding,
            ))),
            'disciplinary_pattern' => mb_substr(trim((string) ($decoded['disciplinary_pattern'] ?? '')), 0, 2000),
            'reason_for_review' => mb_substr(trim((string) ($decoded['reason_for_review'] ?? '')), 0, 2000),
            'recommended_action' => mb_substr(trim((string) ($decoded['recommended_action'] ?? '')), 0, 2000),
        ];
    }
}
