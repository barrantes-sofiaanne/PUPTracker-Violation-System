<?php

namespace App\Http\Controllers\OssStaff;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * OSS Staff profile.
 *
 * The authenticated account is an ADMIN account holding the OSS Staff role, so
 * the identity is loaded through the ADMIN guard and the Admin model's
 * `adminInfo` relation. No Security profile table is consulted, because an OSS
 * Staff member is not a Security account.
 */
class OssStaffProfileController extends Controller
{
    public function index()
    {
        /** @var \App\Models\Admin $admin */
        $admin = Auth::guard('admin')->user();

        $admin->load('adminInfo');

        // Role and account status come from this account's own lookup tables:
        // admin_info_tbl.role_id -> roles_tbl (and status_id -> status_tbl, which
        // is no longer displayed now that the Account Status card is gone).
        $roleName = $admin->roleName() ?: \App\Models\Admin::ROLE_OSS_STAFF;

        // POSITION.
        //
        // The previous source was the free-text `admin_info_tbl.Position`
        // column, which for an OSS Staff account still holds the legacy value
        // "Admin" left over from when the account was created. That column is
        // entry data, not the account's assigned role, so the profile showed
        // "Admin" while the Role field correctly showed "OSS Staff".
        //
        // The assigned role IS the authoritative module label (the same value
        // the Role field displays), so an OSS Staff profile derives Position
        // from it. The stored Position is only used when no OSS Staff role can
        // be resolved, and nothing in the database is rewritten.
        $storedPosition = trim((string) ($admin->adminInfo?->Position ?? ''));

        $position = $admin->isOssStaff()
            ? $roleName
            : ($storedPosition !== '' ? $storedPosition : $roleName);

        // The profile shows three fields only — Full Name, Email and Position —
        // so the status lookup that fed the removed Account Status card is gone.
        return view('oss-staff.profile', compact('admin', 'roleName', 'position'));
    }
}
