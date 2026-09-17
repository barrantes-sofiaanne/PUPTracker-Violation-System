<?php

namespace App\Http\Middleware;

use App\Support\Access\SecurityAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict the security-operational surfaces to Security Personnel ONLY
 * (strict module isolation).
 *
 * WHY THIS IS NEEDED
 * ------------------
 * The security route groups also accept the `admin` guard. Without this gate
 * that would widen access to EVERY administrator — an OSS Staff member, a plain
 * Administrator and an IT Administrator could all reach the security endpoints.
 * This middleware narrows it back down to the accounts the access rule permits.
 *
 * Accepting the `admin` guard only so the account can be IDENTIFIED and then
 * explicitly refused is deliberate: an OSS Staff member is authenticated, and
 * bouncing them to a login page would be misleading. They get an honest 403 and
 * keep using their own `/oss-staff/...` module, which has its own `oss.staff`
 * gate and its own ADMIN audit identity.
 *
 * It runs AFTER the auth middleware, so an unauthenticated visitor is still
 * handed to the auth guard first (and redirected to a login page).
 *
 * The denial is deliberately a 403 rather than a redirect: this is an
 * AUTHORIZATION failure for an already-authenticated account, and silently
 * bouncing them somewhere could mask a misconfiguration.
 */
class EnsureSecurityOperationalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        // Whichever guard authenticated this request holds the account. The
        // admin branch exists so an admin-guard account is refused explicitly
        // rather than treated as a guest.
        $user = $request->user('security')
            ?? $request->user('admin')
            ?? $request->user();

        if (! SecurityAccess::allowed($user)) {
            abort(403, 'You are not authorized to access the security operations area.');
        }

        return $next($request);
    }
}
