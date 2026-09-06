# PUPTracker Violation System — Database Backup & Recovery

**Last updated:** 2026-09-06
**Status:** Implemented

This document describes the ACTUAL database backup implementation in this
repository. It intentionally does **not** claim features the deployment cannot
provide (notably TRUE incremental backups).

---

## 1. Backup types

| Type             | Meaning                                                                                                                                          | When it runs                                                                                                               |
| ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------- |
| `FULL`           | Complete, compressed logical snapshot of the whole database (tables + data + triggers + routines + events). Establishes a new recovery baseline. | Once every **14 days**, measured from the **last SUCCESSFUL FULL** backup. Also runs when there is no successful FULL yet. |
| `INCREMENTAL`    | TRUE MySQL binary-log incremental (changes since the previous backup / binlog position).                                                         | Only when the MySQL server exposes **readable, archivable binary logs** (see §3).                                          |
| `DAILY_SNAPSHOT` | Safe fallback: a complete logical snapshot taken every day when binary logging is NOT available.                                                 | Every other day (when a FULL is not due and TRUE incremental is impossible).                                               |

A daily logical `mysqldump` is **never** labelled "incremental".

### 1.1 The daily decision

A single scheduled job (`php artisan backup:run`) runs once per day at **02:00
Asia/Manila**. It decides the type like this:

1. Find the latest **SUCCESSFUL** `FULL` backup (`backup_history` where
   `status=success` and `backup_type=full`, ordered by `completed_at`).
2. If none exists → create a **FULL**.
3. If 14+ days have passed since that successful FULL → create a **FULL**.
4. Otherwise → create an **INCREMENTAL** if binary logs are supported.
5. Otherwise → create a **DAILY_SNAPSHOT**.

The 14-day window is elapsed time from the last successful FULL — never a
hard-coded calendar date (not the 1st/15th).

**Failures do not reset the interval.** If a FULL is attempted on day 14 and
fails, the next run still sees that the last successful FULL is day 0 and keeps
trying until a FULL succeeds.

---

## 2. TRUE incremental support (binary logs)

A TRUE incremental backup requires:

- `log_bin = ON`
- `SHOW BINARY LOGS` succeeds (logs exist and are readable)
- `binlog_format` is `ROW`, `STATEMENT`, or `MIXED`
- the actual binary-log **bytes** are copied/mounted into a directory the app
  can read (`BACKUP_BINLOG_ARCHIVE_DIR`)
- the `mysqlbinlog` client is reachable (`BACKUP_MYSQLBINLOG_PATH`)

The system probes this at runtime (`php artisan backup:status`, and internally
before every decision) using:

```sql
SHOW VARIABLES LIKE 'log_bin';
SHOW VARIABLES LIKE 'binlog_format';
SHOW BINARY LOGS;
```

### 2.1 Railway limitation — safe fallback

Railway's **managed MySQL does not expose readable binary-log files** to the
application container. In that environment the probe reports _unavailable_, so
the system does **not** fake incremental backups. It uses:

> True MySQL incremental backups require binary-log support. When binary
> logging is unavailable, the system uses daily logical snapshots
> (`DAILY_SNAPSHOT`) as the safe fallback.

The UI never labels a daily snapshot as "incremental".

---

## 3. How a backup is created and recorded

Required flow (a backup is **not** successful merely because a file was made):

```
BACKUP STARTED
   -> DATABASE EXPORT (mysqldump --single-transaction --routines --triggers --events)
   -> COMPRESSION (gzip, streamed)
   -> CHECKSUM (SHA-256)
   -> UPLOAD TO CLOUDFLARE R2 (private)
   -> VERIFY OBJECT EXISTS
   -> (optional) DOWNLOAD + RE-HASH (BACKUP_VERIFY_DOWNLOAD=true)
   -> RECORD SUCCESS in backup_history
```

Any failure records `status=failed` with a sanitized `error_message`, timestamps,
and **no** `verified_at`; it does not update the "last successful full".

Artisan commands:

