<?php

namespace App\Providers;

use App\Models\Announcement;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Dedicated limiter for the token-protected backup report webhook.
        // The Railway backup job legitimately posts only a handful of reports
        // (per backup run), so a strict per-minute cap stops brute-forcing the
        // shared token while never blocking legitimate reports.
        RateLimiter::for('backup-report', function (Request $request) {
            return Limit::perMinute(10)->by(
                'backup-report:' . ($request->ip() ?? 'unknown')
            );
        });

        View::composer(
            ['layouts.admin', 'layouts.student', 'layouts.security'],
            function ($view): void {
                if (!session()->get('show_login_announcement_modal', false)) {
                    $view->with('loginAnnouncementModal', null);
                    return;
                }

                $isAuthenticated = Auth::guard('admin')->check() ||
                    Auth::guard('student')->check() ||
                    Auth::guard('security')->check();

                if (!$isAuthenticated) {
                    $view->with('loginAnnouncementModal', null);
                    return;
                }

                $admin = Auth::guard('admin')->user();
                if ($admin && method_exists($admin, 'isItAdministrator') && $admin->isItAdministrator()) {
                    $view->with('loginAnnouncementModal', null);
                    return;
                }

                $announcement = Announcement::query()
                    ->with('attachments')
                    ->where('show_on_login', true)
                    ->where(function ($query): void {
                        $query->whereNull('login_modal_expires_at')
                            ->orWhere('login_modal_expires_at', '>', now());
                    })
                    ->latest('created_at')
                    ->first();

                $view->with('loginAnnouncementModal', $announcement);
            }
        );
        if (app()->environment('production')) {
            URL::forceScheme('https');
            URL::forceRootUrl(config('app.url'));
        }
    }
}
