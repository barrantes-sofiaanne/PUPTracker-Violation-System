<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class AdminProfileController extends Controller
{
    public function index()
    {
        /** @var \App\Models\Admin $admin */
        $admin = Auth::guard('admin')->user();

        $admin->load('adminInfo');

        return view('admin.profile', compact('admin'));
    }
}
