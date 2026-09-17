<?php

namespace App\Support\Auth;

use App\Models\Admin;
use App\Models\Security;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Where an authenticated account belongs AFTER the second factor.
 *
 * WHY THIS EXISTS
 * ---------------
 * More than one place needs to answer "which module does this account land in?"
 * — the MFA verification step, and the TOTP enrolment/disable flows. Duplicating
 * the role rule in each of them is how an OSS Staff member ends up in the Admin
 * dashboard from one page and the OSS Staff dashboard from another.
 *
 * The rule itself:
 *   admin + IT Administrator -> the IT/Super Admin destination
 *   admin + OSS Staff        -> the OSS Staff module
 *   admin (anything else)    -> the Admin dashboard
 *   security                 -> the Security module
 *   student                  -> the Student module
 *
 * The admin branches FAIL CLOSED: an account whose role cannot be resolved
 * matches neither privileged branch and lands on `admin.dashboard`.
 */
final class AuthDestination
{
    /** The landing route for a guard + account pair. */
    public static function forGuard(string $guard, ?Authenticatable $user = null): string
    {
        return match ($guard) {
            'admin' => self::forAdmin($user instanceof Admin ? $user : null),
            'security' => 'security.dashboard',
            default => 'student.dashboard',
        };
    }

    /**
     * The landing route for an admin-guard account, resolved from its LOCAL role.
     *
     * NOTE: the IT Administrator check uses {@see Admin::isItAdministrator()},
     * which is the predicate the Super Admin middleware and the IDP login
     * destination already use — deliberately NOT a new, stricter rule, so an
     * existing account cannot be re-routed by this module.
     */
    public static function forAdmin(?Admin $admin): string
    {
        if (! $admin instanceof Admin) {
            return 'admin.dashboard';
        }

        // IT Administrator takes precedence: it is the more privileged module.
        if ($admin->isItAdministrator()) {
            return 'admin.super-admin.dashboard';
        }

        if ($admin->isOssStaff()) {
            return 'oss-staff.dashboard';
        }

        return 'admin.dashboard';
    }

    /** Convenience: the destination for a resolved account on any guard. */
    public static function forUser(?Authenticatable $user): string
    {
        return match (true) {
            $user instanceof Admin => self::forAdmin($user),
            $user instanceof Security => 'security.dashboard',
            $user instanceof User => 'student.dashboard',
            default => 'home',
        };
    }
}
