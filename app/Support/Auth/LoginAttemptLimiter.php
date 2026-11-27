<?php

namespace App\Support\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Shared failed-login-attempt limiter ("login lockout").
 *
 * WHY THIS CLASS EXISTS
 * ---------------------
 * Every login page (Administrator, Security, OSS Staff and Student) must apply
 * the SAME rule. Keeping the counter, the threshold and the message formatting
 * in one place means the pages cannot drift apart, and any future module only
 * has to call this class instead of re-implementing the logic.
 *
 * GLOBAL, CLIENT-SCOPED COUNTER (a hard requirement)
 * --------------------------------------------------
 * The counter is keyed ONLY on the client, never on the submitted account or the
 * login module:
 *   - changing the email / student number cannot reset the counter,
 *   - changing the capitalization of an email cannot reset it,
 *   - switching between the Admin, Security, OSS Staff and Student login pages
 *     cannot reset it.
 *
 * WHY THE CLIENT IP, NOT THE EMAIL OR THE USER AGENT
 * --------------------------------------------------
 * The IP is the only client attribute an attacker cannot rotate per request.
 * A submitted email is attacker-chosen, so keying on it (as an earlier revision
 * did) let a caller start a fresh budget simply by typing a different address.
 * A User-Agent string or a custom header is equally attacker-controlled, and a
 * browser cookie can be discarded; keying on any of them would re-open the same
 * bypass. Laravel resolves `$request->ip()` through the configured trusted-proxy
 * list, so a spoofable forwarding header is never trusted here.
 *
 * WHY THE CACHE, NOT THE SESSION
 * ------------------------------
 * Refreshing the login page (or dropping the session cookie) must NOT reset the
 * counter. The application cache is server-side and survives both.
 *
 * WHY THE EXPIRY IS STORED EXPLICITLY
 * -----------------------------------
 * Not every cache store can report a remaining TTL (the file and array stores
 * cannot). Storing the expiry timestamp inside the cached payload makes the
 * "try again in N minutes" message correct on every driver, not just Redis.
 *
 * This is deliberately separate from `session.lifetime` and from the 15-minute
 * inactivity timeout: a lockout is a security response to credential guessing,
 * not a session-state concern.
 */
class LoginAttemptLimiter
{
    /** Cache-key prefix, versioned so a change in key shape cannot collide. */
    private const PREFIX = 'login-attempts:v2';

    /**
     * Resolve the configured budget.
     *
     * The counter is global, so there is exactly ONE policy: the `default`
     * entry. A per-module threshold would mean the same client could be blocked
     * on one login page but not another, which is the bypass this class exists
     * to prevent. Per-module entries in config are therefore intentionally not
     * consulted here.
     *
     * @return array{max:int,lockout_seconds:int}
     */
    public static function policy(): array
    {
        $configured = (array) config('auth.login_attempts.default', []);

        $max = (int) ($configured['max'] ?? 3);
        $lockout = (int) ($configured['lockout_seconds'] ?? 900);

        // Fail closed on nonsense configuration: a threshold below 1 would block
        // every login immediately, and a zero lockout would never lock at all.
        return [
            'max' => max(1, $max),
            'lockout_seconds' => max(1, $lockout),
        ];
    }

    /**
     * The normalized client identifier the counter is keyed on.
     *
     * The IP is resolved by Laravel through the configured trusted-proxy list
     * (`config('app.trusted_proxies')`), so this class never reads a forwarded
     * header itself:
     *   - with the default "trust the calling IP" mode the value is the
     *     RIGHT-MOST X-Forwarded-For hop (the address the edge proxy observed),
     *     so a client cannot change it by prefixing forged addresses;
     *   - with an explicit proxy list, a request from any other peer ignores the
     *     header entirely and resolves to the socket address.
     */
    public static function clientIdentifier(Request $request): string
    {
        $ip = trim((string) $request->ip());

        if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            return $ip;
        }

        // Defensive: an absent or malformed value must not collapse every client
        // into one shared bucket, and must never be keyed on unvalidated input.
        $peer = trim((string) $request->server('REMOTE_ADDR', ''));

