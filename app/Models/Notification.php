<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications_tbl';

    protected $primaryKey = 'notification_id';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'student_number',
        'message',
        'title',
        'is_read',
        'link',
        'notification_type',
        'recipient_type',
        'recipient_id',
        'target_type',
        'target_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'is_read' => 'boolean',
        'recipient_id' => 'integer',
        'target_id' => 'integer',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'student_number',
            'student_number'
        );
    }

    /**
     * Scope notifications to one exact recipient account and recipient type.
     * Rows with a null recipient_id are legacy/unassigned and are deliberately
     * excluded so new accounts do not inherit another account's notifications.
     */
    public function scopeForRecipient(Builder $query, string $recipientType, ?int $recipientId): Builder
    {
        // Notifications are private to one account. A null recipient ID must
        // never act as a role-wide broadcast, because newly created accounts
        // would inherit historical/unassigned notifications.
        if ($recipientId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('recipient_type', $recipientType)
            ->where('recipient_id', $recipientId);
    }

    /**
     * Scope: STUDENT notifications owned by one specific account.
     *
     * ACCOUNT OWNERSHIP is the authorisation boundary: the match is on
     * `recipient_id = users_tbl.user_id` (the account key), NOT on
     * `student_number`.
     *
     * Why not student_number: PUPTracker has no pre-created student-number
     * roster, so a student TYPES their own student number during first-time
     * registration. It is self-asserted profile data, not an identity, and
     * scoping private records on it is strictly weaker than scoping on the
     * account key returned by the IDP.
     *
     * The `student_number` branch is a LEGACY fallback only, and it is
     * deliberately bounded:
     *   - rows whose recipient_id IS NULL (written before account scoping), and
     *   - only for that one student number.
     * A row that carries a DIFFERENT recipient_id is never matched, so a
     * notification already reassigned to (or owned by) another account can not
     * be pulled back in via a student-number match.
     *
     * @param  string|null  $studentNumber  The authenticated account's student number (legacy fallback).
     */
    public function scopeOwnedByStudent(Builder $query, ?int $userId, ?string $studentNumber = null): Builder
    {
        return $query->where('recipient_type', 'student')
            ->where(function (Builder $q) use ($userId, $studentNumber): void {
                $q->where(function (Builder $owned) use ($userId): void {
                    // Account-owned rows (the strong check).
                    if ($userId !== null) {
                        $owned->where('recipient_id', $userId);
                    }
                })
                ->orWhere(function (Builder $legacy) use ($studentNumber): void {
                    // Pre-account-scoping rows, matched by the account's own
                    // student number only.
                    $legacy->whereNull('recipient_id');

                    if ($studentNumber !== null && $studentNumber !== '') {
                        $legacy->where('student_number', $studentNumber);
                    } else {
                        // No student number on the account: no legacy fallback.
                        $legacy->whereRaw('1 = 0');
                    }
                });
            });
    }

    /**
     * Does this notification belong to the given account?
     *
     * Single authoritative ownership predicate used by every open/read/mark
     * action so the rule cannot drift between endpoints.
     */
    public function isOwnedByStudent(?int $userId, ?string $studentNumber = null): bool
    {
        if ((string) ($this->recipient_type ?? '') !== 'student') {
            return false;
        }

        // Account ownership wins outright when the row carries one.
        if ($this->recipient_id !== null) {
            return $userId !== null && (int) $this->recipient_id === (int) $userId;
        }

        // Legacy row: fall back to the authenticated account's student number.
        if ($studentNumber === null || $studentNumber === '') {
            return false;
        }

        return (string) $this->student_number === (string) $studentNumber;
    }
}