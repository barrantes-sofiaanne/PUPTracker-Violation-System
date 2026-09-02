<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $admin_notification_id
 * @property string $message
 * @property string|null $title
 * @property string|null $link
 * @property string|null $notification_type
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

    protected $fillable = [
        'message',
        'title',
        'link',
        'notification_type',
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
}