<?php

namespace App\Providers;

use App\Models\Announcement;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

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
                if ($admin && $admin->isItAdministrator()) {
                    $view->with('loginAnnouncementModal', null);
                    return;
                }

                $announcement = Announcement::query()
                    ->where('show_on_login', true)
                    ->latest('created_at')
                    ->first();

                $view->with('loginAnnouncementModal', $announcement);
            }
        );
    }
}
