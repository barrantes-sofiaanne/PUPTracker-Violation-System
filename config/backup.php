<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup policy
    |--------------------------------------------------------------------------
    |
    | Central, environment-driven configuration for the PUPTracker database
    | backup pipeline. No credentials or secrets are defined here — everything
    | sensitive is read from the environment (.env / Railway variables).
    |
    | Backup types (see app/Models/BackupHistory.php for constants):
    |   - full            : complete logical snapshot that establishes a new
    |                       recovery baseline (runs every FULL_INTERVAL_DAYS).
    |   - incremental     : TRUE MySQL binary-log incremental backup. ONLY used
    |                       when the MySQL server exposes archivable binary logs.
    |   - daily_snapshot  : safe fallback logical snapshot when binary logs are
    |                       NOT available (Railway managed MySQL). It is NEVER
    |                       labelled "incremental".
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Schedule / intervals
    |--------------------------------------------------------------------------
    */

    // How many days must pass since the LAST SUCCESSFUL FULL backup before the
    // daily job promotes itself to a FULL backup. This is elapsed-time based,
    // never a hard-coded calendar date.
    'full_interval_days' => (int) env('BACKUP_FULL_INTERVAL_DAYS', 14),

    // How old a "last successful FULL" record may be before the dashboard is
    // allowed to warn that a full baseline is overdue.
    'full_overdue_grace_days' => (int) env('BACKUP_FULL_OVERDUE_GRACE_DAYS', 17),

    /*
    |--------------------------------------------------------------------------
    | Binary log (TRUE incremental) support
    |--------------------------------------------------------------------------
    */

    // When binary logs cannot be verified the system MUST fail safe and use
    // daily logical snapshots instead of pretending the backup is incremental.
    'binlog_enabled' => (bool) env('BACKUP_BINLOG_ENABLED', true),

    // If true, refuse to run an "incremental" when binary logs are not
    // confirmed. If false the probe is skipped and DAILY_SNAPSHOT is used.
    'binlog_verify' => (bool) env('BACKUP_BINLOG_VERIFY', true),

    // Directory (mounted/copied) that contains the actual binary-log bytes this
    // process can read. Empty/unavailable => TRUE incremental is not possible.
    'binlog_archive_dir' => env('BACKUP_BINLOG_ARCHIVE_DIR', ''),

    // Path to the mysqlbinlog client used to ship archived binary logs.
    'mysqlbinlog_path' => env('BACKUP_MYSQLBINLOG_PATH', 'mysqlbinlog'),

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Backups are stored on the PRIVATE 'backups' disk (Cloudflare R2 via the
    | existing S3-compatible driver already used by the app). Backup objects
    | are never public and are never served through a public Laravel route.
    |
    | BACKUP_STORAGE_DISK:
    |   - "s3"      -> reuse the EXISTING S3-compatible disk (Cloudflare R2 on
    |                  Railway). Configure AWS_ENDPOINT / AWS_ACCESS_KEY_ID /
    |                  AWS_SECRET_ACCESS_KEY / AWS_BUCKET to the private R2
    |                  bucket. (default)
    |   - "backups" -> private local folder (tests / offline development only)
    |
    */

    'disk' => env('BACKUP_STORAGE_DISK', 's3'),

    // Object prefix inside the bucket, e.g. "database-backups".
    'prefix' => trim((string) env('BACKUP_STORAGE_PREFIX', 'database-backups'), '/'),

    // Folder layout under the disk for full backups: full/YYYY/MM/
    'folder_format' => env('BACKUP_FOLDER_FORMAT', 'full/Y/m'),

    /*
    |--------------------------------------------------------------------------
    | Dump command
    |--------------------------------------------------------------------------
    |
    | mysqldump must be reachable from the web/CLI process. Command arguments
    | are built by the service (no user input ever becomes a shell command).
    |
    */

    'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),

    // Extra flags appended to every mysqldump invocation. Kept server-side.
    'mysqldump_extra_flags' => array_values(array_filter(array_map(
        'trim',
        explode(' ', (string) env('BACKUP_MYSQLDUMP_EXTRA_FLAGS', ''))
    ))),

    'dump_timeout_seconds' => (int) env('BACKUP_DUMP_TIMEOUT_SECONDS', 1800),
    'upload_timeout_seconds' => (int) env('BACKUP_UPLOAD_TIMEOUT_SECONDS', 1800),

    /*
    |--------------------------------------------------------------------------
    | Verification
    |--------------------------------------------------------------------------
    |
    | After upload the object existence is verified. Optionally the object is
    | downloaded and hashed again to confirm byte-for-byte integrity.
    | verify_download is expensive; disable for very large databases.
    |
    */

    'verify_download' => (bool) env('BACKUP_VERIFY_DOWNLOAD', false),

    /*
    |--------------------------------------------------------------------------
    | Retention (chain-aware, conservative)
    |--------------------------------------------------------------------------
    |
    | Retention never deletes a FULL backup while dependent INCREMENTAL
    | backups still need it for recovery. The automated prune command runs as
    | a DRY RUN by default; a Super Admin must pass --force after reviewing.
    |
    */

    'retention' => [
        'incremental_days' => (int) env('BACKUP_RETENTION_INCREMENTAL_DAYS', 30),
        'snapshot_days' => (int) env('BACKUP_RETENTION_SNAPSHOT_DAYS', 30),
        'full_days' => (int) env('BACKUP_RETENTION_FULL_DAYS', 90),
        'auto_delete' => (bool) env('BACKUP_RETENTION_AUTO_DELETE', false),
    ],

];
