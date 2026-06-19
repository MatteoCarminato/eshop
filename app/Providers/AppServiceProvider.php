<?php

namespace App\Providers;

use App\Services\ClientService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register Services as Singletons
        $this->app->singleton(ClientService::class, function ($app) {
            return new ClientService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        if (request()->isSecure() || (request()->server('HTTP_X_FORWARDED_PROTO') === 'https')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
