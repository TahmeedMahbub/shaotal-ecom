<?php

namespace App\Providers;

use App\Models\Storefront;
use App\Support\StoreConfig;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Admin-managed config_data values: read once per request / queue job (scoped), never across them.
        $this->app->scoped(StoreConfig::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($proxies = config('storefront.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
        // Production behind HTTPS: generate https URLs even if a proxy forwards plain http.
        if ($this->app->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceHttps();
        }

        // One boot payload per request (kept on the request, so long-running workers and tests never reuse another
        // request's payload), shared by the layout and the page views.
        View::composer(['layouts.store', 'pages.*'], function ($view) {
            $request = request();
            $boot = $request->attributes->get('storefront.boot');
            if ($boot === null) {
                $data = $view->getData();
                $page = $data['page'] ?? [];
                // Page-scoped catalog: controllers pass the product ids the page renders ('all' only for the bulk order form).
                $boot = Storefront::boot(session('customer_id') ? (int) session('customer_id') : null, $page['slug'] ?? null, $data['catalogProducts'] ?? []);
                $request->attributes->set('storefront.boot', $boot);
            }
            $view->with('boot', $boot);
        });
    }
}
