<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
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

        $logs = $query->paginate(15)->withQueryString();

        $actorTypes = AuditLog::query()
            ->select('actor_type')
            ->whereNotNull('actor_type')
            ->distinct()
            ->orderBy('actor_type')
            ->pluck('actor_type');

        $modules = AuditLog::query()
            ->select('module')
            ->whereNotNull('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module');

        return view('admin.audit-logs.index', compact('logs', 'actorTypes', 'modules'));
    }
}
