<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AdminLoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.admin-login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required',
            'password' => 'required',
        ]);

        $admin = Admin::where('email', $request->email)->first();

        if (!$admin) {
            return back()->withErrors([
                'email' => 'Invalid email or password.',
            ])->withInput();
        }

        if (!Hash::check($request->password, $admin->password)) {
            return back()->withErrors([
                'email' => 'Invalid email or password.',
            ])->withInput();
        }

        Auth::guard('admin')->login($admin);

        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}