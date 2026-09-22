<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforceInactivityTimeout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionActivityController extends Controller
{
    public function touch(Request $request): JsonResponse
    {
        if (!EnforceInactivityTimeout::enabled()) {
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

            if (is_numeric($last)) {
                $idleSeconds = now()->timestamp - (int) $last;

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

            EnforceInactivityTimeout::startFreshActivityWindow($request);

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