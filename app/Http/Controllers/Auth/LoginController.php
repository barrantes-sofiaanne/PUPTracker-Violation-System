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

if ($mfaService->hasValidTrustedDevice($request, 'student', (string) $user->student_number)) {
    Auth::guard('student')->login($user);
    $request->session()->regenerate();

    return redirect()->route('student.dashboard')
        ->with('show_login_announcement_modal', true);
}

if (empty($user->email)) {
    return back()
        ->withErrors([
            'login' => 'Your account has no email address configured. Please contact an administrator.'
        ])
        ->withInput();
}

try {
    $mfaService->beginChallenge(
        $request,
        'student',
        (string) $user->student_number,
        (string) $user->email,
        $user->mfa_totp_secret,
        (bool) $user->mfa_totp_enabled
    );
} catch (\Throwable $exception) {
    Log::error('Student MFA challenge initialization failed.', [
        'student_number' => $user->student_number,
        'error' => $exception->getMessage(),
    ]);

    return back()
        ->withErrors([
            'login' => 'Unable to send verification code right now. Please try again later.'
        ])
        ->withInput();
}

return redirect()->route('mfa.verify.show')
    ->with('success', 'A verification code has been sent to your email.');
}

public function logout(Request $request)
{
Auth::guard('student')->logout();
    $request->session()->invalidate();

    $request->session()->regenerateToken();

    return redirect()->route('home');
}
}