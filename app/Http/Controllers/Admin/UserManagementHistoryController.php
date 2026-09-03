<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Security;
use App\Models\User;
use App\Models\UserManagementHistory;
use Illuminate\Http\Request;

class UserManagementHistoryController extends Controller
{
    public function index(Request $request)
    {
        /** @var \App\Models\Admin $admin */
        $admin = auth('admin')->user();
        $isSuperAdmin = $admin->isItAdministrator();

        $query = UserManagementHistory::latest('created_at');

        // Normal Admin: only student/security operational history is visible.
        // Admin-account lifecycle + role/permission changes are privileged and
        // are excluded server-side (not just hidden in the UI).
        if (! $isSuperAdmin) {
            $query->where(function ($q): void {
                $q->where('action', 'like', '%Student Account%')
                    ->orWhere('action', 'like', '%Security Account%');
            });
        }

        // Filters.
        if ($request->filled('action')) {
            $query->where('action', 'like', '%' . $request->string('action')->toString() . '%');
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->string('user_id')->toString());
        }

        if ($request->filled('performed_by')) {
            $query->where('performed_by', (int) $request->string('performed_by')->toString());
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->string('from')->toString());
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->string('to')->toString());
        }

        $history = $query->paginate(15)->withQueryString();

        return view('admin.user-management-history.index', compact('history', 'isSuperAdmin'));
    }

    /**
     * Resolve a user-facing label for a stored user_id based on its numeric
     * range (IDs are allocated per table), with a fallback lookup.
     */
    public static function resolveUserIdLabel(mixed $userId): string
    {
        $userId = (int) $userId;

        if ($userId <= 0) {
            return 'System';
        }

        // Admins and security accounts use small sequential IDs; students are
        // much larger (bulk imports). Look up by explicit range first.
        $admin = Admin::query()->find($userId);
        if ($admin) {
            return $admin->email;
        }

        $security = Security::query()->find($userId);
        if ($security) {
            return $security->email;
        }

        $student = User::query()->find($userId);
        if ($student) {
            return $student->student_number ?: $student->email;
        }

        return 'User #' . $userId;
    }

    /**
     * Resolve a user-facing label for a stored performed_by (admin id).
     */
    public static function resolvePerformedByLabel(mixed $performedBy): string
    {
        $performedBy = (int) $performedBy;

        if ($performedBy <= 0) {
            return 'System';
        }

        $admin = Admin::query()->with('adminInfo')->find($performedBy);

        if ($admin) {
            $name = trim(($admin->adminInfo->firstname ?? '') . ' ' . ($admin->adminInfo->lastname ?? ''));

            return $name ?: $admin->email;
        }

        return 'Admin #' . $performedBy;
    }
}
