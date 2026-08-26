<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Records the outcome of the Railway → MyDumper → Cloudflare R2 backup job.
 *
 * The backup service (cron/script) reports its result back to the app via the
 * "backup report" endpoint or the `backup:report` artisan command, so the Super
 * Admin Dashboard shows real backup evidence rather than hardcoded values.
 */
class BackupHistory extends Model
{
    protected $table = 'backup_history';

    protected $fillable = [
        'backup_name',
        'status',
        'started_at',
        'completed_at',
        'destination',
        'backup_size',
        'file_count',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'backup_size' => 'integer',
        'file_count' => 'integer',
    ];

    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }
}
