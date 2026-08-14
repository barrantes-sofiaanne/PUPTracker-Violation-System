<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\MaintenanceRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MaintenanceRecordController extends Controller
{
    /**
     * Maintenance history page (Super Admin only).
     */
    public function index(Request $request): View
    {
        $query = MaintenanceRecord::with('performedBy')
            ->latest('maintenance_date');

        if ($request->filled('maintenance_type')) {
            $query->where('maintenance_type', $request->string('maintenance_type')->toString());
        }

        if ($request->filled('overall_status')) {
            $query->where('overall_status', $request->string('overall_status')->toString());
        }

        $records = $query->paginate(15)->withQueryString();

        return view('admin.super-admin.maintenance-records.index', compact('records'));
    }

    /**
     * Show the create form.
     */
    public function create(): View
    {
        return view('admin.super-admin.maintenance-records.create');
    }

    /**
     * Store a new maintenance record + audit log.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateRecord($request);

        $admin = Auth::guard('admin')->user();
        $adminId = $admin instanceof Admin ? $admin->id : null;
        $adminEmail = $admin instanceof Admin ? $admin->email : 'unknown';

        $record = MaintenanceRecord::create($data + [
            'performed_by_admin_id' => $adminId,
        ]);

        AuditLog::create([
            'actor_type' => 'admin',
            'actor_id' => $adminId,
            'action' => 'Create',
            'module' => 'Maintenance Records',
            'description' => 'Maintenance record #' . $record->maintenance_id . ' created (' . $record->maintenance_type . ' on ' . $record->maintenance_date->toDateString() . ') by ' . $adminEmail . '.',
            'created_at' => now(),
        ]);

        return redirect()
            ->route('admin.super-admin.maintenance-records.show', $record->maintenance_id)
            ->with('success', 'Maintenance record created successfully.');
    }

    /**
     * Show a single maintenance record.
     */
    public function show(int $maintenanceId): View
    {
        $record = MaintenanceRecord::with('performedBy')
            ->findOrFail($maintenanceId);

        return view('admin.super-admin.maintenance-records.show', compact('record'));
    }

    /**
     * Show the edit form.
     */
    public function edit(int $maintenanceId): View
    {
        $record = MaintenanceRecord::findOrFail($maintenanceId);

        return view('admin.super-admin.maintenance-records.create', compact('record'));
    }

    /**
     * Update a maintenance record + audit log.
     */
    public function update(Request $request, int $maintenanceId): RedirectResponse
    {
        $record = MaintenanceRecord::findOrFail($maintenanceId);

        $data = $this->validateRecord($request);

        $admin = Auth::guard('admin')->user();
        $adminId = $admin instanceof Admin ? $admin->id : null;
        $adminEmail = $admin instanceof Admin ? $admin->email : 'unknown';

        $record->update($data);

        AuditLog::create([
            'actor_type' => 'admin',
            'actor_id' => $adminId,
            'action' => 'Update',
            'module' => 'Maintenance Records',
            'description' => 'Maintenance record #' . $record->maintenance_id . ' updated (' . $record->maintenance_type . ') by ' . $adminEmail . '.',
            'created_at' => now(),
        ]);

        return redirect()
            ->route('admin.super-admin.maintenance-records.show', $record->maintenance_id)
            ->with('success', 'Maintenance record updated successfully.');
    }

    /**
     * Delete a maintenance record + audit log.
     */
    public function destroy(Request $request, int $maintenanceId): RedirectResponse
    {
        $record = MaintenanceRecord::findOrFail($maintenanceId);

        $admin = Auth::guard('admin')->user();
        $adminId = $admin instanceof Admin ? $admin->id : null;
        $adminEmail = $admin instanceof Admin ? $admin->email : 'unknown';

        AuditLog::create([
            'actor_type' => 'admin',
            'actor_id' => $adminId,
            'action' => 'Delete',
            'module' => 'Maintenance Records',
            'description' => 'Maintenance record #' . $record->maintenance_id . ' deleted (' . $record->maintenance_type . ') by ' . $adminEmail . '.',
            'created_at' => now(),
        ]);

        $record->delete();

        return redirect()
            ->route('admin.super-admin.maintenance-records.index')
            ->with('success', 'Maintenance record deleted.');
    }

    /**
     * Validate the maintenance record form.
     */
    protected function validateRecord(Request $request): array
    {
        $data = $request->validate([
            'maintenance_date' => ['required', 'date'],
            'maintenance_type' => ['required', 'in:' . implode(',', MaintenanceRecord::TYPES)],
            'description' => ['nullable', 'string', 'max:2000'],
            'dependency_status' => ['nullable', 'string', 'max:500'],
            'php_runtime_status' => ['nullable', 'string', 'max:500'],
            'server_status' => ['nullable', 'string', 'max:500'],
            'testing_status' => ['nullable', 'string', 'max:500'],
            'overall_status' => ['required', 'in:' . implode(',', MaintenanceRecord::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'updates_identified' => ['nullable', 'string', 'max:2000'],
            'updates_applied' => ['nullable', 'string', 'max:2000'],
            'tests_performed' => ['nullable', 'string', 'max:2000'],
            'test_results' => ['nullable', 'string', 'max:2000'],
            'problems_found' => ['nullable', 'string', 'max:2000'],
            'corrective_actions' => ['nullable', 'string', 'max:2000'],
            'checklist' => ['nullable', 'array'],
            'checklist.*' => ['boolean'],
        ]);

        // Normalize the checklist into the documented 10 items (booleans).
        $checklist = [];
        foreach (array_keys(MaintenanceRecord::CHECKLIST_ITEMS) as $key) {
            $checklist[$key] = isset($data['checklist'][$key]) && (bool) $data['checklist'][$key];
        }

        $data['checklist_json'] = $checklist;
        unset($data['checklist']);

        return $data;
    }
}
