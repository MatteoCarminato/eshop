<?php

namespace App\Providers;

use App\Services\ClientService;
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
        //
    }
}
