<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\Announcement;

class AnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::latest('created_at')->paginate(10);

        return view('security.announcements.index', compact('announcements'));
    }
}
