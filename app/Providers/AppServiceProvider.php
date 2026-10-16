<?php

namespace App\Providers;

use App\Models\Storefront;
use Illuminate\Support\Facades\View;
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
        // One boot payload per request, shared by the layout and the page views.
        $boot = null;
        View::composer(['layouts.store', 'pages.*'], function ($view) use (&$boot) {
            $boot ??= Storefront::boot(session('customer_id') ? (int) session('customer_id') : null);
            $view->with('boot', $boot);
        });
    }
}
