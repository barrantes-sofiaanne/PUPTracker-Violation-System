<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $evidence_id
 * @property string|null $evidence_code   unique display code EVD-YYYY-NNNNNN
 * @property string|null $capture_session_id  correlating CAM-SESSION-YYYY-NNNNNN
 * @property int|null $violation_id
 * @property string|null $disk_path
 * @property string|null $original_name
 * @property string|null $mime_type
 * @property string|null $capture_method  camera | upload
 * @property bool $watermarked
 * @property bool $server_watermarked
 * @property string|null $sha256_hash
 * @property \Illuminate\Support\Carbon|null $captured_at
 * @property int|null $file_size
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $uploader_type
 * @property int|null $uploader_id
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class ViolationEvidence extends Model
{
    protected $table = 'violation_evidence_tbl';

    protected $primaryKey = 'evidence_id';

    public $timestamps = false;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    public const CAPTURE_CAMERA = 'camera';
    public const CAPTURE_UPLOAD = 'upload';

    protected $fillable = [
        'evidence_code',
        'capture_session_id',
        'violation_id',
        'disk_path',
        'original_name',
        'mime_type',
        'capture_method',
        'watermarked',
        'server_watermarked',
        'sha256_hash',
        'captured_at',
        'file_size',
        'ip_address',
        'user_agent',
        'uploader_type',
        'uploader_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'captured_at' => 'datetime',
        'file_size' => 'integer',
        'watermarked' => 'boolean',
        'server_watermarked' => 'boolean',
    ];

    /**
     * Whether the FINAL stored image had a server-side watermark baked into it.
     * This is distinct from the (untrusted) client `watermarked` flag.
     */
    public function getHasServerWatermarkAttribute(): bool
    {
        return (bool) $this->server_watermarked;
    }

    /**
     * Whether this evidence is an image (renders inline) vs a document.
     */
    public function getIsImageAttribute(): bool
    {
        return str_starts_with(strtolower((string) $this->mime_type), 'image/');
    }

    /**
     * Whether the file was captured through the controlled camera workflow.
     */
    public function getIsCameraCaptureAttribute(): bool
    {
        return $this->capture_method === self::CAPTURE_CAMERA;
    }

    public function violation(): BelongsTo
    {
        return $this->belongsTo(Violation::class, 'violation_id', 'violation_id');
    }

    /**
     * Security events associated with this evidence row (capture portal,
     * camera lifecycle, focus/visibility interruptions, view/download).
     */
    public function securityEvents(): HasMany
    {
        return $this->hasMany(EvidenceSecurityEvent::class, 'evidence_id', 'evidence_id');
    }
}
