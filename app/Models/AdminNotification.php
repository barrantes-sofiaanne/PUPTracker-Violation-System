<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $admin_notification_id
 * @property string $message
 * @property string|null $title
 * @property string|null $link
 * @property string|null $notification_type
 * @property string|null $priority
 * @property string|null $target_type
 * @property int|null $target_id
 * @property string|null $recipient_type
 * @property int|null $recipient_id
 * @property bool $is_read
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class AdminNotification extends Model
{
    use HasFactory;

    protected $table = 'admin_notifications_tbl';

protected $primaryKey = 'admin_notification_id';
    public $timestamps = false;

    /**
     * AUDIENCE: operational admins. Violation / sanction / student workflow.
     * The Super Admin is deliberately EXCLUDED from this audience.
     */
    public const AUDIENCE_ADMIN = 'admin';

    /**
     * AUDIENCE: the Super Admin. SYSTEM-LEVEL alerts only — backup,
     * infrastructure, storage, critical security. Never student-specific.
     */
    public const AUDIENCE_SUPER_ADMIN = 'super_admin';

    /** Severity levels (§5). Only awareness/action events deserve a notification. */
    public const PRIORITY_INFO = 'info';
    public const PRIORITY_WARNING = 'warning';
    public const PRIORITY_CRITICAL = 'critical';

    protected $fillable = [
        'message',
        'title',
        'link',
        'notification_type',
        'priority',
        'target_type',
        'target_id',
        'recipient_type',
        'recipient_id',
        'is_read',
        'created_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'target_id' => 'integer',
        'recipient_id' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Scope: notifications belonging to ONE admin account.
     *
     * This is the single authoritative rule for the admin/Super Admin inboxes.
     *
     * WHY `recipient_type` IS PART OF THE RULE
     * ----------------------------------------
     * The Super Admin is an `admins` row, so matching on `recipient_id` alone
     * would hand them every operational notification again. The audience marker
     * keeps the two inboxes disjoint:
     *
     *   Super Admin  -> recipient_type = 'super_admin' only
     *   regular Admin -> recipient_type = 'admin' (broadcast + their own)
     *
     * `recipient_id IS NULL` means "the whole audience" (the existing broadcast
     * convention), and a non-null id narrows it to that one account.
     *
     * Pre-existing rows keep their old behaviour: the migration backfills NULL
     * recipient_type to 'admin', which is exactly how they were already being
     * treated.
     */
    /**
     * Scope: notifications belonging to ONE admin account.
     *
     * This is the single authoritative rule for the admin/Super Admin inboxes.
     *
     * WHY `recipient_type` IS PART OF THE RULE
     * ----------------------------------------
     * The Super Admin is an `admins` row, so matching on `recipient_id` alone
     * would hand them every operational notification again. The audience marker
     * keeps the two inboxes disjoint:
     *
     *   Super Admin   -> recipient_type = 'super_admin' only
     *   regular Admin -> recipient_type = 'admin' (plus legacy NULL rows)
     *
     * The audience is DERIVED from `$isSuperAdmin` rather than passed in, so a
     * caller cannot accidentally request the wrong audience for its own account
     * (that mismatch previously handed a regular admin the Super Admin's alerts).
     *
     * `recipient_id IS NULL` means "the whole audience" (the existing broadcast
     * convention); a non-null id narrows it to that one account.
     *
     * Pre-existing rows keep their old behaviour: NULL recipient_type is folded
     * into the ADMIN audience explicitly, because a plain `where('admin')` would
     * EXCLUDE nulls (SQL three-valued logic) and silently hide every
     * pre-migration row from everyone.
     */
    public function scopeForAudience(Builder $query, ?int $recipientId, bool $isSuperAdmin = false): Builder
    {
        return $query->where(function (Builder $q) use ($isSuperAdmin): void {
            if ($isSuperAdmin) {
                // System-level alerts only. Legacy NULL rows are deliberately
                // NOT included: they were visible to all admins, and claiming
                // them for the Super Admin would recreate the leak.
                $q->where('recipient_type', self::AUDIENCE_SUPER_ADMIN);
            } else {
                $q->where('recipient_type', self::AUDIENCE_ADMIN)
                    ->orWhereNull('recipient_type');
            }
        })
            ->where(function (Builder $q) use ($recipientId): void {
                $q->whereNull('recipient_id')
                    ->orWhere('recipient_id', $recipientId);
            });
    }

    /**
     * The audience a notification is addressed to, defaulting to 'admin' for
     * legacy rows that predate the marker.
     */
    public function audience(): string
    {
        return (string) ($this->recipient_type ?: self::AUDIENCE_ADMIN);
    }

    public function isSuperAdminAlert(): bool
    {
        return $this->audience() === self::AUDIENCE_SUPER_ADMIN;
    }

    /** Priority with a safe default for rows written before the column existed. */
    public function priorityLevel(): string
    {
        return (string) ($this->priority ?: self::PRIORITY_INFO);
    }

    /** Tailwind-free badge class used by the notification views. */
    public function priorityBadgeClass(): string
    {
        return match ($this->priorityLevel()) {
            self::PRIORITY_CRITICAL => 'admin-badge admin-badge-danger',
            self::PRIORITY_WARNING => 'admin-badge admin-badge-gold',
            default => 'admin-badge admin-badge-neutral',
        };
    }
}