<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOssStaffAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('admin');

        if (! $user instanceof Admin || ! $user->isOssStaff()) {
            abort(403, 'You are not authorized to access the OSS Staff module.');
        }

        return $next($request);
    }
}