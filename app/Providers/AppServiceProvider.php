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
     * The name of the custom user-provider driver used by the Admin and
     * Security guards (config/auth.php → providers.{admins,security}.driver).
     */
    private const STATE_AWARE_PROVIDER = 'state-aware';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerStateAwareUserProvider();
    }

    /**
     * Register the "state-aware" user provider driver.
     *
     * WHY THIS IS IN register() AND NOT boot()
     * ----------------------------------------
     * `Auth::provider()` only records a creator on the AuthManager; a guard is
     * built lazily the first time it is resolved, and it throws
     * "Authentication user provider [state-aware] is not defined." if the
     * creator is not registered yet.
     *
     * Every provider's register() runs BEFORE any provider's boot(), so
     * registering here guarantees the creator exists before ANY code that could
     * touch a guard — another provider's boot(), the schedule callbacks, route
     * or middleware setup, or a console command. Registering in boot() only
     * worked because nothing resolved a guard before this provider booted; that
     * is an ordering assumption this method removes.
     *
     * Laravel registers Illuminate\Auth\AuthServiceProvider (which binds the
     * `auth` singleton) before application providers, so `auth` is always
     * resolvable at this point.
     *
     * The driver is registered only when a guard actually asks for it: the
     * closure defers building the provider until then, and the class itself
     * (App\Support\Auth\StateAwareUserProvider) still gates remembered-device
     * restoration on account state.
     */
    private function registerStateAwareUserProvider(): void
    {
        $this->app['auth']->provider(
            self::STATE_AWARE_PROVIDER,
            function ($app, array $config): \App\Support\Auth\StateAwareUserProvider {
                return new \App\Support\Auth\StateAwareUserProvider(
                    $app['hash'],
                    $config['model']
                );
            }
        );
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
