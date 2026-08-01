<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $isLocal = app()->environment('local', 'testing');
        $localhost = $isLocal ? "'self' http://127.0.0.1:8000 http://localhost:8000" : "'self'";

        $csp = implode(' ', [
            "default-src {$localhost};",
            "base-uri {$localhost};",
            "form-action {$localhost};",
            "frame-ancestors {$localhost};",
            "object-src 'none';",
            "img-src {$localhost} data: https:;",
            "script-src {$localhost} 'unsafe-inline' https://cdn.jsdelivr.net;",
            "style-src {$localhost} 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com;",
            "font-src {$localhost} data: https://cdn.jsdelivr.net https://fonts.gstatic.com;",
            "connect-src {$localhost} https://cdn.jsdelivr.net;",
        ]);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // Always set HSTS header (production uses HTTPS)
        // Set to 1 year (31536000 seconds)
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        return $response;
    }
}
