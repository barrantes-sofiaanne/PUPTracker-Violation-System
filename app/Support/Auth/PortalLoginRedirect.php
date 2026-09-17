<?php

namespace App\Support\Auth;

use App\Models\Admin;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Which LOGIN PAGE an account uses.
 *
 * WHY THIS EXISTS
 * ---------------
 * Security Personnel and OSS Staff share ONE login page (`security.login`), but
 * they are different account types on different guards:
 *
 *   Security Personnel -> `security` guard -> Security module
 *   OSS Staff          -> `admin`    guard -> OSS Staff module
 *
 * The shared page authenticates both, and after a cancelled/failed MFA the user
 * must be returned to the page they actually came from. Deriving that from the
 * account (rather than assuming "admin guard => admin login page") keeps an OSS
 * Staff member inside the shared portal instead of dropping them on the
 * Administrator login they never used.
 *
 * Regular Administrators and IT Administrators keep `admin.login`: they do NOT
 * use the shared Security/OSS Staff portal.
 */
final class PortalLoginRedirect
{
    /** The shared Security + OSS Staff login route name. */
    public const SHARED_PORTAL = 'security.login';

    /** The Administrator login route name (regular Admin / IT Administrator). */
    public const ADMIN_PORTAL = 'admin.login';

    /**
     * The login route for an arbitrary admin-guard account.
     *
     * Fails CLOSED: an account whose role cannot be resolved is treated as a
     * regular Administrator and sent to the Administrator login, never into the
     * shared portal.
     */
    public static function forAdmin(?Admin $admin): string
    {
        return $admin instanceof Admin && $admin->isOssStaff()
            ? self::SHARED_PORTAL
            : self::ADMIN_PORTAL;
    }

    /**
     * The login route for a guard + account pair.
     *
     * A null account (e.g. an unauthenticated request) falls back to the
     * guard's own login page, which is the existing behaviour.
     */
    public static function forGuard(string $guard, ?Authenticatable $user = null): string
    {
        if ($guard === 'admin') {
            return self::forAdmin($user instanceof Admin ? $user : null);
        }

        return $guard === 'security' ? self::SHARED_PORTAL : 'student.login';
    }
}
