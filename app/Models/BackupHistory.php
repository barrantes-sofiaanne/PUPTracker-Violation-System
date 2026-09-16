<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Records the outcome of every PUPTracker database backup.
 *
 * The backup report webhook (`POST /admin/super-admin/backups/report`) and the
 * `backup:report` Artisan command keep this table in sync when backups are
 * produced OUTSIDE the application (the Railway/MyDumper external job). The
 * in-app pipeline (`backup:run`) writes here directly.
 *
 * Status values remain "success" / "failed" (existing convention). The backup
 * TYPE is tracked separately:
 *   - full            : complete logical snapshot / recovery baseline
 *   - incremental     : TRUE MySQL binary-log incremental backup
 *   - daily_snapshot  : safe logical snapshot fallback (never called incremental)
 */
class BackupHistory extends Model
{
    protected $table = 'backup_history';

    protected $fillable = [
        'backup_name',
        'backup_type',
        'parent_backup_id',
        'base_backup_name',
        'status',
        'started_at',
        'completed_at',
        'destination',
        'backup_size',
        'file_count',
        'checksum',
        'checksum_algorithm',
        'verified_at',
        'storage_path',
        'binlog_file_start',
        'binlog_position_start',
        'binlog_file_end',
        'binlog_position_end',
        'binlog_timestamp_start',
        'binlog_timestamp_end',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'verified_at' => 'datetime',
        'binlog_timestamp_start' => 'datetime',
        'binlog_timestamp_end' => 'datetime',
        'backup_size' => 'integer',
        'file_count' => 'integer',
        'parent_backup_id' => 'integer',
        'binlog_position_start' => 'integer',
        'binlog_position_end' => 'integer',
    ];

    // Status values (existing convention).
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    // Backup types.
    public const TYPE_FULL = 'full';
    public const TYPE_INCREMENTAL = 'incremental';
    public const TYPE_DAILY_SNAPSHOT = 'daily_snapshot';

    public const TYPES = [
        self::TYPE_FULL,
        self::TYPE_INCREMENTAL,
        self::TYPE_DAILY_SNAPSHOT,
    ];

    // Human-readable labels.
    public const TYPE_LABELS = [
        self::TYPE_FULL => 'FULL',
        self::TYPE_INCREMENTAL => 'INCREMENTAL',
        self::TYPE_DAILY_SNAPSHOT => 'DAILY SNAPSHOT',
    ];

    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeFull($query)
    {
        // Backwards/schema-compatible: only filter on backup_type when the
        // column actually exists (pre-policy deployments / legacy tables).
        if (self::hasColumn('backup_type')) {
            return $query->where('backup_type', self::TYPE_FULL);
        }

        // Without a type column we cannot distinguish a FULL from other rows;
        // fall back to all successful rows so the UI never breaks.
        return $query->where('status', self::STATUS_SUCCESS);
    }

    /**
     * Cache-aware column existence check that never throws on a missing table.
     *
     * CACHE SCOPE — important
     * -----------------------
     * The memo is keyed by CONNECTION + DATABASE, not just by column name. A
     * bare `static $columns` used to persist for the whole PHP process, so once
     * any code path touched a schema without the column (e.g. a lean test
     * fixture), the `false` answer was replayed against every LATER schema in
     * that process — even one that really had the column. In the test suite that
     * silently dropped `base_backup_name` / `backup_type` from later tests and
     * broke backup reporting with `Undefined array key "backup_type"`.
     *
     * Scoping the memo to the active connection removes the leak in-process
     * (a fresh connection = a fresh cache) while keeping the per-request benefit.
     */
    public static function hasColumn(string $column): bool
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('backup_history')) {
                return false;
            }

            $connection = \Illuminate\Support\Facades\Schema::getConnection()->getName()
                . '|' . \Illuminate\Support\Facades\Schema::getConnection()->getDatabaseName();

            $key = $connection . '|' . $column;

            if (! array_key_exists($key, self::$columnCache)) {
                self::$columnCache[$key] = \Illuminate\Support\Facades\Schema::hasColumn('backup_history', $column);
            }

            return self::$columnCache[$key];
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Forget the memoised column list.
     *
     * Called by the test suite between tests (see Tests\TestCase), and usable by
     * any long-running process — a queue worker, for example — that must observe
     * a schema change without being restarted.
     */
    public static function forgetColumnCache(): void
    {
        self::$columnCache = [];
    }

    /** Backing store for hasColumn(); keyed by connection + database + column. */
    private static array $columnCache = [];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_backup_id');
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function typeLabel(): string
    {
        if ($this->backup_type === null) {
            // Records created before the type column existed.
            return '—';
        }

        return self::TYPE_LABELS[$this->backup_type] ?? strtoupper($this->backup_type);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null && $this->isSuccessful();
    }

    public function isChecksumVerified(): bool
    {
        return $this->checksum !== null
            && $this->checksum_algorithm !== null
            && $this->verified_at !== null;
    }

    /**
     * True when the object still exists on its storage disk.
     */
    public function isStorageAvailable(): bool
    {
        if (! $this->storage_path) {
            return false;
        }

        try {
            return Storage::disk($this->storageDisk())->exists($this->storage_path);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function storageDisk(): string
    {
        return (string) config('backup.disk', 's3');
    }

    public function displayDestination(): string
    {
        return $this->destination ?: 'Cloudflare R2';
    }

    public function displayChecksum(): string
    {
        if (! $this->checksum_algorithm) {
            return $this->checksum ? $this->checksum : '—';
        }

        return $this->checksum_algorithm . ' ' . ($this->checksum ?? '—');
    }
}
