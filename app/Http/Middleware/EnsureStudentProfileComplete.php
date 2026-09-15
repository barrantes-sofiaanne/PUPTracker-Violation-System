<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures an authenticated student has COMPLETED first-time registration
 * before reaching the Student Module.
 *
 * Why this exists: the IDP callback can only gate the FIRST redirect after
 * login. A student could otherwise reach the dashboard later by deep-linking,
 * using browser history, or re-submitting a URL. This middleware makes
 * "registration complete" an enforced precondition on every protected student
 * request, not just a one-off redirect.
 *
 * It runs AFTER `auth:student`, so an unauthenticated visitor never reaches
 * the check — they are bounced to the login page by the auth guard.
 *
 * Only students are affected: the admin and security guards never resolve here,
 * so no other module's behaviour changes.
 */
class EnsureStudentProfileComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $student = Auth::guard('student')->user();

        // No student in this guard (e.g. a different guard's request) — leave it
        // to the route's own auth middleware to decide.
        if (! $student) {
            return $next($request);
        }

        // Already registered → normal flow.
        if (method_exists($student, 'hasCompletedProfile') && $student->hasCompletedProfile()) {
            return $next($request);
        }

        // First-time registrant with an AUTHENTICATED IDP session still staged:
        // send them to the registration page to finish.
        if ($request->session()->get('student.idp.pending_profile') !== null) {
            return redirect()
                ->route('student.idp.link')
                ->with('info', 'Please complete your student information to continue.');
        }

        // No completed profile AND no staged IDP session: the student cannot be
        // trusted to register (we have no verified IDP identity to associate).
        // Log them out and send them through IDP login again — never a
        // registration page without a verified IDP identity.
        Auth::guard('student')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('student.login')
            ->withErrors([
                'login' => 'Please sign in with your PUP IDP account to complete your registration.',
            ]);
    }
}
