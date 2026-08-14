<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Risk No. 7 — a recorded email dispatch failure incident.
 *
 * Super Admin only. Never stores credentials, SMTP passwords, or tokens.
 */
class EmailIncident extends Model
{
    protected $table = 'email_incidents';

    protected $primaryKey = 'incident_id';

    public const STATUS_FAILED = 'failed';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_RESOLVED = 'resolved';

    public const STATUSES = [
        self::STATUS_FAILED,
        self::STATUS_ACKNOWLEDGED,
        self::STATUS_RESOLVED,
    ];

    protected $fillable = [
        'incident_type',
        'email_type',
        'related_record_type',
        'related_record_id',
        'safe_error_message',
        'status',
        'acknowledged_by_admin_id',
        'acknowledged_at',
        'resolved_by_admin_id',
        'resolved_at',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'acknowledged_by_admin_id', 'id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'resolved_by_admin_id', 'id');
    }
}