| Command                     | Purpose                                                                                        |
| --------------------------- | ---------------------------------------------------------------------------------------------- |
| `php artisan backup:run`    | Run the daily backup (decides FULL / INCREMENTAL / DAILY_SNAPSHOT). `--type=` forces one type. |
| `php artisan backup:status` | Read-only status: binlog support, next FULL due, latest history.                               |
| `php artisan backup:prune`  | Chain-aware retention (dry run by default; `--force` deletes).                                 |
| `php artisan backup:report` | Record an externally-produced backup result (backward compatible).                             |

---

## 4. Storage — Cloudflare R2 (private)

Backups reuse the **existing** S3-compatible disk (`s3` in
`config/filesystems.php`), which points at the private Cloudflare R2 bucket via
the environment variables the project already uses:

- `AWS_ENDPOINT` (R2: `https://<ACCOUNT_ID>.r2.cloudflarestorage.com`)
- `AWS_ACCESS_KEY_ID`
- `AWS_SECRET_ACCESS_KEY`
- `AWS_DEFAULT_REGION=auto`
- `AWS_BUCKET` (private bucket)

There is **no second** R2/cloud disk and **no** new cloud provider. Backups stay
**private**: the `s3` disk has no public URL mapping, backup objects are never
placed under `storage/app/public`, and there is no public download route.

Object layout inside the bucket (prefix configurable):

```
database-backups/
├── full/2026/09/full_2026-09-15_020000.sql.gz
├── incremental/2026/09/incremental_2026-09-16_020000.sql.gz   (binlog path only)
└── snapshot/2026/09/snapshot_2026-09-16_020000.sql.gz           (fallback path)
```

For local development / tests set `BACKUP_STORAGE_DISK=backups` (private local
folder `storage/app/backups`). In production set `BACKUP_STORAGE_DISK=s3`.

---

## 5. Backup history

The existing `backup_history` table and `BackupHistory` model are reused (no
second table). An additive migration
`2026_09_06_000001_add_backup_policy_fields_to_backup_history.php` added:

- `backup_type` (`full` | `incremental` | `daily_snapshot`)
- `parent_backup_id` (recovery-chain parent / base FULL)
- `checksum`, `checksum_algorithm`
- `verified_at`
- `storage_path`
- `binlog_file_start`, `binlog_position_start`, `binlog_file_end`,
  `binlog_position_end`, `binlog_timestamp_start`, `binlog_timestamp_end`

The migration is additive and reversible and preserves existing rows.

The Super Admin backup history UI shows type, status, start/completion time,
size, destination, integrity/checksum state, verification time, error messages,
and the parent/base backup.

---

## 6. Integrity / checksum

- Every successful backup records a **SHA-256** checksum (`checksum_algorithm=SHA-256`).
- The checksum is computed over the compressed object before upload.
- When `BACKUP_VERIFY_DOWNLOAD=true` the uploaded object is downloaded and
  re-hashed after upload to prove byte-for-byte integrity.
- `verified_at` is recorded **only after** the object existence check passes.
- Checksums never contain credentials.

---

## 7. Security

- Backup files are private; no public URL / route.
- R2 credentials are environment variables only, never in source control or JS.
- DB credentials are never written to logs or error messages; `MYSQL_PWD` is
  used for the dump sub-process (never `--password=` on the command line).
- Raw SQL dumps are never written to application logs.
- No user input becomes a shell command: sub-processes run with argument arrays
  (Symfony Process, shell-less).
- The report endpoint is protected by `BACKUP_REPORT_TOKEN` compared with
  `hash_equals`. The token stays server-side.
- Backup history is treated as audit data and is only reachable by Super Admins
  (`super.admin` middleware). Students and normal admins cannot access backups.

---

## 8. Retention

Defaults (configurable in `.env`):

- Daily snapshots: 30 days
- Incrementals: 30 days
- FULL backups: 90 days

Retention is **chain-aware**: a FULL backup is never deleted while a retained
incremental recovery chain still depends on it. Because automatic deletion is
risky, `php artisan backup:prune` runs as a **dry run** by default and only
deletes with `--force` after a Super Admin reviews the plan.
`BACKUP_RETENTION_AUTO_DELETE=false` keeps the scheduled prune non-destructive.

