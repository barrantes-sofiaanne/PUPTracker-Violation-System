<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Security-event log for the controlled evidence capture/protection workflow.
 *
 * These events are DETECTION / DETERRENCE indicators only. They are recorded
 * when the capture portal is opened, the security notice is accepted, the
 * camera is initialized/fails, a photo is captured/uploaded, the page blurs or
 * loses visibility, a possible screen-capture interruption is detected, or
 * evidence is viewed/downloaded. An event NEVER asserts that a screenshot
 * "confirmed" occurred — browsers/OSes cannot guarantee that.
 *
 * @property int $event_id
 * @property string|null $capture_session_id
 * @property int|null $violation_id
 * @property int|null $evidence_id
 * @property string|null $event_type
 * @property string|null $severity
 * @property string|null $actor_type
 * @property int|null $actor_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $meta
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class EvidenceSecurityEvent extends Model
{
    protected $table = 'evidence_security_events_tbl';

    protected $primaryKey = 'event_id';

    public $timestamps = false;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    /**
     * Event types. Deliberately NO "SCREENSHOT_CONFIRMED" — see class docblock.
     */
    public const CAMERA_PORTAL_OPENED = 'CAMERA_PORTAL_OPENED';
    public const SECURITY_NOTICE_ACCEPTED = 'SECURITY_NOTICE_ACCEPTED';
    public const CAMERA_INITIALIZED = 'CAMERA_INITIALIZED';
    public const CAMERA_PERMISSION_DENIED = 'CAMERA_PERMISSION_DENIED';
    public const CAMERA_ERROR = 'CAMERA_ERROR';
    public const EVIDENCE_CAPTURED = 'EVIDENCE_CAPTURED';
    public const EVIDENCE_UPLOADED = 'EVIDENCE_UPLOADED';
    public const PAGE_BLURRED = 'PAGE_BLURRED';
    public const PAGE_FOCUS_RESTORED = 'PAGE_FOCUS_RESTORED';
    public const PAGE_VISIBILITY_CHANGED = 'PAGE_VISIBILITY_CHANGED';
    public const POSSIBLE_SCREEN_CAPTURE = 'POSSIBLE_SCREEN_CAPTURE';
    public const PRINTSCREEN_DETECTED = 'PRINTSCREEN_DETECTED';
    public const EVIDENCE_VIEWED = 'EVIDENCE_VIEWED';
    public const EVIDENCE_DOWNLOADED = 'EVIDENCE_DOWNLOADED';
    public const STUDENT_EVIDENCE_VIEWED = 'STUDENT_EVIDENCE_VIEWED';
    public const STUDENT_EVIDENCE_DOWNLOADED = 'STUDENT_EVIDENCE_DOWNLOADED';
    public const PROOF_SUBMITTED = 'PROOF_SUBMITTED';
    public const PROOF_VIEWED = 'PROOF_VIEWED';
    public const PROOF_DOWNLOADED = 'PROOF_DOWNLOADED';
    public const PROOF_ACCEPTED = 'PROOF_ACCEPTED';
    public const PROOF_REJECTED = 'PROOF_REJECTED';
    public const SANCTION_COMPLETED = 'SANCTION_COMPLETED';
    public const CAPTURE_ABANDONED = 'CAPTURE_ABANDONED';

    protected $fillable = [
        'capture_session_id',
        'violation_id',
        'evidence_id',
        'event_type',
        'severity',
        'actor_type',
        'actor_id',
        'ip_address',
        'user_agent',
        'meta',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function violation(): BelongsTo
    {
        return $this->belongsTo(Violation::class, 'violation_id', 'violation_id');
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(ViolationEvidence::class, 'evidence_id', 'evidence_id');
    }

    public function scopeForEvidence($query, $evidenceId)
    {
        return $query->where('evidence_id', $evidenceId);
    }

    public function scopeForViolation($query, $violationId)
    {
        return $query->where('violation_id', $violationId);
    }

    public function scopeForCaptureSession($query, string $captureSessionId)
    {
        return $query->where('capture_session_id', $captureSessionId);
    }
}
