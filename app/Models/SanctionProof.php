<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $proof_id
 * @property int|null $record_id
 * @property string|null $disk_path
 * @property string|null $original_name
 * @property string|null $mime_type
 * @property int|null $file_size
 * @property string|null $notes
 * @property string|null $status       submitted | accepted | rejected
 * @property int|null $reviewed_by_admin_id
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property string|null $review_remarks
 * @property \Illuminate\Support\Carbon|null $submitted_at
 */
class SanctionProof extends Model
{
    protected $table = 'sanction_proofs_tbl';

    protected $primaryKey = 'proof_id';

    public $timestamps = false;

    const CREATED_AT = null;
    const UPDATED_AT = null;

    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'record_id',
        'disk_path',
        'original_name',
        'mime_type',
        'file_size',
        'notes',
        'status',
        'reviewed_by_admin_id',
        'reviewed_at',
        'review_remarks',
        'submitted_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'file_size' => 'integer',
    ];

    public function getIsImageAttribute(): bool
    {
        return str_starts_with(strtolower((string) $this->mime_type), 'image/');
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(StudentSanctionRecord::class, 'record_id', 'record_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id', 'id');
    }
}