---

## 9. Restore procedure

### 9.1 FULL + INCREMENTAL (binlog path, when available)

```
1. Restore the most recent FULL backup into a target database.
2. Apply each subsequent INCREMENTAL (in chain order, from its parent boundary).
3. Verify the database (row counts / important tables).
4. Return to service.
```

### 9.2 DAILY SNAPSHOT fallback

```
1. Choose the desired snapshot.
2. Restore it into the target database.
3. Verify the database.
4. Return to service.
```

### 9.3 Safe restore / restore testing (never touches production)

There is **no** destructive one-click production restore. To validate a backup
without affecting production:

1. Download the object from R2 (private).
2. Verify its SHA-256 checksum against `backup_history`.
3. Decompress.
4. Restore into an **isolated temporary database**.
5. Run schema validation.
6. Check important tables (`users_tbl`, `student_info_tbl`, `violation_tbl`,
   `violation_type_tbl`, `violation_category_tbl`, `violation_evidence_tbl`,
   `student_sanction_records_tbl`, `disciplinary_sanctions`, `audit_logs`,
   `backup_history`).
7. Verify row counts where appropriate.
8. Confirm the application can connect.
9. Delete the temporary restore database.

---

## 10. Required environment variables

| Variable                            | Purpose                                        | Example              |
| ----------------------------------- | ---------------------------------------------- | -------------------- |
| `BACKUP_STORAGE_DISK`               | `s3` (R2) or `backups` (local/test)            | `s3`                 |
| `BACKUP_STORAGE_PREFIX`             | Object prefix in the bucket                    | `database-backups`   |
| `BACKUP_FULL_INTERVAL_DAYS`         | Full interval from last successful FULL        | `14`                 |
| `BACKUP_MYSQLDUMP_PATH`             | mysqldump binary                               | `mysqldump`          |
| `BACKUP_BINLOG_ENABLED`             | Allow binlog probing                           | `true`               |
| `BACKUP_BINLOG_VERIFY`              | Require confirmation before "incremental"      | `true`               |
| `BACKUP_BINLOG_ARCHIVE_DIR`         | Readable binlog directory (self-managed MySQL) | _(empty on Railway)_ |
| `BACKUP_MYSQLBINLOG_PATH`           | mysqlbinlog client                             | `mysqlbinlog`        |
| `BACKUP_VERIFY_DOWNLOAD`            | Download + re-hash after upload                | `false`              |
| `BACKUP_RETENTION_FULL_DAYS`        | Keep FULLs (days)                              | `90`                 |
| `BACKUP_RETENTION_INCREMENTAL_DAYS` | Keep incrementals (days)                       | `30`                 |
| `BACKUP_RETENTION_SNAPSHOT_DAYS`    | Keep snapshots (days)                          | `30`                 |
| `BACKUP_RETENTION_AUTO_DELETE`      | Allow auto prune (else dry-run)                | `false`              |
| `BACKUP_REPORT_TOKEN`               | Shared token for the report webhook            | _(secret)_           |

R2/cloud variables (existing): `AWS_ENDPOINT`, `AWS_ACCESS_KEY_ID`,
`AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`.

All are documented in `.env.example` without real credentials.

---

## 11. Railway deployment notes

- The scheduler runs `php artisan backup:run` at 02:00 Asia/Manila and
  `php artisan backup:prune` (dry run) at 03:30. On Railway, a worker/repeatable
  service should run `php artisan schedule:work` (or a cron line calling
  `php artisan schedule:run` every minute).
- `mysqldump` must be present in the runtime image.
- The web/worker process must be able to reach R2 (network egress) with the
  AWS/R2 variables set.
- Railway managed MySQL: binary logs are not accessible to the container, so
  the app uses DAILY_SNAPSHOT. To enable TRUE incrementals you would need a
  MySQL deployment that exposes/copies binary logs into `BACKUP_BINLOG_ARCHIVE_DIR`.
