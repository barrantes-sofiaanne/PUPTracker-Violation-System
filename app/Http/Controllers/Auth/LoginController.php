<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class LoginController extends Controller
{
    public function showLogin()
    {
        return view('student.login');
    }

    public function login(Request $request)
    {
        return redirect()->route('student.idp.login');
    }

    public function logout(Request $request)
    {
        // Capture the IDP tokens BEFORE clearing the session so we can attempt
        // a server-side IDP logout (token revocation). Verified contract:
        //   POST /auth/logout  requires  Authorization: Bearer <access_token>
        //   (401 without a valid Bearer token; body carries client_id).
        $idpAccessToken = (string) $request->session()->get('student.idp.access_token', '');
        $idpClientId = (string) config('services.idp.client_id', '');

        Auth::guard('student')->logout();

        // Best-effort server-side token revocation: never expose or log tokens,
        // and never break local logout if the IDP endpoint is unavailable.
        if ($idpAccessToken !== '') {
            $idpBaseUrl = rtrim((string) config('services.idp.base_url'), '/');
            $idpLogoutPath = (string) config('services.idp.logout_path', '/auth/logout');
            $idpTimeout = (int) config('services.idp.timeout', 15);

            try {
                if ($idpBaseUrl !== '' && $idpLogoutPath !== '') {
                    \Illuminate\Support\Facades\Http::baseUrl($idpBaseUrl)
                        ->acceptJson()
                        ->asJson()
                        ->withToken($idpAccessToken)
                        ->timeout($idpTimeout)
                        ->post($idpLogoutPath, [
                            'client_id' => $idpClientId,
                        ]);
                }
            } catch (\Throwable $e) {
                // Best-effort only — local logout must still succeed.
                Log::warning('IDP token revocation failed', ['error' => $e->getMessage()]);
            }
        }

        // Clear any student IDP flow/session keys.
        $request->session()->forget([
            'student.idp.state',
            'student.idp.student_number',
            'student.idp.access_token',
            'student.idp.refresh_token',
            'student.idp.pending_subject',
            'student.idp.pending_email',
        ]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Front-channel IDP logout: clear the IDP SSO session cookie in the
        // browser. The IDP redirects back to the App Client's registered Logout
        // URL (https://puptvs.com/student/login), so the student lands on the
        // login page. If the IDP logout URL is not configured or unreachable,
        // fall back to the local student login page so logout always succeeds.
        $idpLogoutUrl = rtrim((string) config('services.idp.logout_url'), '/');

        if ($idpLogoutUrl === '') {
            // Derive from base_url by stripping the /api/v1 base path (the
            // front-channel logout lives at the root, e.g. https://host/logout).
            $idpLogoutUrl = rtrim(preg_replace('#/api/v1/?$#', '', (string) config('services.idp.base_url')), '/');
        }

        if ($idpLogoutUrl !== '') {
            try {
                return redirect()->away($idpLogoutUrl);
            } catch (\Throwable $e) {
                Log::warning('IDP front-channel logout redirect failed', ['error' => $e->getMessage()]);
            }
        }

        return redirect()->route('student.login');
    }
}