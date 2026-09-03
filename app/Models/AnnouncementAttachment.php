<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AnnouncementAttachment
 *
 * Metadata row for one physical attachment belonging to an announcement.
 * The actual file lives on a private storage disk (see
 * config('filesystems.announcements_disk')); this model stores only metadata.
 *
 * @property int $attachment_id
 * @property int|null $announcement_id
 * @property string|null $original_filename
 * @property string|null $stored_filename
 * @property string|null $storage_disk
 * @property string|null $storage_path
 * @property string|null $mime_type
 * @property int|null $file_size
 * @property string|null $file_hash
 * @property int|null $uploaded_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Announcement|null $announcement
 */
class AnnouncementAttachment extends Model
{
    protected $table = 'announcement_attachments';

    protected $primaryKey = 'attachment_id';

    protected $fillable = [
        'announcement_id',
        'original_filename',
        'stored_filename',
        'storage_disk',
        'storage_path',
        'mime_type',
        'file_size',
        'file_hash',
        'uploaded_by',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'announcement_id' => 'integer',
        'file_size' => 'integer',
        'uploaded_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Human-friendly file size (e.g. "2.4 MB", "845 KB", "120 B").
     */
    public function getHumanSizeAttribute(): string
    {
        $bytes = (float) ($this->file_size ?? 0);

        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        $value = $bytes / (1024 ** $i);

        return ($i === 0 ? (string) round($value) : number_format($value, 1)) . ' ' . $units[$i];
    }

    /**
     * Whether the file is an image (may render inline).
     */
    public function getIsImageAttribute(): bool
    {
        return str_starts_with(strtolower((string) $this->mime_type), 'image/');
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class, 'announcement_id', 'announcement_id');
    }
}
