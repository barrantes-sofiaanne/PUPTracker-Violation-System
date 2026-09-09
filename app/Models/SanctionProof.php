<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $proof_id
 * @property string|null $proof_code
 * @property int|null $record_id
 * @property string|null $student_number
 * @property string|null $disk_path
 * @property string|null $original_name
 * @property string|null $mime_type
 * @property int|null $file_size
 * @property string|null $sha256_hash
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
        'proof_code',
        'record_id',
        'student_number',
        'disk_path',
        'original_name',
        'mime_type',
        'file_size',
        'sha256_hash',
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

    /**
     * Generate the next unique proof code: PROOF-{YEAR}-{6-digit sequence}.
     */
    public static function nextProofCode(?int $year = null): string
    {
        $year = $year ?? (int) now()->format('Y');
        $prefix = 'PROOF-' . $year . '-';

        $last = static::query()
            ->where('proof_code', 'like', $prefix . '%')
            ->orderByDesc('proof_id')
            ->value('proof_code');

        if ($last) {
            $seq = ((int) substr((string) $last, strlen($prefix))) + 1;

            return $prefix . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
        }

        return $prefix . '000001';
    }

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
