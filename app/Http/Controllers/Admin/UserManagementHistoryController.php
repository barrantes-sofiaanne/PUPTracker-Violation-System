<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserManagementHistory;

class UserManagementHistoryController extends Controller
{
    public function index()
    {
        $history = UserManagementHistory::latest('created_at')->paginate(15);

        return view('admin.user-management-history.index', compact('history'));
    }
}
