<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

class SuperAdminController extends Controller
{
    public function dashboard()
    {
        $totalLogs = AuditLog::count();
        $todayLogs = AuditLog::whereDate('created_at', today())->count();
        $maintenanceStatus = app()->isDownForMaintenance();
        $latestLog = AuditLog::latest('created_at')->first();

        return view('admin.super-admin.dashboard', compact(
            'totalLogs',
            'todayLogs',
            'maintenanceStatus',
            'latestLog'
        ));
    }

    public function auditTrail(Request $request)
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

        $logs = $query->paginate(20)->withQueryString();

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

        return view('admin.super-admin.audit-trail', compact('logs', 'actorTypes', 'modules'));
    }

    public function maintenanceConfiguration()
    {
        $maintenanceStatus = app()->isDownForMaintenance();

        $latestMaintenanceLog = AuditLog::query()
            ->where('module', 'Maintenance Configuration')
            ->latest('created_at')
            ->first();

        return view('admin.super-admin.maintenance', compact('maintenanceStatus', 'latestMaintenanceLog'));
    }

    public function updateMaintenanceConfiguration(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:enable,disable'],
        ]);

        $admin = Auth::guard('admin')->user();
        $isDown = app()->isDownForMaintenance();
        $action = $validated['action'];

        if ($action === 'enable') {
            if ($isDown) {
                return back()->with('info', 'Maintenance mode is already enabled.');
            }

            Artisan::call('down');

            AuditLog::create([
                'actor_type' => 'admin',
                'actor_id' => $admin?->getKey(),
                'action' => 'Enabled Maintenance Mode',
                'module' => 'Maintenance Configuration',
                'description' => 'Maintenance mode enabled by IT Administrator ' . ($admin?->email ?? 'unknown') . '.',
                'created_at' => now(),
            ]);

            return back()->with('success', 'Maintenance mode has been enabled.');
        }

        if (!$isDown) {
            return back()->with('info', 'Maintenance mode is already disabled.');
        }

        Artisan::call('up');

        AuditLog::create([
            'actor_type' => 'admin',
            'actor_id' => $admin?->getKey(),
            'action' => 'Disabled Maintenance Mode',
            'module' => 'Maintenance Configuration',
            'description' => 'Maintenance mode disabled by IT Administrator ' . ($admin?->email ?? 'unknown') . '.',
            'created_at' => now(),
        ]);

        return back()->with('success', 'Maintenance mode has been disabled.');
    }
}
