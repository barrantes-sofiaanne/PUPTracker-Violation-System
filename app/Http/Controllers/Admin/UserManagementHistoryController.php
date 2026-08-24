<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Security;
use App\Models\User;
use App\Models\UserManagementHistory;

class UserManagementHistoryController extends Controller
{
    public function index()
    {
        $history = UserManagementHistory::latest('created_at')->paginate(15)->withQueryString();

        return view('admin.user-management-history.index', compact('history'));
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
