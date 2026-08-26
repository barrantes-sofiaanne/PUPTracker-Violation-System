<?php

namespace App\Models;

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
        'target_type',
        'target_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'is_read' => 'boolean',
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
}