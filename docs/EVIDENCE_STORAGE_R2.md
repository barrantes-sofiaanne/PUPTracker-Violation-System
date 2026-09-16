# Evidence & Proof Storage (Cloudflare R2)

Violation evidence and sanction proof-of-completion files are **confidential**.
Their bytes live in a private object store (Cloudflare R2, reached through the
S3-compatible disk); MySQL stores only the object key plus metadata. Retrieval is
always streamed through an authorized Laravel route — the bucket is never public
and no direct URL is ever handed to the browser.

```
Upload
  ↓
Configured evidence disk (EVIDENCE_STORAGE_DISK=s3)
  ↓
Cloudflare R2 object
  ↓
Object key saved in MySQL
  ↓
Fresh request → read the key from MySQL → configured disk → R2
  ↓
Authorized Laravel stream
```

## Where the object key is stored

| Purpose                     | Table                          | Column       |
| --------------------------- | ------------------------------ | ------------ |
| Violation evidence          | `violation_evidence_tbl`       | `disk_path`  |
| Current proof of completion | `student_sanction_records_tbl` | `proof_path` |
| Proof history (audit trail) | `sanction_proofs_tbl`          | `disk_path`  |

These columns hold the **object key only** (for example
`violation-evidence/32/EVD-2026-000001.jpg`). Never a full URL, and never a
filesystem path. The legacy `violation_tbl.evidence_path` column is left in place
untouched; the multi-file workflow uses `violation_evidence_tbl`.

## Required Railway environment variables

Set these on the application service. All are read through the existing `s3`
disk definition in `config/filesystems.php` — there is **no second set** of
storage variables.

| Variable                      | Value                                           |
| ----------------------------- | ----------------------------------------------- |
| `EVIDENCE_STORAGE_DISK`       | `s3`                                            |
| `AWS_ACCESS_KEY_ID`           | R2 access key id                                |
| `AWS_SECRET_ACCESS_KEY`       | R2 secret access key                            |
| `AWS_DEFAULT_REGION`          | `auto`                                          |
| `AWS_BUCKET`                  | the private bucket name                         |
| `AWS_ENDPOINT`                | `https://<ACCOUNT_ID>.r2.cloudflarestorage.com` |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `false`                                         |

`ANNOUNCEMENT_STORAGE_DISK=s3` uses the same variables.

> **Do not commit credentials.** Configure them in the Railway dashboard, not in
> a tracked file.

## Configuration caching (important)

If the configuration is cached, changing an environment variable has **no
effect until the cache is rebuilt**. After adding or editing any of the variables
above, redeploy or run:

```
php artisan config:clear
php artisan config:cache
```

A stale configuration cache is one of the ways `EVIDENCE_STORAGE_DISK` can appear
to be ignored.

## Why a local disk is never a valid production fallback

Railway's filesystem (`storage/app/private`, `storage/app/public`,
`public/storage`) is **ephemeral**. A file written there is lost on redeploy,
container replacement or restart, while the database row survives — which
presents as "the evidence existed yesterday and is now a 404".

For that reason the application does not silently fall back to `local`:

- In **production**, if the configured disk names `s3` but the bucket or
  credentials are missing, uploads are **refused** with an actionable message
  instead of writing to ephemeral storage.
- Outside production (tests, local development) a local disk is fine and the
  guard is inert.

Every evidence/proof disk lookup goes through one class,
`App\Support\Storage\EvidenceDisk`, so uploads and reads can never resolve
different disks.

## Diagnosing a file that will not open

```
php artisan evidence:diagnose
php artisan evidence:diagnose --record=evidence:32
php artisan evidence:diagnose --record=proof:77
```

It prints the configured disk, whether R2 is actually reachable (a real
write/read/delete probe), and classifies each inspected row:

| Case  | Meaning                                                       | Typical fix                                            |
| ----- | ------------------------------------------------------------- | ------------------------------------------------------ |
| **A** | DB reference missing (`disk_path`/`proof_path` NULL or empty) | The upload never completed; re-upload                  |
| **B** | DB reference exists, object missing from storage              | Object deleted or uploaded to a disk that was replaced |
| **C** | Object exists on a **different** disk than configured         | Fix `EVIDENCE_STORAGE_DISK`; do not re-upload          |
| **D** | Object present on the configured disk                         | Storage is fine — look at retrieval/authorization      |

Object keys are never printed. The command reports a SHA-256 hash prefix, which
is enough to correlate two records without exposing bucket contents.

## Legacy key shapes

Stored keys are normalised for lookup only — the database row is **never
rewritten**. These all resolve to the same object:

```
violation-evidence/32/EVD-2026-000001.jpg
/violation-evidence/32/EVD-2026-000001.jpg
violation-evidence//32///EVD-2026-000001.jpg
storage/violation-evidence/32/EVD-2026-000001.jpg
violation-evidence%2F32%2FEVD-2026-000001.jpg
https://<endpoint>/<bucket>/violation-evidence/32/EVD-2026-000001.jpg
```

## Authorization is unchanged

Evidence stays confidential. Reading a file still requires the same role checks
as before:

- Security — according to the existing recorder authorization.
- Admin — according to existing admin permissions.
- Student — confirmed evidence on their own record, and their own proof.

The storage fix never widens access: files are streamed through authorized
routes, and the bucket stays private.

## Sanction proof history

Every proof submission writes a new object (`PROOF-YYYY-NNNNNN.ext`) and a new
`sanction_proofs_tbl` row, so the audit trail stays intact:

```
Proof #1 → rejected
Proof #2 → accepted
```

A resubmission never overwrites an earlier proof object, and
`student_sanction_records_tbl.proof_path` always points at the current/latest
submission.
