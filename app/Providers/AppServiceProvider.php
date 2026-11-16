<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        if ($this->app->environment('production', 'staging') || env('FORCE_HTTPS', false)) {
            URL::forceScheme('https');
        }

        // Set custom corporate Pelindo pagination view (anti-giant SVG icon)
        Paginator::defaultView('vendor.pagination.corporate');
        Paginator::defaultSimpleView('vendor.pagination.corporate');

        view()->composer([
            'admin.reports.print_*',
            'user.laporan.print',
        ], function ($view) {
            $view->with('branchSettings', Setting::getAllKeyValue());
        });
    }
}
