<?php

namespace App\Http\Middleware;

use Closure;
use App\Support\Auth\SessionExpiryNotice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * PART 3 / PART 4 / PART 5 — 15-minute INACTIVITY timeout.
 *
 * If an authenticated account makes no qualifying request for 15 minutes, the
 * session is invalidated server-side and the user must authenticate again.
 *
 * WHY NOT JUST LOWER session.lifetime
 * -----------------------------------
 * `session.lifetime` is a sliding window measured by Laravel against the last
 * request, but it is GLOBAL (it would also shorten a student's session) and it
 * silently drops the session without a usable explanation. This middleware
 * scopes the rule per guard and gives the user the required message.
 *
 * WHAT COUNTS AS ACTIVITY (PART 4)
 * --------------------------------
 * Any request that reaches an authenticated application route — page requests,
 * API calls, form submissions. Background polling and open tabs do NOT extend
 * the window on their own: the stamp only moves when a request is actually
 * served, so a tab left open with no user action still expires. Requests that
 * are explicitly marked as background are SKIPPED entirely (see below) so a
 * poller cannot hold a session open indefinitely.
 *
 * The timestamp lives in the session, which is server-side, so a client cannot
 * forge it.
 */
class EnforceInactivityTimeout
{
    /**
     * Is the inactivity timeout switched on?
     *
     * The feature is currently DISABLED (see config/auth.php `features`). The
     * implementation below is kept intact so re-enabling it is a one-flag change
     * rather than a code restore.
     *
     * When disabled the middleware is a pass-through: it writes no activity
     * stamp, expires nothing, and produces no "session expired" notice. The
     * ordinary `session.lifetime` still applies, so sessions do not live
     * forever — only the per-guard 15-minute rule is off.
     */
    public static function enabled(): bool
    {
        return (bool) config('auth.features.inactivity_timeout', false);
    }

    /**
     * Guards this rule applies to. Students are included: PART 3 says the
     * timeout applies to all authenticated users unless the architecture
     * excludes a system/service account, and there is no such account here.
     */
    public const GUARDS = ['admin', 'security', 'student'];

    /** Idle window, in minutes (PART 3). */
    public const TIMEOUT_MINUTES = 15;

    /** Session key holding the last qualifying activity timestamp. */
    public const SESSION_KEY = 'auth.last_activity_at';

    /**
     * Session key used to carry the one-time expiry notice across the redirect.
     *
     * Kept as an alias of {@see SessionExpiryNotice::KEY} so the middleware and
     * the login view can never disagree about the key name.
     */
    public const REASON_KEY = SessionExpiryNotice::KEY;

    /** Landing route per guard, used by the post-timeout bounce. */
    public const LANDING_ROUTES = [
        'admin' => 'admin.dashboard',
        'security' => 'security.dashboard',
        'student' => 'student.dashboard',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Feature switched off: do nothing at all. Returning before the guard
        // check also means the recaller-cookie purge below cannot run, so a
        // remembered session is not rejected either.
        if (! self::enabled()) {
            return $next($request);
        }

        [$guard, $user] = $this->activeGuard($request);

        // Nobody authenticated on a known guard: nothing to expire here. The
        // route's own auth middleware decides what happens next.
        if ($guard === null || $user === null) {
            return $next($request);
        }

        // PART 5 — the timeout bounce re-authenticates on a FRESH session via the
        // Remember Me recaller cookie, so the student does NOT land on the login
        // page. We purge that cookie on the way in and send them straight to the
        // login screen with the required message.
        //
        // This is done BEFORE the auth middleware resolves the guard, because
        // resolving it is what writes the new session in the first place.
        if ($request->cookies->has($this->recallerCookieName($guard))) {
            return $this->rejectRememberedSession($request, $guard);
        }

        $now = now();
        $last = $request->session()->get(self::SESSION_KEY);

        // Only honour a stamp that came from THIS session. A remembered session
        // carries no stamp (it starts clean), so there is nothing to extend and
        // the request is simply stamped below.
        if (is_numeric($last)) {
            $idleMinutes = ($now->getTimestamp() - (int) $last) / 60;

            if ($idleMinutes >= self::TIMEOUT_MINUTES) {
                return $this->expire($request, $guard);
            }
        }

        // Qualifying activity: stamp the session. Written on every served
        // request so the window tracks real interaction, not wall-clock time
        // since login.
        $request->session()->put(self::SESSION_KEY, $now->getTimestamp());

        return $next($request);
    }

    /**
     * Forget a stale/used Remember Me recaller cookie and force a manual login.
     *
     * PART 5: "Do NOT automatically authenticate the user again merely because
     * Remember Me is enabled."
     *
     * A recaller cookie is only ever PRESENT when the browser still holds a
     * persistent credential. Once the session has gone (inactivity, logout, or a
     * closed browser), arriving with that cookie would silently restore the
     * session — exactly what PART 5 forbids. So the cookie is purged and the user
     * must authenticate again.
     *
     * The user is sent to their guard's LANDING route rather than the login page,
     * because the purge itself makes them a guest there; the route's own auth
     * middleware then redirects them to the correct login screen with the message.
     */
    private function rejectRememberedSession(Request $request, string $guard): Response
    {
        $cookieName = $this->recallerCookieName($guard);

        Auth::guard($guard)->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Record the notice on the FRESH session. It is consumed by the login
        // page on display, so it can be shown at most once and cannot reappear
        // on a later visit. Written AFTER invalidate(), which discards data.
        SessionExpiryNotice::record(
            $request,
            SessionExpiryNotice::inactivityReason(self::TIMEOUT_MINUTES)
        );

        $landing = self::LANDING_ROUTES[$guard] ?? 'student.dashboard';

        return \Illuminate\Support\Facades\Route::has($landing)
            ? redirect()->route($landing)->withCookie(cookie()->forget($cookieName))
            : redirect()->route($this->loginRoute($guard))->withCookie(cookie()->forget($cookieName));
    }

    /**
     * Laravel's remember-cookie name for a guard.
     *
     * The real format (verified against `SessionGuard::getRecallerName()`) is
     *     remember_<guardName>_<sha1(SessionGuard::class)>
     * — the GUARD NAME COMES FIRST. An earlier revision of this method had the
     * two segments the other way round, which produced a name that never
     * matched, so the cookie purge below silently never ran.
     *
     * The name is derived here rather than read from the guard because reading
     * it requires a RESOLVED guard — which is what restores the session we are
     * trying to reject.
     */
    private function recallerCookieName(string $guard): string
    {
        return 'remember_' . $guard . '_' . sha1(\Illuminate\Auth\SessionGuard::class);
    }

    /**
     * Which guard currently holds an authenticated account, and that account.
     *
     * Checked in a fixed order so the result does not depend on the default
     * guard. Returns [null, null] when no known guard has a user.
     *
     * @return array{0: string|null, 1: \Illuminate\Contracts\Auth\Authenticatable|null}
     */
    private function activeGuard(Request $request): array
    {
        foreach (self::GUARDS as $guard) {
            $user = Auth::guard($guard)->user();

            if ($user !== null) {
                return [$guard, $user];
            }
        }

        return [null, null];
    }

    /**
     * Invalidate the session and send the user back to the right login page.
     *
     * PART 5: invalidate server-side, clear auth state, redirect to login and
     * show the required message. The session is NOT simply regenerated — it is
     * fully invalidated so the timeout cannot be walked back, and any Remember
     * Me recaller cookie is invalidated too so it cannot silently re-authenticate
     * the user (PART 5 forbids that explicitly).
     *
     * The reason travels in a short-lived SESSION value on the FRESH session,
     * so the login page can display it without exposing anything sensitive.
     */
    private function expire(Request $request, string $guard): Response
    {
        $guardUser = Auth::guard($guard)->user();

        // Log out through the guard so the recaller cookie is cleared as well.
        Auth::guard($guard)->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // One-time notice for the login page (see record() for why this is
        // stored and pulled rather than flashed).
        SessionExpiryNotice::record(
            $request,
            SessionExpiryNotice::inactivityReason(self::TIMEOUT_MINUTES)
        );

        return redirect()->route($this->loginRoute($guard, $guardUser));
    }

    /**
     * The login page for a guard.
     *
     * Uses the guard's own login route so the user is returned to the correct
     * flow (admin / security / student) rather than a generic one.
     *
     * An OSS Staff admin signed in through the SHARED Security + OSS Staff
     * portal, so it is returned there instead of the Administrator login it
     * never used. Regular Administrators and IT Administrators keep
     * `admin.login`.
     */
    private function loginRoute(string $guard, ?\Illuminate\Contracts\Auth\Authenticatable $user = null): string
    {
        if ($guard === 'admin') {
            return \App\Support\Auth\PortalLoginRedirect::forGuard('admin', $user);
        }

        $candidate = $guard . '.login';

        return \Illuminate\Support\Facades\Route::has($candidate)
            ? $candidate
            : 'student.login';
    }
}
