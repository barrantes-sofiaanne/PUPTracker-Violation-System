<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Security;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class SecurityLoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.security-login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $security = Security::where('email', $request->email)->first();

        if (!$security || !Hash::check($request->password, $security->password)) {
            return back()
                ->withErrors([
                    'login' => 'Invalid email or password.'
                ])
                ->withInput();
        }

        Auth::guard('security')->login($security);

        $request->session()->regenerate();

        return redirect()->route('security.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('security')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('security.login');
    }
}