<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\Announcement;

class AnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::with('attachments')->latest('created_at')->get();

        return view('security.announcements.index', compact('announcements'));
    }
}
