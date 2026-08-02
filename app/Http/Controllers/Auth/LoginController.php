<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Support\MfaService;

class LoginController extends Controller
{
    public function showLogin()
    {
        return view('student.login');
    }

    public function login(Request $request, MfaService $mfaService)
{
        return redirect()->route('student.idp.login');
}

public function logout(Request $request)
{
Auth::guard('student')->logout();
    $request->session()->invalidate();

    $request->session()->regenerateToken();

    return redirect()->route('home');
}
}