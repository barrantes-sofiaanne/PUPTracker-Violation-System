<?php

namespace App\Support\Access;

use App\Models\Admin;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * PART 1 / PART 2 — the security-operational access rule.
 *
 * The Security module is gated by `auth:security`. The Admin Assistant is an
 * ADMIN account that must reach the SAME operational surfaces, so the
 * `security` route groups accept the `admin` guard as well and then defer the
 * actual decision to this class.
 *
 * WHY NOT JUST TRUST THE GUARD
 * ----------------------------
 * Accepting `auth:admin` on those routes would let EVERY administrator —
 * including a plain Administrator and an IT Administrator — record violations
 * through the security-operational endpoints. That would GRANT access, which
 * the brief forbids. So the guard swap is paired with this explicit,
 * server-side check: only an Admin Assistant may proceed.
 *
 * "Same features as Security" is deliberately scoped to OPERATIONAL
 * functionality. This class grants nothing beyond what Security Personnel can
 * already do, and never implies Super Admin capability.
 */
final class SecurityAccess
{
    /**
     * May this account use the security-operational surfaces?
     *
     * - Security Personnel: yes (they ARE the reference role).
     * - Admin Assistant:    yes (the whole point of the role).
     * - Anyone else:        no.
     */
    public static function allowed(?Authenticatable $user): bool
    {
        if ($user === null) {
            return false;
        }

        // A Security Personnel account always may.
        if ($user instanceof \App\Models\Security) {
            return true;
        }

        // An ADMIN account may only when it holds the Admin Assistant role.
        if ($user instanceof Admin) {
            return $user->isAdminAssistant();
        }

        return false;
    }

    /**
     * The route group a permitted account should be sent to.
     *
     * Both roles share the same operational pages, so this is currently a single
     * destination; it exists so a caller never has to re-derive the rule.
     */
    public static function landingRoute(?Authenticatable $user): string
    {
        return 'security.dashboard';
    }
}
