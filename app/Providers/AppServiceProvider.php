<?php

namespace App\Providers;

use App\Services\StockPriceService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StockPriceService::class, fn () => new StockPriceService());

        // Bind WebauthnService so any construction error is surfaced at request time
        // (not during bootstrap) and caught by the controller's try/catch.
        $this->app->bind(\App\Services\WebauthnService::class, fn () => new \App\Services\WebauthnService());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
