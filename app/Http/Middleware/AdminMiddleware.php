<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin-module authorisation.
 *
 * OSS Staff accounts authenticate on the `admin` guard (they ARE `admins` rows),
 * so `auth:admin` alone would let an OSS Staff member open any `/admin/...`
 * page. This middleware closes that hole: the Admin module belongs to
 * Administrators, while OSS Staff own `/oss-staff/...` (gated by `oss.staff`).
 *
 * It FAILS OPEN for every other admin-guard account (plain Administrator, IT
 * Administrator, or an account whose role cannot be resolved), so existing
 * Administrator behaviour is untouched.
 */
class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('admin');

        if ($user instanceof Admin && $user->isOssStaff()) {
            abort(403, 'You are not authorized to access the Admin module.');
        }

        return $next($request);
    }
}
