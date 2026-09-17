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

        return view('oss-staff.profile', compact('admin'));
    }
}
