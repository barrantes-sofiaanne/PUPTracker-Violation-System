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
    // Get the IDP access token before destroying the local session.
    $idpAccessToken = (string) $request->session()->get(
        'student.idp.access_token',
        ''
    );

    $idpClientId = (string) config(
        'services.idp.client_id',
        ''
    );

    /*
     * 1. Logout from the IDP server-side.
     */
    if ($idpAccessToken !== '' && $idpClientId !== '') {

        $idpBaseUrl = rtrim(
            (string) config('services.idp.base_url'),
            '/'
        );

        $idpLogoutPath = (string) config(
            'services.idp.logout_path',
            '/auth/logout'
        );

        $idpTimeout = (int) config(
            'services.idp.timeout',
            15
        );

        try {

            if ($idpBaseUrl !== '' && $idpLogoutPath !== '') {

                $response = \Illuminate\Support\Facades\Http::baseUrl(
                    $idpBaseUrl
                )
                ->acceptJson()
                ->asJson()
                ->withToken($idpAccessToken)
                ->timeout($idpTimeout)
                ->post(
                    $idpLogoutPath . '?client_id=' . urlencode($idpClientId),
                    [
                        'client_id' => $idpClientId,
                    ]
                );

                Log::info('IDP logout request completed', [
                    'status' => $response->status(),
                ]);
            }

        } catch (\Throwable $e) {

            Log::warning('IDP logout failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /*
     * 2. Logout from PUPTracker.
     */
    Auth::guard('student')->logout();

    /*
     * 3. Remove IDP-related session values.
     */
    $request->session()->forget([
        'student.idp.state',
        'student.idp.student_number',
        'student.idp.access_token',
        'student.idp.refresh_token',
        'student.idp.pending_subject',
        'student.idp.pending_email',
    ]);

    /*
     * 4. Completely invalidate the local session.
     */
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    /*
     * 5. Return to the PUPTracker student login page.
     *
     * Do NOT redirect to the IDP root.
     * We still need the official IDP browser/SSO logout
     * endpoint before implementing that portion.
     */
    return redirect()->route('student.login');
}
}