<?php

namespace App\Providers;

use App\Contracts\TransactionEngineInterface;
use App\Services\DatabaseTransactionEngine;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TransactionEngineInterface::class, DatabaseTransactionEngine::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