        return filter_var($peer, FILTER_VALIDATE_IP) !== false ? $peer : 'unknown';
    }

    /**
     * Cache key for one client.
     */
    public static function key(Request $request): string
    {
        return self::PREFIX . ':' . sha1(self::clientIdentifier($request));
    }

    /**
     * The stored entry, or null when nothing has been recorded.
     *
     * Reading never creates an entry, so merely visiting the login page cannot
     * start a counter.
     *
     * @return array{attempts:int,expires_at:int}|null
     */
    private static function entry(Request $request): ?array
    {
        $value = Cache::get(self::key($request));

        if (! is_array($value) || ! isset($value['attempts'])) {
            return null;
        }

        // Defensively treat an expired payload as absent, so a store that keeps
        // a value past its TTL cannot lock anyone out indefinitely.
        if (isset($value['expires_at']) && (int) $value['expires_at'] <= now()->getTimestamp()) {
            return null;
        }

        return [
            'attempts' => (int) $value['attempts'],
            'expires_at' => (int) ($value['expires_at'] ?? 0),
        ];
    }

    /**
     * Number of failed attempts RECORDED so far by this client.
     */
    public static function attempts(Request $request): int
    {
        $entry = self::entry($request);

        return $entry === null ? 0 : $entry['attempts'];
    }

    /**
     * Is this client currently blocked?
     */
    public static function isLocked(Request $request): bool
    {
        return self::attempts($request) >= self::policy()['max'];
    }

    /**
     * Remaining attempts before the block engages (never negative).
     */
    public static function remaining(Request $request): int
    {
        $policy = self::policy();

        return max(0, $policy['max'] - self::attempts($request));
    }

    /**
     * Record one failed attempt and (re)arm the expiry window.
     *
     * Each recording rewrites the window, so the lockout is measured from the
     * most recent failure. Continuing to guess therefore cannot shorten the
     * block, and a user who stops trying is released after the configured wait.
     */
    public static function recordFailure(Request $request): int
    {
        $policy = self::policy();
        $attempts = self::attempts($request) + 1;

        Cache::put(
            self::key($request),
            [
                'attempts' => $attempts,
                'expires_at' => now()->getTimestamp() + $policy['lockout_seconds'],
            ],
            now()->addSeconds($policy['lockout_seconds'])
        );

        return $attempts;
    }

    /**
     * Clear the counter for this client. Called on a SUCCESSFUL credential
     * check, so "3 attempts" always means three FAILURES and never three
     * lifetime logins.
     *
     * Only a successful authentication clears the counter: a different email,
     * a different module or a refreshed page never does.
     */
    public static function clear(Request $request): void
    {
        Cache::forget(self::key($request));
    }

    /**
     * Seconds until the block lifts, or 0 when nothing is recorded.
     */
    public static function secondsRemaining(Request $request): int
    {
        $entry = self::entry($request);

        if ($entry === null) {
            return 0;
        }

        return max(0, $entry['expires_at'] - now()->getTimestamp());
    }

    /**
     * Render a wait duration for the user without exposing internals.
     *
     * Rounds UP to the next whole unit so the message is never optimistic: a
     * 61-second wait is reported as "2 minute(s)", never as "1".
     */
    public static function humaniseSeconds(int $seconds): string
    {
        $seconds = max(1, $seconds);

        if ($seconds >= 3600) {
            return (int) ceil($seconds / 3600) . ' hour(s)';
        }

        if ($seconds >= 60) {
            return (int) ceil($seconds / 60) . ' minute(s)';
        }

        return $seconds . ' second(s)';
    }

    /**
     * The user-facing message for a currently-blocked client.
     *
     * Deliberately module-neutral: the block applies to every login page, and
     * naming a module would suggest (wrongly) that switching pages helps.
     */
    public static function lockedMessage(Request $request): string
    {
        $wait = self::secondsRemaining($request);

        return 'Too many failed login attempts. '
            . 'Login has been temporarily blocked. Please try again in '
            . self::humaniseSeconds($wait) . '.';
    }

    /**
     * The user-facing message after recording a failure, including how many
     * attempts are left. Once the budget is exhausted it returns the lock
     * message instead, so a caller cannot report "0 remaining" and still let the
     * user through.
     */
    public static function failureMessage(Request $request): string
    {
        if (self::isLocked($request)) {
            return self::lockedMessage($request);
        }

        $remaining = self::remaining($request);

        return 'Invalid email or password. ' . $remaining . ' attempt(s) remaining.';
    }
}
