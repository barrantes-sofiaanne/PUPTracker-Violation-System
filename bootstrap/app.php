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
    })
 ->withMiddleware(function (Middleware $middleware) {
    $middleware->trustProxies(at: '*');

    $middleware->append([
        \App\Http\Middleware\ForceHttps::class,
        \App\Http\Middleware\SecurityHeadersMiddleware::class,
    ]);

    $middleware->redirectGuestsTo(function (Request $request) {
        return route('student.login');
    });

    $middleware->alias([
        'super.admin' => \App\Http\Middleware\SuperAdminMiddleware::class,
        'student.profile.complete' => \App\Http\Middleware\EnsureStudentProfileComplete::class,
        // Strict module isolation — narrows the security-operational surfaces
        // to Security Personnel only, AFTER the auth guard has run.
        'security.operational' => \App\Http\Middleware\EnsureSecurityOperationalAccess::class,
        // PART 3/4/5 — 15-minute inactivity expiry, enforced server-side.
        'oss.staff' => \App\Http\Middleware\EnsureOssStaffAccess::class,
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
