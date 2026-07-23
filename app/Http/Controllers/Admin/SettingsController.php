<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    public function index()
    {
        $admin = Auth::guard('admin')->user();

        return view('admin.setting', compact('admin'));
    }
}
