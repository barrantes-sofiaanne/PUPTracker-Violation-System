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
        // admin_info_tbl.role_id -> roles_tbl and status_id -> status_tbl.
        $roleName = $admin->roleName() ?: \App\Models\Admin::ROLE_OSS_STAFF;

        $statusId = $admin->adminInfo?->status_id;

        $accountStatus = $statusId
            ? \App\Models\Status::query()->whereKey($statusId)->value('status_name')
            : null;

        return view('oss-staff.profile', compact('admin', 'roleName', 'accountStatus'));
    }
}
