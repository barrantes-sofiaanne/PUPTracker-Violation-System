<?php

namespace App\Support;

use App\Models\MfaTrustedDevice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class MfaTrustedDeviceService
{
    private const COOKIE_NAME = 'mfa_trusted_device';

    private const LIFETIME_MINUTES = 720;

    /**
     * Create a trusted-device token after successful MFA.
     *
     * Only the SHA-256 hash is stored in the database.
     * The raw token exists only in the browser cookie.
     */
    public function trust(string $guard, int $userId): void
    {
        if (!in_array($guard, ['admin', 'security'], true)) {
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

        Cookie::queue(
            Cookie::make(
                self::COOKIE_NAME,
                $token,
                self::LIFETIME_MINUTES,
                '/',
                null,
                true,
                true,
                false,
                'lax'
            )
        );
    }

    /**
     * Determine whether this browser has a valid MFA trust record
     * for the specified account.
     */
    public function isTrusted(string $guard, int $userId): bool
    {
        if (!in_array($guard, ['admin', 'security'], true)) {
            return false;
        }

        $token = request()->cookie(self::COOKIE_NAME);

        if (!is_string($token) || $token === '') {
            return false;
        }

        $device = MfaTrustedDevice::query()
            ->active()
            ->where('guard', $guard)
            ->where('user_id', $userId)
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($device) {
            return true;
        }

        return false;
    }

    /**
     * Remove this browser's trusted-device record and cookie.
     */
    public function forget(): void
    {
        $token = request()->cookie(self::COOKIE_NAME);

        if (is_string($token) && $token !== '') {
            MfaTrustedDevice::query()
                ->where('token_hash', hash('sha256', $token))
                ->delete();
        }

        Cookie::queue(Cookie::forget(self::COOKIE_NAME));
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
}