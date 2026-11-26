<?php

namespace App\Support;

use App\Models\MfaTrustedDevice;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class MfaTrustedDeviceService
{
    /**
     * Base name of the remembered-verification cookie.
     *
     * The cookie actually sent to the browser is SCOPED PER ACCOUNT AND GUARD
     * (see {@see self::cookieName()}) so several accounts can hold a valid
     * remembered verification in ONE browser: signing in as a second account
     * writes a second cookie instead of overwriting the first.
     */
    private const COOKIE_PREFIX = 'mfa_trusted_device';

    /**
     * The single (unscoped) cookie name used before per-account cookies existed.
     *
     * Still READ as a fallback so a device trusted just before this change keeps
     * working until its own 12-hour window lapses. It is never written.
     */
    private const LEGACY_COOKIE_NAME = 'mfa_trusted_device';

    /** Exact maximum lifetime of a remembered verification: 12 hours. */
    private const LIFETIME_MINUTES = 720;

    /** Guards that may ever hold a remembered verification. */
    private const SUPPORTED_GUARDS = ['admin', 'security'];

    /**
     * The cookie name for one account on one guard.
     *
     * Scoping the cookie by guard + account id is what isolates remembered
     * verifications from each other: account switching in one browser can only
     * read the cookie belonging to the account being authenticated, and the
     * stored token is still validated against that same guard + user id.
     */
    public static function cookieName(string $guard, int $userId): string
    {
        return self::COOKIE_PREFIX . '_' . $guard . '_' . $userId;
    }

    /** The exact configured lifetime, in minutes (12 hours). */
    public static function lifetimeMinutes(): int
    {
        return self::LIFETIME_MINUTES;
    }

    /**
     * Create a trusted-device token after successful MFA.
     *
     * Only the SHA-256 hash is stored in the database; the raw token exists ONLY
     * in this account's browser cookie.
     *
     * A repeated remembered login does NOT renew the window: if this browser
     * already presents a token for this account that is still valid, the
     * ORIGINAL record is left untouched (logout and re-login therefore never
     * extend the 12 hours).
     */
    public function trust(string $guard, int $userId): void
    {
        if (!in_array($guard, self::SUPPORTED_GUARDS, true)) {
            return;
        }

        $presented = $this->presentedToken($guard, $userId);

        if ($presented !== null && $this->hasActiveRecord($guard, $userId, $presented)) {
            return;
        }

        $token = Str::random(64);
        $expiresAt = now()->addMinutes(self::LIFETIME_MINUTES);

        MfaTrustedDevice::query()->create([
            'guard' => $guard,
            'user_id' => $userId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
        ]);

        $this->queueCookie($guard, $userId, $token, self::LIFETIME_MINUTES);
    }

    /**
     * Determine whether this browser has a valid MFA trust record for the
     * specified account.
     *
     * The expiry is enforced SERVER-SIDE on every check (`active()` + the
     * `expires_at` comparison), so an expired record is never accepted even if
     * the browser keeps presenting its cookie.
     */
    public function isTrusted(string $guard, int $userId): bool
    {
        if (!in_array($guard, self::SUPPORTED_GUARDS, true)) {
            return false;
        }

        $token = $this->presentedToken($guard, $userId);

        if ($token === null) {
            return false;
        }

        return $this->hasActiveRecord($guard, $userId, $token);
    }

    /**
     * Remove the legacy (unscoped) trusted-device cookie.
     *
     * Kept for backwards compatibility and explicit "forget this device" flows.
     * Ordinary LOGOUT deliberately does NOT call this: logging out must not
     * discard a still-valid remembered verification.
     */
    public function forget(): void
    {
        $token = request()->cookie(self::LEGACY_COOKIE_NAME);

        if (is_string($token) && $token !== '') {
            MfaTrustedDevice::query()
                ->where('token_hash', hash('sha256', $token))
                ->delete();
        }

        $this->queueCookieValue(self::LEGACY_COOKIE_NAME, null, -2628000);
    }

    /**
     * Remove one account's remembered verification (explicit "forget this
     * device", or a revoked/rotated credential).
     */
    public function forgetFor(string $guard, int $userId): void
    {
        $token = $this->presentedToken($guard, $userId);

        if ($token !== null) {
            MfaTrustedDevice::query()
                ->where('guard', $guard)
                ->where('user_id', $userId)
                ->where('token_hash', hash('sha256', $token))
                ->delete();
        }

        $this->queueCookieValue(self::cookieName($guard, $userId), null, -2628000);
    }

    /**
     * Remove expired trusted-device records.
     */
    public function purgeExpired(): void
    {
        MfaTrustedDevice::query()
            ->where('expires_at', '<=', now())
            ->delete();
    }

    /**
     * The raw token this browser presents for the account, or null.
     *
     * The per-account cookie is authoritative. The legacy single cookie is read
     * only as a fallback, and even then the token must still match a record for
     * THIS guard + account id before it is honoured.
     */
    private function presentedToken(string $guard, int $userId): ?string
    {
        $scoped = request()->cookie(self::cookieName($guard, $userId));

        if (is_string($scoped) && $scoped !== '') {
            return $scoped;
        }

        $legacy = request()->cookie(self::LEGACY_COOKIE_NAME);

        return is_string($legacy) && $legacy !== '' ? $legacy : null;
    }

    /**
     * Whether the presented token matches a non-expired record for this exact
     * guard + account.
     */
    private function hasActiveRecord(string $guard, int $userId, string $token): bool
    {
        return MfaTrustedDevice::query()
            ->active()
            ->where('guard', $guard)
            ->where('user_id', $userId)
            ->where('token_hash', hash('sha256', $token))
            ->exists();
    }

    /**
     * Queue this account's remembered-verification cookie.
     *
     * The cookie carries ONLY an opaque random token: never a password, an MFA
     * code or any other authentication secret. It is HttpOnly (unreadable from
     * JavaScript), Secure, and SameSite=Lax.
     */
    private function queueCookie(string $guard, int $userId, string $token, int $minutes): void
    {
        $this->queueCookieValue(self::cookieName($guard, $userId), $token, $minutes);
    }

    private function queueCookieValue(string $name, ?string $value, int $minutes): void
    {
        Cookie::queue(
            Cookie::make(
                $name,
                $value,
                $minutes,
                '/',
                null,
                true,
                true,
                false,
                'lax'
            )
        );
    }
}
