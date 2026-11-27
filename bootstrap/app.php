<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Single daily backup job at 02:00 AM (Asia/Manila). The job itself
        // decides FULL vs INCREMENTAL vs DAILY_SNAPSHOT based on the elapsed
        // time since the last successful FULL backup (never calendar dates).
        $schedule->command('backup:run')
            ->dailyAt('02:00')
            ->timezone('Asia/Manila')
            ->withoutOverlapping(3600)
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/backup-scheduler.log'));

        // Chain-aware retention. Runs as a DRY RUN by default (nothing deleted)
        // so a Super Admin can review the plan. Auto-delete only if explicitly
        // enabled (BACKUP_RETENTION_AUTO_DELETE=true).
        $schedule->command('backup:prune' . (config('backup.retention.auto_delete') ? ' --force' : ''))
            ->dailyAt('03:30')
            ->timezone('Asia/Manila')
            ->withoutOverlapping(3600)
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/backup-scheduler.log'));

        // Housekeeping: drop EXPIRED MFA trusted-device rows only. Expiration is
        // already enforced during authentication (MfaTrustedDevice::scopeActive),
        // so this is hygiene, not a security control. It never touches a row
        // whose expires_at is still in the future.
        $schedule->call(function (): void {
            app(\App\Support\MfaTrustedDeviceService::class)->purgeExpired();
        })
            ->name('purge-expired-mfa-trusted-devices')
            ->dailyAt('04:00')
            ->timezone('Asia/Manila')
            ->withoutOverlapping(60)
            ->onOneServer();
    })
 ->withMiddleware(function (Middleware $middleware) {
    // Trusted proxies are configured in config/trustedproxy.php (`proxies`,
    // env TRUSTED_PROXIES), which Laravel's global TrustProxies middleware reads
    // per request. It is deliberately NOT set here: the middleware callback runs
    // before the config repository exists, and a bootstrap-time hardcode would
    // remove the ability to tighten the list per deployment.
    //
    // Default '*' = trust the CALLING IP only, which resolves the client IP from
    // the right-most X-Forwarded-For hop (the address the edge proxy observed),
    // so a client cannot change it by prefixing forged addresses.

    $middleware->append([
        \App\Http\Middleware\ForceHttps::class,
        \App\Http\Middleware\SecurityHeadersMiddleware::class,
    ]);

    // Enforce backup-code acknowledgement inside the web stack, after sessions start.
    $middleware->web(append: [\App\Http\Middleware\RequireBackupCodesAcknowledgement::class]);

    // MODULE-AWARE guest redirection.
    //
    // A single universal `student.login` target sent an unauthenticated
    // Administrator — and Security / OSS Staff — to the STUDENT login page, so a
    // genuinely expired session looked like the wrong portal had been opened.
    // Each module now returns to its OWN login page.
    //
    // OSS Staff authenticate as ADMIN accounts through the SHARED Security + OSS
    // Staff portal (`security.login`), so `/oss-staff/...` returns there too.
    // Routes outside a module prefix (the shared TOTP and announcement-attachment
    // groups) keep the previous `student.login` default, so their behaviour is
    // unchanged.
    $middleware->redirectGuestsTo(function (Request $request) {
        $loginRoutes = [
            'admin' => 'admin.login',
            'security' => 'security.login',
            'oss-staff' => 'security.login',
            'student' => 'student.login',
        ];

        $loginRoute = $loginRoutes[$request->segment(1)] ?? 'student.login';

        return \Illuminate\Support\Facades\Route::has($loginRoute)
            ? route($loginRoute)
            : route('student.login');
    });

    $middleware->alias([
        'super.admin' => \App\Http\Middleware\SuperAdminMiddleware::class,
        'student.profile.complete' => \App\Http\Middleware\EnsureStudentProfileComplete::class,
        // Strict module isolation — narrows the security-operational surfaces
        // to Security Personnel only, AFTER the auth guard has run.
        'security.operational' => \App\Http\Middleware\EnsureSecurityOperationalAccess::class,
        // PART 3/4/5 — 15-minute inactivity expiry, enforced server-side.
        'oss.staff' => \App\Http\Middleware\EnsureOssStaffAccess::class,
        // Admin-module authorisation: refuses OSS Staff (who share the admin
        // guard) while leaving every other Administrator untouched.
        'admin.module' => \App\Http\Middleware\AdminMiddleware::class,
        'session.inactivity' => \App\Http\Middleware\EnforceInactivityTimeout::class,
    ]);

    // Exempt ONLY the machine-to-machine backup report webhook from CSRF
    // verification. The Railway backup job cannot hold a browser session /
    // CSRF token; authentication is provided by the BACKUP_REPORT_TOKEN
    // (hash_equals) check inside BackupHistoryController@report. All other
    // POST/PUT/PATCH/DELETE routes keep normal CSRF protection.
    $middleware->validateCsrfTokens(except: [
        'admin/super-admin/backups/report',
    ]);
})
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
