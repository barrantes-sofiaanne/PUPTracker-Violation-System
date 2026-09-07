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
     * Scope: notifications visible to a specific recipient of a given type.
     *
     * The "recipient_id IS NULL" branch preserves pre-policy / broadcast rows
     * (mirrors the AdminNotification convention where NULL = all of the role).
     * Every NEW notification created by the app carries an explicit
     * recipient_id so it is only visible to its intended recipient.
     */
    public function scopeForRecipient(Builder $query, string $recipientType, ?int $recipientId): Builder
    {
        return $query->where('recipient_type', $recipientType)
            ->where(function (Builder $q) use ($recipientId): void {
                $q->whereNull('recipient_id')
                    ->orWhere('recipient_id', $recipientId);
            });
    }
}