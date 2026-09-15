<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BackupHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BackupHistoryController extends Controller
{
    /**
     * Super Admin: list backup history (newest first).
     */
    public function index(Request $request)
    {
        $query = BackupHistory::query()->latest('completed_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('type')) {
            // Only filter by type when the column exists (pre-policy schemas).
            if (\App\Models\BackupHistory::hasColumn('backup_type')) {
                $query->where('backup_type', $request->string('type')->toString());
            }
        }

        if ($request->filled('search')) {
            $search = '%' . $request->string('search')->toString() . '%';
            $query->where('backup_name', 'like', $search);
        }

        $backups = $query->paginate(20)->withQueryString();

        return view('admin.super-admin.backup-history', compact('backups'));
    }

    /**
     * Super Admin: show a single backup detail.
     */
    public function show(BackupHistory $backup)
    {
        return view('admin.super-admin.backup-show', compact('backup'));
    }

    /**
     * Report endpoint used by the Railway backup job (MyDumper → Cloudflare R2)
     * and the in-app `backup:run` pipeline.
     *
     * Expects a JSON body (or POST fields):
     *   status          : success | failed
     *   backup_name     : e.g. 2026-08-26-backup
     *   backup_type     : full | incremental | daily_snapshot (optional)
     *   started_at      : ISO timestamp (optional)
     *   completed_at    : ISO timestamp (optional)
     *   destination     : default "Cloudflare R2"
     *   backup_size     : bytes (optional)
     *   file_count      : count (optional)
     *   checksum        : SHA-256 hex (optional)
     *   checksum_algorithm : default SHA-256
     *   verified_at     : ISO timestamp (optional)
     *   storage_path    : private object key (optional)
     *   parent_backup_id: recovery-chain parent (optional)
     *   base_backup_name: FULL backup name an incremental depends on (optional)
     *   binlog_file_start / binlog_position_start : incremental start boundary
     *   binlog_file_end / binlog_position_end     : incremental end boundary
     *   error_message   : required when status=failed
     *
     * Protected by a shared token in config (config('services.backup.report_token')).
     * The same backup_name may be reported more than once; later reports UPDATE
     * the existing row instead of creating duplicates.
     */
    public function report(Request $request)
    {
        try {
            return $this->handleReport($request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation errors as-is (they are already structured and
            // reveal only field-level messages, never internals).
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Backup report webhook failed', [
                'error' => \Illuminate\Support\Str::limit($e->getMessage(), 300),
            ]);

            return response()->json(['error' => 'Unable to process backup report.'], 500);
        }
    }

    private function handleReport(Request $request)
    {
        $expectedToken = config('services.backup.report_token');

        if ($expectedToken === null || $expectedToken === '') {
            return response()->json(['error' => 'Backup report service is not configured.'], 503);
        }

        $providedToken = $request->bearerToken()
            ?? $request->input('token')
            ?? $request->header('X-Backup-Token');

        if (! hash_equals((string) $expectedToken, (string) $providedToken)) {
            return response()->json(['error' => 'Unauthorized.'], 401);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:success,failed'],
            'backup_name' => ['required', 'string', 'max:255'],
            'backup_type' => ['nullable', 'string', 'in:full,incremental,daily_snapshot'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'destination' => ['nullable', 'string', 'max:255'],
            'backup_size' => ['nullable', 'integer', 'min:0'],
            'file_count' => ['nullable', 'integer', 'min:0'],
            // Checksum must be a SHA-256 hex digest when provided (64 chars,
            // hex only). The application only ever produces SHA-256.
            'checksum' => ['nullable', 'string', 'regex:/^[a-f0-9]{64}$/i'],
            'checksum_algorithm' => ['nullable', 'string', 'in:SHA-256,sha256'],
            'verified_at' => ['nullable', 'date'],
            'storage_path' => ['nullable', 'string', 'max:500'],
            'parent_backup_id' => ['nullable', 'integer', 'exists:backup_history,id'],
            // Recovery-chain metadata for TRUE incrementals. All optional so the
            // existing FULL / daily_snapshot contract is unchanged.
            'base_backup_name' => ['nullable', 'string', 'max:255'],
            'base_full_storage_path' => ['nullable', 'string', 'max:500'],
            'binlog_file_start' => ['nullable', 'string', 'max:255'],
            'binlog_position_start' => ['nullable', 'integer', 'min:0'],
            'binlog_file_end' => ['nullable', 'string', 'max:255'],
            'binlog_position_end' => ['nullable', 'integer', 'min:0'],
            'error_message' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['status'] === 'failed' && empty($validated['error_message'])) {
            return response()->json(['error' => 'error_message is required when status=failed.'], 422);
        }

        $completedAt = $validated['completed_at'] ?? now();

        $attributes = [
            'backup_name' => $validated['backup_name'],
            'backup_type' => $validated['backup_type'] ?? null,
            'status' => $validated['status'],
            'started_at' => $validated['started_at'] ?? $completedAt,
            'completed_at' => $completedAt,
            'destination' => $validated['destination'] ?? 'Cloudflare R2',
            'backup_size' => $validated['backup_size'] ?? null,
            'file_count' => $validated['file_count'] ?? null,
            'checksum' => $validated['checksum'] ?? null,
            'checksum_algorithm' => $validated['checksum_algorithm'] ?? 'SHA-256',
            // verified_at is only set when the reporter explicitly states the
            // backup passed verification. It is never assumed from status alone.
            'verified_at' => $validated['verified_at'] ?? null,
            'storage_path' => $validated['storage_path'] ?? null,
            'parent_backup_id' => $validated['parent_backup_id'] ?? null,
            // base_backup_name is the human-readable FULL the incremental depends
            // on. Null for FULL / daily_snapshot. Only present on newer schema.
            'base_backup_name' => $validated['backup_type'] === 'incremental'
                ? ($validated['base_backup_name'] ?? null)
                : null,
            // Binlog boundary — only meaningful for TRUE incrementals.
            'binlog_file_start' => $validated['backup_type'] === 'incremental'
                ? ($validated['binlog_file_start'] ?? null)
                : null,
            'binlog_position_start' => $validated['backup_type'] === 'incremental'
                ? ($validated['binlog_position_start'] ?? null)
                : null,
            'binlog_file_end' => $validated['backup_type'] === 'incremental'
                ? ($validated['binlog_file_end'] ?? null)
                : null,
            'binlog_position_end' => $validated['backup_type'] === 'incremental'
                ? ($validated['binlog_position_end'] ?? null)
                : null,
            // error_message is only persisted when the report status is failed.
            'error_message' => $validated['status'] === 'failed'
                ? ($validated['error_message'] ?? null)
                : null,
        ];

        $existing = BackupHistory::query()
            ->where('backup_name', $validated['backup_name'])
            ->latest('id')
            ->first();

        if ($existing) {
            // Do not attempt to write columns that do not exist on legacy
            // schemas (pre-policy deployment that has not migrated yet).
            $writeable = array_filter($attributes, fn ($value, $key) => BackupHistory::hasColumn($key), ARRAY_FILTER_USE_BOTH);
            $existing->forceFill($writeable)->save();
            $backup = $existing;
        } else {
            $writeable = array_filter($attributes, fn ($value, $key) => BackupHistory::hasColumn($key), ARRAY_FILTER_USE_BOTH);
            $backup = BackupHistory::create($writeable);
        }

        // Record an audit entry so this also shows up in Audit Logs.
        $this->auditBackup($backup);

        // §7 — a failed backup MUST reach the Super Admin as a system alert.
        // Deliberately AFTER the audit entry: the backup record is the source of
        // truth and must exist before anything tries to notify about it.
        $this->notifySuperAdminOfBackup($backup);

        return response()->json([
            'success' => true,
            'backup_history_id' => $backup->id,
        ], $existing ? 200 : 201);
    }

    /**
     * Raise a SYSTEM-LEVEL Super Admin alert for a backup that needs attention.
     *
     * §7 flow: backup runs -> fails -> record failure -> Super Admin system
     * notification -> Super Admin reviews Backup Management.
     *
     * Only failures and unverified backups notify. A successful, verified backup
     * is a normal system event and would only add noise (§5: do not flood), so
     * it is recorded in backup_history + audit_logs and stays silent here.
     *
     * This never throws into the webhook: the external backup job must still get
     * a clean 2xx even if notification writing fails, otherwise it would retry
     * and duplicate the backup record.
     */
    private function notifySuperAdminOfBackup(BackupHistory $backup): void
    {
        try {
            if ($backup->isSuccessful() && $backup->verified_at !== null) {
                return;
            }

            $service = app(\App\Services\AdminNotificationService::class);
            $typeLabel = method_exists($backup, 'typeLabel') ? $backup->typeLabel() : 'database';
            $destination = $backup->destination ?: 'Cloudflare R2';

            if (! $backup->isSuccessful()) {
                // A failed backup is CRITICAL: there is no usable recovery point.
                $reason = trim((string) $backup->error_message);

                $service->createSystemAlert(
                    type: 'system_backup_failure',
                    title: 'Database Backup Failed',
                    message: 'The scheduled ' . $typeLabel . ' backup "' . $backup->backup_name
                        . '" FAILED and no recovery point was produced. Reason: '
                        . ($reason !== '' ? $reason : 'not reported by the backup job.')
                        . ' Review Backup Management immediately.',
                    targetType: 'backup',
                    targetId: (int) $backup->id,
                    priority: \App\Models\AdminNotification::PRIORITY_CRITICAL,
                );

                return;
            }

            // Succeeded but never verified — worth attention, not an emergency.
            $service->createSystemAlert(
                type: 'system_backup_unverified',
                title: 'Backup Verification Outstanding',
                message: 'The ' . $typeLabel . ' backup "' . $backup->backup_name
                    . '" completed to ' . $destination
                    . ' but has not been verified. Integrity is unconfirmed.',
                targetType: 'backup',
                targetId: (int) $backup->id,
                priority: \App\Models\AdminNotification::PRIORITY_WARNING,
            );
        } catch (\Throwable $e) {
            // Never break the backup report webhook.
            \Illuminate\Support\Facades\Log::warning('Unable to raise Super Admin backup notification', [
                'backup_id' => $backup->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function auditBackup(BackupHistory $backup): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        try {
            AuditLog::create([
                'actor_type' => 'system',
                'actor_id' => null,
                'action' => $backup->isSuccessful()
                    ? 'Backup Completed'
                    : 'Backup Failed',
                'module' => 'Backup',
                'description' => $backup->isSuccessful()
                    ? 'Database ' . $backup->typeLabel() . ' backup ' . $backup->backup_name . ' completed to ' . ($backup->destination ?: 'Cloudflare R2') . '.'
                    : 'Database ' . $backup->typeLabel() . ' backup ' . $backup->backup_name . ' failed: ' . ($backup->error_message ?: 'Unknown error') . '.',
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to write backup audit entry', ['error' => $e->getMessage()]);
        }
    }
}
