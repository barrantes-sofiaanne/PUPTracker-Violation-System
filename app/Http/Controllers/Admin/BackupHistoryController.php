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
     * Report endpoint used by the Railway backup job (MyDumper → Cloudflare R2).
     *
     * Expects a JSON body (or POST fields):
     *   status         : success | failed
     *   backup_name    : e.g. 2026-08-26-backup
     *   started_at     : ISO timestamp (optional)
     *   completed_at   : ISO timestamp (optional)
     *   destination    : default "Cloudflare R2"
     *   backup_size    : bytes (optional)
     *   file_count     : count (optional)
     *   error_message  : required when status=failed
     *
     * Protected by a shared token in config (config('services.backup.report_token')).
     */
    public function report(Request $request)
    {
        $expectedToken = config('services.backup.report_token');

        if ($expectedToken === null || $expectedToken === '') {
            return response()->json(['error' => 'Backup report token is not configured.'], 503);
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
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'destination' => ['nullable', 'string', 'max:255'],
            'backup_size' => ['nullable', 'integer', 'min:0'],
            'file_count' => ['nullable', 'integer', 'min:0'],
            'error_message' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['status'] === 'failed' && empty($validated['error_message'])) {
            return response()->json(['error' => 'error_message is required when status=failed.'], 422);
        }

        $backup = BackupHistory::create([
            'backup_name' => $validated['backup_name'],
            'status' => $validated['status'],
            'started_at' => $validated['started_at'] ?? now(),
            'completed_at' => $validated['completed_at'] ?? now(),
            'destination' => $validated['destination'] ?? 'Cloudflare R2',
            'backup_size' => $validated['backup_size'] ?? null,
            'file_count' => $validated['file_count'] ?? null,
            'error_message' => $validated['error_message'] ?? null,
        ]);

        // Record an audit entry so this also shows up in Audit Logs.
        $this->auditBackup($backup);

        return response()->json([
            'success' => true,
            'backup_history_id' => $backup->id,
        ], 201);
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
                    ? 'Database backup ' . $backup->backup_name . ' completed to ' . ($backup->destination ?: 'Cloudflare R2') . '.'
                    : 'Database backup ' . $backup->backup_name . ' failed: ' . ($backup->error_message ?: 'Unknown error') . '.',
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to write backup audit entry', ['error' => $e->getMessage()]);
        }
    }
}
