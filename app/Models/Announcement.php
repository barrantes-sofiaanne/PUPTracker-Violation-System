<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Admin;
use App\Models\AdminInfo;

/**
 * @property int $announcement_id
 * @property int|null $admin_id
 * @property string $title
 * @property string $content
 * @property string|null $attachment_path
 * @property bool $show_on_login
 * @property \Illuminate\Support\Carbon|null $login_modal_expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Admin|null $admin
 * @property-read AdminInfo|null $adminInfo
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AnnouncementAttachment> $attachments
 */
class Announcement extends Model
{
    use HasFactory;

    protected $table = 'announcements_tbl';

    protected $primaryKey = 'announcement_id';

    protected $fillable = [
        'admin_id',
        'title',
        'content',
        'attachment_path',
        'show_on_login',
        'login_modal_expires_at',
    ];

    protected $casts = [
        'show_on_login' => 'boolean',
        'login_modal_expires_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function adminInfo(): BelongsTo
    {
        return $this->belongsTo(AdminInfo::class, 'admin_id');
    }

    /**
     * Multiple attachments for this announcement.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(AnnouncementAttachment::class, 'announcement_id', 'announcement_id');
    }
}