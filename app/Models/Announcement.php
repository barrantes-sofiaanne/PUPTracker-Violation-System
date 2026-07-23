<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Admin;
use App\Models\AdminInfo;

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
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function adminInfo(): BelongsTo
    {
        return $this->belongsTo(AdminInfo::class, 'admin_id');
    }
}