<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforceInactivityTimeout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionActivityController extends Controller
{
    /**
     * Refresh the authenticated user's inactivity window.
     *
     * This endpoint is called only when the user performs an
     * intentional interaction with the system or clicks
     * "I'm still here".
     */
    public function touch(Request $request): JsonResponse
    {
        if (! EnforceInactivityTimeout::enabled()) {
            return response()->json([
                'ok' => true,
                'enabled' => false,
            ]);
        }

        foreach (EnforceInactivityTimeout::GUARDS as $guard) {
            $user = Auth::guard($guard)->user();

            if ($user === null) {
                continue;
            }

            $last = $request->session()->get(
                EnforceInactivityTimeout::SESSION_KEY
            );

            /*
             * Check the server-side timeout BEFORE resetting it.
             *
             * This prevents a user from extending an already-expired
             * session simply by sending an activity request.
             */
            if (is_numeric($last)) {
                $idleSeconds =
                    now()->timestamp - (int) $last;

                if (
                    $idleSeconds >=
                    EnforceInactivityTimeout::timeoutMinutes() * 60
                ) {
                    return response()->json([
                        'ok' => false,
                        'expired' => true,
                    ], 440);
                }
            }

            /*
             * Session is still valid.
             * Start a fresh inactivity window.
             */
            EnforceInactivityTimeout::startFreshActivityWindow(
                $request
            );

            return response()->json([
                'ok' => true,
                'expired' => false,
                'timeout_minutes' =>
                    EnforceInactivityTimeout::timeoutMinutes(),
            ]);
        }

        return response()->json([
            'ok' => false,
            'expired' => true,
        ], 401);
    }
}