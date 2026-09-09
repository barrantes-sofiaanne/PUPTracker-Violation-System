<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\EvidenceSecurityEvent;
use App\Models\ViolationEvidence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Central security/audit helper for the Evidence Capture & Protection flow.
 *
 * Responsibilities:
 *   - Generate unique human-readable evidence codes (EVD-YYYY-NNNNNN).
 *   - Record evidence security events (detection/deterrence indicators).
 *   - Write AuditLog rows when evidence is viewed/downloaded.
 *   - Resolve the configured private evidence disk (S3-ready on Railway).
 *
 * Honest security model: these helpers support DETER / DETECT / TRACE /
 * PROTECT. They never assert that a screenshot was "confirmed" — browsers and
 * OSes control screenshot capture outside the page, so events are indicators
 * that require administrative review.
 */
class EvidenceSecurityService
{
    /**
     * Disk used for evidence. Local = storage/app/private (private, served
     * behind auth). On Railway set EVIDENCE_STORAGE_DISK=s3 and the AWS_*
     * variables to use a private S3-compatible bucket instead.
     */
    public function evidenceDisk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk((string) config('filesystems.evidence_disk', 'local'));
    }

    /**
     * Generate the next unique evidence code: EVD-{YEAR}-{6-digit sequence}.
     * The sequence is per-year and derived from the max existing code so it is
     * stable and re-runnable without a dedicated sequence table.
     */
    public function nextEvidenceCode(?int $year = null): string
    {
        $year = $year ?? (int) now()->format('Y');

        $prefix = 'EVD-' . $year . '-';

        $last = DB::table('violation_evidence_tbl')
            ->where('evidence_code', 'like', $prefix . '%')
            ->orderByDesc('evidence_id')
            ->value('evidence_code');

        if ($last) {
            $seq = ((int) substr((string) $last, strlen($prefix))) + 1;

            return $prefix . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
        }

        return $prefix . '000001';
    }

    /**
     * Generate a fresh camera/capture session id: CAM-SESSION-{YEAR}-{6-digit}.
     * Each open of the camera portal creates a NEW session so the browser
     * monitor events (open, blur, focus, possible capture, photo taken,
     * submitted/abandoned) can all be correlated to the same attempt.
     */
    public function newCaptureSessionId(?int $year = null): string
    {
        $year = $year ?? (int) now()->format('Y');

        // Uniqueness is per-session; derive from a random 6-digit suffix rather
        // than a shared DB sequence (no session table exists). Collisions are
        // astronomically unlikely and harmless (still individually logged).
        return 'CAM-SESSION-' . $year . '-' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Record an evidence security event. Event types are defined on
     * EvidenceSecurityEvent. Non-fatal: never throws for logging problems.
     */
    public function logEvent(
        string $eventType,
        ?int $violationId = null,
        ?int $evidenceId = null,
        string $severity = 'info',
        ?Request $request = null,
        ?array $meta = null,
        ?string $actorType = null,
        ?int $actorId = null,
        ?string $captureSessionId = null,
    ): void {
        try {
            $payload = [
                'capture_session_id' => $captureSessionId,
                'violation_id' => $violationId,
                'evidence_id' => $evidenceId,
                'event_type' => $eventType,
                'severity' => $severity,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'created_at' => now(),
            ];

            if ($request) {
                $payload['ip_address'] = $this->clientIp($request);
                $payload['user_agent'] = $this->userAgent($request);
            }

            if (! empty($meta)) {
                $payload['meta'] = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            EvidenceSecurityEvent::create($payload);
        } catch (\Throwable $e) {
            // Security logging must never break the primary flow.
            report($e);
        }
    }

    /**
     * Resolve the current actor (guard-aware). Returns [type, id] or [null, null].
     */
    public function resolveActor(): array
    {
        foreach (['admin', 'security', 'student'] as $guard) {
            $guardInstance = auth($guard);
            if ($guardInstance->check()) {
                $user = $guardInstance->user();

                $id = $guard === 'student'
                    ? (int) ($user->user_id ?? $user->getAuthIdentifier())
                    : (int) $user->getAuthIdentifier();

                return [$guard, $id];
            }
        }

        return [null, null];
    }

    /**
     * Write an AuditLog row for an evidence access (view/download), plus an
     * evidence-scoped security event on the correlated timeline.
     */
    public function auditEvidenceAccess(
        string $action,
        ViolationEvidence $evidence,
        string $actorType,
        int $actorId,
        ?Request $request = null,
    ): void {
        try {
            $violation = $evidence->violation;
            $description = sprintf(
                '%s evidence %s for violation #%s (student %s).',
                $action,
                $evidence->evidence_code ?: '#' . $evidence->evidence_id,
                $violation?->violation_id ?: 'unknown',
                $violation?->student_number ?: 'unknown',
            );

            $row = [
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'action' => $action,
                'module' => 'Evidence',
                'description' => $description,
                'created_at' => now(),
            ];

            // IP / user-agent are stored on the dedicated security-events
            // table (which has the columns). AuditLog keeps the human-readable
            // trail; we also log an evidence-scoped event for the timeline.
            AuditLog::create($row);

            $eventType = $this->securityEventForAccessAction($action);

            $this->logEvent(
                $eventType,
                $violation?->violation_id,
                $evidence->evidence_id,
                'info',
                $request,
                ['action' => $action],
                $actorType,
                $actorId,
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Map an access action to its evidence security event constant. Unknown
     * actions fall back to EVIDENCE_VIEWED (never throws).
     */
    private function securityEventForAccessAction(string $action): string
    {
        return match ($action) {
            'EVIDENCE_DOWNLOADED' => EvidenceSecurityEvent::EVIDENCE_DOWNLOADED,
            'STUDENT_EVIDENCE_VIEWED' => EvidenceSecurityEvent::STUDENT_EVIDENCE_VIEWED,
            'STUDENT_EVIDENCE_DOWNLOADED' => EvidenceSecurityEvent::STUDENT_EVIDENCE_DOWNLOADED,
            'PROOF_VIEWED' => EvidenceSecurityEvent::PROOF_VIEWED,
            'PROOF_DOWNLOADED' => EvidenceSecurityEvent::PROOF_DOWNLOADED,
            default => EvidenceSecurityEvent::EVIDENCE_VIEWED,
        };
    }

    public function clientIp(?Request $request): ?string
    {
        if (! $request) {
            return null;
        }

        return $request->ip() ?: ($request->header('X-Forwarded-For') ?: null);
    }

    public function userAgent(?Request $request): ?string
    {
        if (! $request) {
            return null;
        }

        return mb_substr((string) $request->userAgent(), 0, 500) ?: null;
    }
}
