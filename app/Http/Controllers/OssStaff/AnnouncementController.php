<?php

namespace App\Http\Controllers\OssStaff;

use App\Http\Controllers\Controller;
use App\Models\Announcement;

/**
 * OSS Staff announcements.
 *
 * The announcement feed itself is the SAME data for every module (published
 * announcements + their attachments), served through the same shared UI
 * partials. The OSS Staff module only contributes its own branding and
 * navigation, so the controller supplies `$announcements` exactly like the
 * Security one does.
 */
class AnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::with('attachments')->latest('created_at')->get();

        return view('oss-staff.announcements.index', compact('announcements'));
    }
}
