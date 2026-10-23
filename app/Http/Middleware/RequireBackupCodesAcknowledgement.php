<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireBackupCodesAcknowledgement
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->hasSession()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        $allowedRoutes = [
            'totp.backup-codes',
            'totp.confirm-backup-codes',
            'logout',
            'admin.logout',
            'security.logout',
        ];

        if (!(bool) $request->session()->get('mfa_backup_codes_pending', false)
            || in_array($routeName, $allowedRoutes, true)
            || $request->is('totp/backup-codes')
            || $request->is('totp/confirm-backup-codes')) {
            return $next($request);
        }

        foreach (['admin', 'security'] as $guard) {
            if (auth()->guard($guard)->check()) {
                return redirect()->route('totp.backup-codes', ['guard' => $guard]);
            }
        }

        return $next($request);
    }
}
