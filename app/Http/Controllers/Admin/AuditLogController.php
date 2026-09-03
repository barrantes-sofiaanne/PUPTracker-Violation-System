<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * The audit trail viewer is served to every authenticated admin, but its
     * contents are scoped server-side by role:
     *   - Super Admin (IT Administrator): sees the full system-wide trail.
     *   - Normal Admin: sees only operational audit entries — privileged
     *     modules (Backup, Maintenance, System Logs, role/permission config,
     *     etc.) and other Super Admin activity are excluded. This is enforced
     *     in the query, not by hiding buttons.
     */
    public function index(Request $request)
    {
        /** @var \App\Models\Admin $admin */
        $admin = auth('admin')->user();
        $isSuperAdmin = $admin->isItAdministrator();

        $query = AuditLog::query()->latest('created_at');

        // Role scoping (server-side). Normal Admin cannot see privileged modules.
        if (! $isSuperAdmin) {
            $query->where(function ($q): void {
                $q->whereNull('module')
                    ->orWhereNotIn('module', AuditLogService::PRIVILEGED_MODULES);
            });
        }

        if ($request->filled('actor_type')) {
            $query->where('actor_type', $request->string('actor_type')->toString());
        }

        if ($request->filled('module')) {
            $query->where('module', $request->string('module')->toString());
        }

        if ($request->filled('keyword')) {
            $keyword = '%' . $request->string('keyword')->toString() . '%';
            $query->where(function ($innerQuery) use ($keyword): void {
                $innerQuery->where('action', 'like', $keyword)
                    ->orWhere('description', 'like', $keyword);
            });
        }

        // Date-range filter (from/to on created_at).
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->string('from')->toString());
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->string('to')->toString());
        }

        $logs = $query->paginate(15)->withQueryString();

        // The actor/module dropdowns are scoped to what the admin can see.
        $visibleActorTypes = $this->visibleActorTypes($isSuperAdmin);
        $visibleModules = $this->visibleModules($isSuperAdmin);

        $actorTypes = AuditLog::query()
            ->select('actor_type')
            ->whereNotNull('actor_type')
            ->when(! $isSuperAdmin, fn ($q) => $q->whereIn('actor_type', $visibleActorTypes))
            ->distinct()
            ->orderBy('actor_type')
            ->pluck('actor_type');

        $modules = AuditLog::query()
            ->select('module')
            ->whereNotNull('module')
            ->when(! $isSuperAdmin, fn ($q) => $q->whereIn('module', $visibleModules))
            ->distinct()
            ->orderBy('module')
            ->pluck('module');

        return view('admin.audit-logs.index', compact('logs', 'actorTypes', 'modules', 'isSuperAdmin'));
    }

    /**
     * Export the currently-filtered audit trail as CSV.
     *
     * Exports are restricted to Super Admin and the export itself is audited
     * (accountability for data extraction).
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        /** @var \App\Models\Admin $admin */
        $admin = auth('admin')->user();

        if (! $admin || ! $admin->isItAdministrator()) {
            abort(403, 'Only the IT Administrator can export the audit trail.');
        }

        $query = AuditLog::query()->latest('created_at');

        if ($request->filled('actor_type')) {
            $query->where('actor_type', $request->string('actor_type')->toString());
        }

        if ($request->filled('module')) {
            $query->where('module', $request->string('module')->toString());
        }

        if ($request->filled('keyword')) {
            $keyword = '%' . $request->string('keyword')->toString() . '%';
            $query->where(function ($innerQuery) use ($keyword): void {
                $innerQuery->where('action', 'like', $keyword)
                    ->orWhere('description', 'like', $keyword);
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->string('from')->toString());
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->string('to')->toString());
        }

        $rows = $query->limit(10000)->get();

        // Audit the export itself (append-only).
        app(AuditLogService::class)->audit([
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
            'action' => 'Audit Trail Export',
            'module' => 'Audit Trail',
            'description' => 'Exported audit trail (' . $rows->count() . ' entries).',
        ], $request);

        $filename = 'audit-trail-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Actor Type', 'Actor ID', 'Action', 'Module', 'Description', 'Recorded']);

            foreach ($rows as $log) {
                fputcsv($out, [
                    $log->actor_type ?? 'System',
                    (string) ($log->actor_id ?? ''),
                    (string) $log->action,
                    (string) ($log->module ?? ''),
                    (string) ($log->description ?? ''),
                    optional($log->created_at)->toDateTimeString() ?? '',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Actor types a Normal Admin is allowed to filter on (Super Admin sees all).
     */
    protected function visibleActorTypes(bool $isSuperAdmin): array
    {
        if ($isSuperAdmin) {
            return ['admin', 'security', 'student', 'system'];
        }

        // Normal admins may filter operational actor types, not privileged
        // 'system' maintenance actors.
        return ['admin', 'security', 'student'];
    }

    /**
     * Modules a Normal Admin may filter on (excludes privileged modules).
     */
    protected function visibleModules(bool $isSuperAdmin): array
    {
        $all = AuditLog::query()
            ->select('module')
            ->whereNotNull('module')
            ->distinct()
            ->pluck('module')
            ->filter(fn ($m) => ! app(AuditLogService::class)->isPrivilegedModule((string) $m))
            ->values();

        return $all->all();
    }
}

