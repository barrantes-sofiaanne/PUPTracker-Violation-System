<?php

namespace App\Support\Access;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The security-operational access rule — STRICT MODULE ISOLATION.
 *
 * The Security module belongs to Security Personnel ONLY. The `security` route
 * groups still accept the `admin` guard, but that is purely so an `admins` row
 * can be IDENTIFIED and then explicitly REFUSED here: the guard alone is never
 * proof of operational rights.
 *
 * WHY THE ADMIN GUARD IS STILL ACCEPTED BY `auth`
 * -----------------------------------------------
 * OSS Staff accounts are `admins` rows. If the Security routes only accepted
 * `auth:security`, an OSS Staff member would be bounced to the login page
 * rather than told they are not authorized — a confusing, misleading outcome
 * for an account that IS authenticated. Accepting the guard and then refusing
 * the account produces an honest 403.
 *
 * WHO IS REFUSED
 * --------------
 *   - OSS Staff          -> owns the SEPARATE `/oss-staff/...` module. Its
 *                           activity must never be recorded as Security.
 *   - Regular Admin      -> owns the Admin module.
 *   - IT Administrator   -> owns the Admin/IT (Super Admin) module.
 *   - Anything else      -> fail closed.
 *
 * A DENIED account is not merely redirected elsewhere; the Security module is
 * simply not theirs.
 */
final class SecurityAccess
{
    /**
     * May this account use the security-operational surfaces?
     *
     * Security Personnel only. Every other account — including an OSS Staff
     * admin — is refused.
     */
    public static function allowed(?Authenticatable $user): bool
    {
        // Fail closed: an absent identity can never be authorized, and no
        // non-Security account type is granted access here.
        return $user instanceof \App\Models\Security;
    }

    /**
     * The route group a permitted account should be sent to.
     */
    public static function landingRoute(?Authenticatable $user): string
    {
        return 'security.dashboard';
    }
}
