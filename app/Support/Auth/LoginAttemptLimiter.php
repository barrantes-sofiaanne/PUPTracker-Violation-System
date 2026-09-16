<?php

namespace App\Support\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Shared failed-login-attempt limiter ("login lockout").
 *
 * WHY THIS CLASS EXISTS
 * ---------------------
 * The Administrator and Security modules must apply the SAME rule. Keeping the
 * counter, the threshold and the message formatting in one place means the two
 * login controllers cannot drift apart, and any future module only has to call
 * this class instead of re-implementing the logic.
 *
 * PER-MODULE ISOLATION (a hard requirement)
 * -----------------------------------------
 * The cache key includes the MODULE name as well as the submitted email and
 * client IP, so:
 *   - a lockout on the Administrator login never blocks the Security login
 *     (separate key namespaces), and
 *   - a lockout on one account never blocks another account.
 *
 * WHY THE SUBMITTED EMAIL, NOT THE RESOLVED ACCOUNT
 * -------------------------------------------------
 * Keying on the resolved account would mean an unknown email never increments a
 * counter (free guessing against non-existent accounts), and the differing
 * responses would leak whether an account exists. Keying on the submitted value
 * plus the IP counts every failed attempt and answers identically either way.
 *
 * WHY THE CACHE, NOT THE SESSION
 * ------------------------------
 * The brief requires that refreshing the login page must NOT reset the counter.
 * A session-backed counter is trivially reset by discarding the session cookie,
 * and a brand-new visitor gets a fresh session anyway. The application cache is
 * server-side and survives both.
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
    private const PREFIX = 'login-attempts:v1';

    /**
     * Resolve the configured budget for a module, falling back to the default
     * when the module has no entry of its own.
     *
     * @return array{max:int,lockout_seconds:int}
     */
    public static function policy(string $module): array
    {
        $default = (array) config('auth.login_attempts.default', []);
        $configured = (array) config('auth.login_attempts.' . $module, []);

        $max = (int) ($configured['max'] ?? $default['max'] ?? 3);
        $lockout = (int) ($configured['lockout_seconds'] ?? $default['lockout_seconds'] ?? 900);

        // Fail closed on nonsense configuration: a threshold below 1 would block
        // every login immediately, and a zero lockout would never lock at all.
        return [
            'max' => max(1, $max),
            'lockout_seconds' => max(1, $lockout),
        ];
    }

    /**
     * Cache key for one account within one module.
     *
     * The module is part of the key, which is what keeps the modules isolated.
     */
    public static function key(string $module, Request $request): string
    {
        $submitted = strtolower(trim((string) $request->input('email')));

        return self::PREFIX . ':' . $module . ':' . sha1($submitted . '|' . (string) $request->ip());
    }

    /**
     * The stored entry, or null when nothing has been recorded.
     *
     * Reading never creates an entry, so merely visiting the login page cannot
     * start a counter.
     *
     * @return array{attempts:int,expires_at:int}|null
     */
    private static function entry(string $module, Request $request): ?array
    {
        $value = Cache::get(self::key($module, $request));

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
     * Number of failed attempts RECORDED so far.
     */
    public static function attempts(string $module, Request $request): int
    {
        $entry = self::entry($module, $request);

        return $entry === null ? 0 : $entry['attempts'];
    }

    /**
     * Is this module/account currently blocked?
     */
    public static function isLocked(string $module, Request $request): bool
    {
        return self::attempts($module, $request) >= self::policy($module)['max'];
    }

    /**
     * Remaining attempts before the block engages (never negative).
     */
    public static function remaining(string $module, Request $request): int
    {
        $policy = self::policy($module);

        return max(0, $policy['max'] - self::attempts($module, $request));
    }

    /**
     * Record one failed attempt and (re)arm the expiry window.
     *
     * Each recording rewrites the window, so the lockout is measured from the
     * most recent failure. Continuing to guess therefore cannot shorten the
     * block, and a user who stops trying is released after the configured wait.
     */
    public static function recordFailure(string $module, Request $request): int
    {
        $policy = self::policy($module);
        $attempts = self::attempts($module, $request) + 1;

        Cache::put(
            self::key($module, $request),
            [
                'attempts' => $attempts,
                'expires_at' => now()->getTimestamp() + $policy['lockout_seconds'],
            ],
            now()->addSeconds($policy['lockout_seconds'])
        );

        return $attempts;
    }

    /**
     * Clear the counter for this module/account. Called on a SUCCESSFUL
     * credential check, so "3 attempts" always means three FAILURES and never
     * three lifetime logins.
     */
    public static function clear(string $module, Request $request): void
    {
        Cache::forget(self::key($module, $request));
    }

    /**
     * Seconds until the block lifts, or 0 when nothing is recorded.
     */
    public static function secondsRemaining(string $module, Request $request): int
    {
        $entry = self::entry($module, $request);

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
     * The user-facing message for a currently-blocked module.
     *
     * Names the module so the user can tell WHICH login is blocked, which matters
     * because a lockout is deliberately scoped to one module.
     */
    public static function lockedMessage(string $module, Request $request): string
    {
        $wait = self::secondsRemaining($module, $request);

        return 'Too many failed login attempts for this module. '
            . 'Login has been temporarily blocked. Please try again in '
            . self::humaniseSeconds($wait) . '.';
    }

    /**
     * The user-facing message after recording a failure, including how many
     * attempts are left. Once the budget is exhausted it returns the lock
     * message instead, so a caller cannot report "0 remaining" and still let the
     * user through.
     */
    public static function failureMessage(string $module, Request $request): string
    {
        if (self::isLocked($module, $request)) {
            return self::lockedMessage($module, $request);
        }

        $remaining = self::remaining($module, $request);

        return 'Invalid email or password. ' . $remaining . ' attempt(s) remaining.';
    }
}
