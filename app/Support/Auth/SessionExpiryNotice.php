<?php

namespace App\Support\Auth;

use Illuminate\Http\Request;

/**
 * One-time "your session expired" notice shown on a login page.
 *
 * THE BUG THIS FIXES
 * ------------------
 * The inactivity middleware wrote the reason with
 * `session()->put('auth.expired_reason', ...)` and the login view READ it with
 * `session('auth.expired_reason')`. Nothing ever cleared it, so the value stayed
 * in the session for the whole session lifetime (~120 minutes) and the login
 * page re-displayed "your session has expired" on every subsequent visit — even
 * for someone who had merely opened the login page again, and even though no
 * expiry had ever been detected for them.
 *
 * THE FIX
 * -------
 * `record()` stores the reason together with a one-shot flag. `pull()` returns
 * the reason ONCE and immediately consumes it, so:
 *   - the message is displayed at most once,
 *   - the second and later renders of that page show nothing, and
 *   - any legacy `auth.expired_reason` value left behind by the old code is also
 *     consumed (and therefore cannot linger).
 *
 * WHY NOT `session()->flash()`
 * ----------------------------
 * A flash value survives exactly one SUBSEQUENT request. The expiry path
 * redirects to a landing route which then redirects again to the login page —
 * a two-hop chain — so a flash would be swallowed by the intermediate request
 * and the user would never see the message. Storing the value and pulling it on
 * display keeps the message visible on the login page while still guaranteeing
 * it can only ever be shown once.
 *
 * Only the inactivity middleware calls `record()`, and only after a genuinely
 * AUTHENTICATED user's session has expired — so an unauthenticated visitor
 * arriving at a login page for the first time has nothing to display.
 */
class SessionExpiryNotice
{
    /**
     * Session key. Kept as the historical name so the middleware, the views and
     * the existing tests continue to line up.
     */
    public const KEY = 'auth.expired_reason';

    /**
     * Record the notice on the (fresh) session so the login page can show it.
     *
     * Call this AFTER invalidating the session: `invalidate()` flushes all data,
     * so writing beforehand would be discarded.
     */
    public static function record(Request $request, string $reason): void
    {
        $request->session()->put(self::KEY, $reason);
    }

    /**
     * The standard message for an inactivity expiry.
     */
    public static function inactivityReason(int $minutes): string
    {
        return 'Your session has expired due to ' . $minutes
            . ' minutes of inactivity. Please log in again.';
    }

    /**
     * Read the notice and remove it, so it renders exactly once.
     *
     * Safe to call from a view on every render: a missing session, a missing key
     * or a non-string value all yield null.
     */
    public static function pull(Request $request): ?string
    {
        $reason = $request->session()->pull(self::KEY);

        return self::normalise($reason);
    }

    /**
     * Convenience wrapper for views, which have no injected Request.
     */
    public static function pullFromCurrentRequest(): ?string
    {
        $request = request();

        if (! $request instanceof Request || ! $request->hasSession()) {
            return null;
        }

        return self::pull($request);
    }

    /**
     * Drop any pending notice.
     *
     * Called once a user is authenticated again: a notice describes a PAST
     * expiry, so carrying it into a new session would let it surface later on a
     * login page the user has no reason to trust.
     */
    public static function clear(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->forget(self::KEY);
        }
    }

    /**
     * Keep only a meaningful string, so a blank value cannot render an empty
     * alert box on the login page.
     */
    private static function normalise(mixed $reason): ?string
    {
        if (! is_string($reason)) {
            return null;
        }

        $reason = trim($reason);

        return $reason === '' ? null : $reason;
    }
}
