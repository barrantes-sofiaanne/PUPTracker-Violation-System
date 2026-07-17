<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class LoginController extends Controller
{
    public function showLogin()
    {
        return view('student.login');
    }

   public function login(Request $request)
{
    $validated = $request->validate([
        'student_number' => 'required',
        'password' => 'required',
    ]);
$user = User::where('student_number', $request->student_number)->first();

    if (!$user || !Hash::check($request->password, $user->password_hash)) {
    return back()
        ->withErrors([
            'login' => 'Incorrect student number or password.'
        ])
        ->withInput();
}

if ($user->status_id != 1) {
    return back()
        ->withErrors([
            'login' => 'Your account has been deactivated. Please contact an administrator.'
        ])
        ->withInput();
}

Auth::login($user);

$request->session()->regenerate();

return redirect()->route('student.dashboard');
}

public function logout(Request $request)
{
    Auth::logout();

    $request->session()->invalidate();

    $request->session()->regenerateToken();

    return redirect()->route('home');
}
}