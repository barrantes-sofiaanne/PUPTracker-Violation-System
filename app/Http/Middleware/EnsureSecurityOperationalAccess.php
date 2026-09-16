<?php

namespace App\Http\Middleware;

use App\Support\Access\SecurityAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PART 1 / PART 2 — restrict the security-operational surfaces to the accounts
 * that are ALLOWED to use them.
 *
 * WHY THIS IS NEEDED
 * ------------------
 * The security route groups now also accept `auth:admin` so the Admin Assistant
 * (an admin account) can reach them. Without this gate that would widen access
 * to EVERY administrator — a plain Administrator or an IT Administrator could
 * record violations through the security endpoints. This middleware narrows it
 * back down to Security Personnel + Admin Assistant only.
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
        // Whichever guard authenticated this request holds the account.
        $user = $request->user('security')
            ?? $request->user('admin')
            ?? $request->user();

        if (! SecurityAccess::allowed($user)) {
            abort(403, 'You are not authorized to access the security operations area.');
        }

        return $next($request);
    }
}
